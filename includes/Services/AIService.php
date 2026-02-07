<?php
namespace ProOneAI\Services;

class AIService {
    private $groq_api_key;
    private $huggingface_api_key;
    
    private $groq_api_url = 'https://api.groq.com/openai/v1/chat/completions';
    
    // CORREÇÃO: URL do Router da Hugging Face
    // Documentação: https://huggingface.co/docs/inference-endpoints/index
    private $hf_api_base = 'https://router.huggingface.co/hf-inference/models/';

    public function __construct() {
        $this->groq_api_key = get_option('p1ai_groq_api_key');
        $this->huggingface_api_key = get_option('p1ai_huggingface_api_key');
    }

    public function generate_content($model, $prompt, $system_instruction, $temperature = 0.2) {
        if (strpos($model, 'hf_') === 0) {
            $real_model_id = str_replace('hf_', '', $model);
            return $this->generate_huggingface_text($real_model_id, $prompt, $system_instruction, $temperature);
        } else {
            return $this->generate_groq_text($model, $prompt, $system_instruction, $temperature);
        }
    }

    private function generate_groq_text($model, $prompt, $system_instruction, $temperature) {
        if (empty($this->groq_api_key)) {
            return new \WP_Error('no_api_key', 'Chave API da Groq não configurada.');
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_instruction],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => (float)$temperature,
            'max_tokens' => 8000
        ];

        $args = [
            'body'        => json_encode($body),
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->groq_api_key
            ],
            'timeout'     => 60,
            'method'      => 'POST',
            'data_format' => 'body',
        ];

        $response = wp_remote_post($this->groq_api_url, $args);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            return new \WP_Error('api_error', 'Groq Error: ' . ($data['error']['message'] ?? 'Erro desconhecido'));
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        return $this->parse_json_response($content);
    }

    private function generate_huggingface_text($model_id, $prompt, $system_instruction, $temperature) {
        if (empty($this->huggingface_api_key)) {
            return new \WP_Error('no_api_key', 'Chave API do Hugging Face não configurada.');
        }

        // Tenta a rota de chat completion via Router
        $url = $this->hf_api_base . $model_id . '/v1/chat/completions';
        
        $body = [
            'model' => $model_id,
            'messages' => [
                ['role' => 'system', 'content' => $system_instruction],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float)$temperature,
            'max_tokens' => 4000,
            'stream' => false
        ];

        $args = [
            'body'        => json_encode($body),
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->huggingface_api_key
            ],
            'timeout'     => 120,
            'method'      => 'POST',
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        // Fallback se rota /v1/chat não existir (404)
        if (wp_remote_retrieve_response_code($response) == 404) {
            return $this->generate_huggingface_text_fallback($model_id, $prompt, $system_instruction, $temperature);
        }

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            if (is_string($data['error']) && strpos($data['error'], 'loading') !== false) {
                return new \WP_Error('api_loading', 'O modelo ' . $model_id . ' está carregando (Cold Boot). Tente novamente em 30s.');
            }
            return new \WP_Error('api_error', 'HF Error: ' . (is_array($data['error']) ? json_encode($data['error']) : $data['error']));
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($content) && isset($data[0]['generated_text'])) {
             $content = $data[0]['generated_text'];
        }

        return $this->parse_json_response($content);
    }

    private function generate_huggingface_text_fallback($model_id, $prompt, $system_instruction, $temperature) {
        $url = $this->hf_api_base . $model_id;
        
        $full_prompt = "<|system|>\n$system_instruction\n<|user|>\n$prompt\n<|assistant|>\n";

        $body = [
            'inputs' => $full_prompt,
            'parameters' => [
                'max_new_tokens' => 4000,
                'temperature' => (float)$temperature,
                'return_full_text' => false
            ]
        ];

        $args = [
            'body'    => json_encode($body),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->huggingface_api_key
            ],
            'timeout' => 120,
            'method'  => 'POST'
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) return new \WP_Error('api_error', 'HF Fallback Error: ' . (is_array($data['error']) ? json_encode($data['error']) : $data['error']));
        
        $text = isset($data[0]['generated_text']) ? $data[0]['generated_text'] : '';
        return $this->parse_json_response($text);
    }

    public function generate_huggingface_image($model_id, $prompt) {
        if (empty($this->huggingface_api_key) || empty($prompt)) return false;

        // URL para imagem via Router
        $url = $this->hf_api_base . $model_id;
        
        $body = [
            'inputs' => $prompt,
        ];

        $args = [
            'body'    => json_encode($body),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->huggingface_api_key
            ],
            'timeout' => 120, 
            'method'  => 'POST'
        ];

        $response = wp_remote_post($url, $args);

        // Retry simples para erro 503 (Model Loading)
        if (wp_remote_retrieve_response_code($response) == 503) {
            sleep(8); 
            $response = wp_remote_post($url, $args); 
        }

        if (is_wp_error($response)) {
            error_log('P1AI: Erro na requisição HF Image: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $body_content = wp_remote_retrieve_body($response);

        // Verifica erro JSON
        if (strpos($content_type, 'application/json') !== false) {
            $json = json_decode($body_content, true);
            if (isset($json['error'])) {
                error_log('P1AI: HF Image Retornou Erro JSON: ' . (is_array($json['error']) ? json_encode($json['error']) : $json['error']));
                return false;
            }
        }

        // Sucesso
        if ($response_code == 200 && !empty($body_content)) {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/p1ai_temp';
            
            if (!file_exists($temp_dir)) {
                mkdir($temp_dir, 0755, true);
            }

            // Define extensão
            $ext = 'jpeg';
            if (strpos($content_type, 'png') !== false) $ext = 'png';
            
            $filename = 'hf_gen_' . uniqid() . '.' . $ext;
            // Salva path absoluto para escrita
            $file_path = $temp_dir . '/' . $filename;
            // Retorna URL pública para o ImageService baixar
            $file_url = $upload_dir['baseurl'] . '/p1ai_temp/' . $filename;

            if (file_put_contents($file_path, $body_content)) {
                return $file_url;
            } else {
                error_log('P1AI: Falha ao escrever arquivo de imagem em: ' . $file_path);
            }
        }

        return false;
    }

    private function parse_json_response($raw_text) {
        $raw_text = preg_replace('/```json/i', '', $raw_text);
        $raw_text = preg_replace('/```/', '', $raw_text);
        
        $start = strpos($raw_text, '{');
        $end = strrpos($raw_text, '}');
        
        if ($start !== false && $end !== false) {
            $raw_text = substr($raw_text, $start, ($end - $start) + 1);
        } else {
             return new \WP_Error('json_error', 'Estrutura JSON não encontrada.');
        }

        $json = json_decode($raw_text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $raw_text = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $raw_text);
            $json = json_decode($raw_text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new \WP_Error('json_error', 'Erro ao decodificar JSON: ' . json_last_error_msg());
            }
        }
        return $json;
    }
}