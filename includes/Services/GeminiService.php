<?php
namespace GeminiVestibularAI\Services;

class GeminiService {
    private $api_key;
    private $api_url_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct() {
        $this->api_key = get_option('gva_gemini_api_key');
    }

    public function generate_content($model, $prompt, $system_instruction, $file_url = null) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Chave API do Gemini não configurada.');
        }

        $url = $this->api_url_base . $model . ':generateContent?key=' . $this->api_key;

        $user_parts = [];
        
        // Processar Arquivo (PDF)
        if ($file_url) {
            // Converter URL para caminho local se for do mesmo domínio
            $upload_dir = wp_upload_dir();
            if (strpos($file_url, $upload_dir['baseurl']) !== false) {
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                if (file_exists($file_path)) {
                    $file_data = base64_encode(file_get_contents($file_path));
                    $mime_type = mime_content_type($file_path);
                    
                    $user_parts[] = [
                        'inline_data' => [
                            'mime_type' => $mime_type,
                            'data' => $file_data
                        ]
                    ];
                }
            }
        }

        // Adicionar Prompt de Texto
        $user_parts[] = ['text' => $prompt];

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $user_parts
                ]
            ],
            'systemInstruction' => [
                'parts' => [['text' => $system_instruction]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.4 // Temperatura ajustada para precisão
            ]
        ];

        $args = [
            'body'        => json_encode($body),
            'headers'     => ['Content-Type' => 'application/json'],
            'timeout'     => 120,
            'method'      => 'POST',
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['error'])) {
            return new \WP_Error('api_error', $data['error']['message']);
        }

        $raw_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $raw_text = preg_replace('/^```json\s*|\s*```$/', '', $raw_text); // Limpeza Markdown
        
        return json_decode($raw_text, true);
    }
}