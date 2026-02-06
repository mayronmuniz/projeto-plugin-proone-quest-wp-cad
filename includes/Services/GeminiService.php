<?php
namespace GeminiVestibularAI\Services;

class GeminiService {
    private $api_key;
    private $api_url_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct() {
        $this->api_key = get_option('gva_gemini_api_key');
    }

    public function generate_content($model, $prompt, $system_instruction, $pdf_url = null) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Chave API do Gemini não configurada.');
        }

        $url = $this->api_url_base . $model . ':generateContent?key=' . $this->api_key;

        $parts = [['text' => $prompt]];

        // Handle PDF (Multimodal) - Using File API logic abstraction for simplicity
        // In a real scenario, we'd upload the file to Google AI Studio File API first, get URI, then pass here.
        // For this implementation, we assume text extraction OR small base64 provided in prompt context.
        // Se o usuário anexou PDF, o ideal é extrair o texto em PHP antes de enviar, pois a API direta de arquivos é complexa.
        
        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts
                ]
            ],
            'systemInstruction' => [
                'parts' => [['text' => $system_instruction]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.7
            ]
        ];

        $args = [
            'body'        => json_encode($body),
            'headers'     => ['Content-Type' => 'application/json'],
            'timeout'     => 120, // Long timeout for batch generation
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

        // Extract JSON from the text response
        $raw_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Clean markdown code blocks if present
        $raw_text = preg_replace('/^```json\s*|\s*```$/', '', $raw_text);
        
        return json_decode($raw_text, true);
    }
}