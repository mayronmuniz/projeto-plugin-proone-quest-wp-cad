<?php
namespace GeminiVestibularAI\Admin;

use GeminiVestibularAI\Services\GeminiService;
use GeminiVestibularAI\Services\ImageService;

class AjaxHandler {

    private $gemini;
    private $image_service;

    public function run() {
        $this->gemini = new GeminiService();
        $this->image_service = new ImageService();

        add_action('wp_ajax_gva_process_request', [$this, 'handle_request']);
    }

    public function handle_request() {
        check_ajax_referer(GVA_NONCE, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada.');
        }

        $type = sanitize_text_field($_POST['type']); // 'oficial' or 'similar'
        $params = isset($_POST['params']) ? $_POST['params'] : [];
        
        // 1. Preparar Instrução do Sistema (Baseada nos Prompts detalhados)
        $system_instruction = $this->get_system_instruction($type);

        // 2. Preparar Prompt do Usuário com os parâmetros do formulário
        $prompt = $this->build_user_prompt($type, $params);

        // 3. Chamada ao Gemini
        $pdf_url = isset($params['pdf_url']) ? esc_url_raw($params['pdf_url']) : null;
        $book_url = isset($params['book_url']) ? esc_url_raw($params['book_url']) : null;
        
        // Se houver PDF da prova, passamos a URL (ou processamos texto se a API exigir text-only)
        // Aqui assumimos que o serviço suporta a URL ou texto extraído
        
        $response = $this->gemini->generate_content(
            sanitize_text_field($params['ai_model']),
            $prompt,
            $system_instruction,
            $pdf_url // Passar PDF da prova se existir
        );

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        if (!isset($response['questions']) || !is_array($response['questions'])) {
            wp_send_json_error('Formato de resposta inválido da IA. Certifique-se de que o modelo retornou JSON válido.');
        }

        $results = [];

        // 4. Loop através das questões geradas e inserção no WP
        foreach ($response['questions'] as $q_data) {
            $post_id = $this->insert_question_into_wp($q_data, $type);
            
            if ($post_id) {
                // Log History
                $has_image = (isset($q_data['com_imagem']) && $q_data['com_imagem'] == '1');
                $this->log_history($q_data['codigo_questao'], $type, $params['ai_model'], $has_image);
                
                // Lógica de Geração de Imagem (Se "Com Imagem" estiver marcado)
                // Se for 'similar' e o usuário pediu imagem, ou se for 'oficial' e a IA detectou imagem
                if (($type === 'similar' && isset($params['com_imagem']) && $params['com_imagem'] == '1') || 
                    ($type === 'oficial' && $has_image && !empty($q_data['imagePrompt']))) {
                    
                    // Aqui você chamaria o serviço de geração de imagem (Imagen 3)
                    // $image_url = $this->gemini->generate_image($q_data['imagePrompt']);
                    // $this->image_service->process_image($image_url, $post_id, $q_data['codigo_questao']);
                }

                $results[] = [
                    'code' => $q_data['codigo_questao'],
                    'status' => 'success',
                    'id' => $post_id
                ];
            }
        }

        wp_send_json_success($results);
    }

    private function insert_question_into_wp($data, $origin_type) {
        // Criar Post do tipo 'questoes'
        $post_data = [
            'post_title'   => $data['codigo_questao'], // Código como título
            'post_content' => $data['enunciado'],      // Enunciado como conteúdo
            'post_status'  => 'publish',               // Publicado por padrão (ou draft conforme lógica)
            'post_type'    => 'questoes',              // Custom Post Type
        ];

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) return false;

        // Salvar Meta Fields (Campos personalizados)
        update_post_meta($post_id, 'resolucao', $data['resolucao']);
        update_post_meta($post_id, 'tipo_questao', $origin_type === 'oficial' ? 'Questão Oficial' : 'Questão Similar');
        
        // Dados taxonômicos armazenados como meta para referência rápida ou se a taxonomia falhar
        if (isset($data['taxonomies'])) {
            update_post_meta($post_id, 'nivel_dificuldade', $data['taxonomies']['difficulty']);
            update_post_meta($post_id, 'instituicao', $data['taxonomies']['institution']);
            update_post_meta($post_id, 'ano', $data['taxonomies']['year']);
            update_post_meta($post_id, 'versao', $data['taxonomies']['version'] ?? '');
            update_post_meta($post_id, 'dia', $data['taxonomies']['day'] ?? '');
        }

        // Alternativas e Textos de Apoio (JSON serializado)
        update_post_meta($post_id, 'alternativas', $data['alternativas_data']); 
        if (isset($data['support_texts_data'])) {
            update_post_meta($post_id, 'textos_apoio', $data['support_texts_data']);
        }

        // --- Tratamento de Taxonomias (Assuntos) ---
        // A IA pode retornar novos assuntos. Precisamos verificar se existem.
        if (isset($data['subjects_data']) && is_array($data['subjects_data'])) {
            $term_ids = [];
            foreach ($data['subjects_data'] as $assunto_nome) {
                // Verifica se o termo existe na taxonomia 'assunto'
                $term = term_exists($assunto_nome, 'assunto');
                
                if ($term !== 0 && $term !== null) {
                    $term_ids[] = (int) $term['term_id'];
                } else {
                    // Cria novo termo se não existir
                    $new_term = wp_insert_term($assunto_nome, 'assunto');
                    if (!is_wp_error($new_term)) {
                        $term_ids[] = (int) $new_term['term_id'];
                        // Opcional: Logar em "Novos Assuntos" (tabela customizada ou transient)
                        $this->log_new_subject($data['codigo_questao'], $assunto_nome);
                    }
                }
            }
            // Vincula os termos ao post
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'assunto');
            }
        }

        // Tags Extras
        if (!empty($data['extra_tags'])) {
            wp_set_post_tags($post_id, $data['extra_tags'], true);
        }

        // Campo auxiliar para identificar se tem imagem manualmente depois
        if (isset($data['com_imagem']) && $data['com_imagem'] == '1') {
            update_post_meta($post_id, 'precisa_imagem', '1');
        }

        return $post_id;
    }

    private function log_history($code, $type, $model, $has_image) {
        global $wpdb;
        $table = $wpdb->prefix . 'gva_history';
        $wpdb->insert($table, [
            'time' => current_time('mysql'),
            'question_code' => $code,
            'type' => $type,
            'ai_model' => $model,
            'has_image' => $has_image ? 1 : 0,
            'status' => 'success'
        ]);
    }

    private function log_new_subject($code, $subject) {
        // Implementação simples: salvar em option ou transient para exibir no dashboard
        // Em produção, criar uma tabela separada seria ideal
        $new_subjects = get_option('gva_new_subjects', []);
        $new_subjects[] = [
            'code' => $code,
            'subject' => $subject,
            'date' => current_time('mysql')
        ];
        update_option('gva_new_subjects', array_slice($new_subjects, -50)); // Manter apenas os últimos 50
    }

    private function get_system_instruction($type) {
        // Instrução baseada nos Prompts fornecidos
        $base = "Atue como um vestibulando Brasileiro de alto nível especializado em disciplinas do ensino médio. Seu objetivo é criar um JSON estruturado para importação em plataforma de questões. Siga rigorosamente o formato JSON.";
        
        $json_structure = '
        Estrutura JSON obrigatória:
        {
            "questions": [
                {
                    "tipo_questao": "Múltipla Escolha",
                    "codigo_questao": "Q[DISC][ANO][RANDOM]",
                    "enunciado": "HTML content",
                    "resolucao": "HTML content",
                    "com_texto_adicional": "0 ou 1",
                    "tipo_de_questao_radio": "' . ($type === 'oficial' ? 'oficial' : 'similar') . '",
                    "numero_oficial": "Inteiro",
                    "com_imagem": "0 ou 1",
                    "extra_tags": "Tag1, Tag2, Tag3",
                    "taxonomies": {
                        "discipline": "String",
                        "institution": "String",
                        "year": "String",
                        "difficulty": "Fácil/Médio/Difícil",
                        "version": "String",
                        "day": "String"
                    },
                    "alternativas_data": [
                        { "text": "HTML", "is_correct": "0 ou 1", "order": "0" }
                    ],
                    "subjects_data": ["Assunto1", "Assunto2"],
                    "imagePrompt": "String (Description in English if image needed, else null)"
                }
            ]
        }';

        if ($type === 'oficial') {
            $base .= " Extraia as informações do texto/PDF fornecido. Mantenha fidelidade total ao conteúdo original. Se houver fórmulas, use LaTeX entre $ ou $$. Se houver imagem na questão original, marque 'com_imagem': '1'. " . $json_structure;
        } else {
            $base .= " Crie uma questão INÉDITA (Similar) baseada nos parâmetros fornecidos. Seja criativo mas mantenha o rigor acadêmico. Se solicitado imagem, crie um prompt descritivo em inglês no campo 'imagePrompt'. " . $json_structure;
        }

        return $base;
    }

    private function build_user_prompt($type, $params) {
        $qtd = $params['quantidade'] ?? 1;
        $disc = $params['disciplina'] ?? 'Geral';
        
        if ($type === 'oficial') {
            // Para oficial, os dados vêm do PDF (contexto), aqui reforçamos os metadados
            return "Extraia $qtd questões do conteúdo fornecido. Metadados obrigatórios: Disciplina: $disc, Instituição: {$params['instituicao']}, Ano: {$params['ano']}, Versão: {$params['versao']}, Dia: {$params['dia']}. Se a questão tiver texto de apoio, extraia e preencha 'support_texts_data'.";
        } else {
            // Para similar, construímos o pedido de geração
            $details = "Assunto: " . ($params['assunto'] ?? 'Geral');
            if (!empty($params['estilo_questao'])) {
                $details .= ". Estilo/Foco: " . $params['estilo_questao'];
            }
            if (!empty($params['genero'])) {
                $details .= ". Gênero Textual: " . $params['genero'];
            }
            if (!empty($params['subgenero'])) {
                $details .= ". Subgênero: " . $params['subgenero'];
            }
            
            $img_req = ($params['com_imagem'] == '1') ? "A questão DEVE conter uma imagem (gere o prompt)." : "A questão NÃO deve ter imagem.";

            return "Gere $qtd questões inéditas de $disc. $details. $img_req. Nível: Médio/Difícil.";
        }
    }
}