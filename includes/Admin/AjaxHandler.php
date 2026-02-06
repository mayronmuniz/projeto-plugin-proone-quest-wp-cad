<?php
namespace GeminiVestibularAI\Admin;

use GeminiVestibularAI\Services\GeminiService;
use GeminiVestibularAI\Services\ImageService;

class AjaxHandler {

    private $gemini;
    private $image_service;

    public function run() {
        $this->gemini = new GeminiService();
        // $this->image_service = new ImageService(); // Descomentar quando implementar serviço de imagem

        add_action('wp_ajax_gva_process_request', [$this, 'handle_request']);
        add_action('wp_ajax_gva_get_history', [$this, 'get_history']);
        add_action('wp_ajax_gva_get_new_subjects', [$this, 'get_new_subjects']);
        
        // Endpoints para popular dropdowns
        add_action('wp_ajax_gva_get_institutions', function() { $this->get_terms('pqp_institutions'); });
        add_action('wp_ajax_gva_get_years', function() { $this->get_terms('pqp_years'); });
        add_action('wp_ajax_gva_get_versions', function() { $this->get_terms_rel('pqp_versions'); });
        // Adicione outros conforme necessário
    }

    private function get_terms($table_suffix) {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        $results = $wpdb->get_results("SELECT name FROM $table ORDER BY name");
        wp_send_json_success($results);
    }
    
    // Versões dependem de instituicao, mas simplificado aqui
    private function get_terms_rel($table_suffix) {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        $results = $wpdb->get_results("SELECT name FROM $table GROUP BY name ORDER BY name");
        wp_send_json_success($results);
    }

    public function handle_request() {
        check_ajax_referer(GVA_NONCE, 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Sem permissão.');

        $type = sanitize_text_field($_POST['type']); // 'oficial' ou 'similar'
        $params = $_POST['params'];

        // 1. Prompt Gigante com Regras Estritas
        $system_instruction = $this->get_complex_system_instruction($type);
        $user_prompt = $this->build_user_prompt($type, $params);

        // 2. Chamada IA
        $pdf_url = !empty($params['pdf_url']) ? esc_url_raw($params['pdf_url']) : null;
        $book_url = !empty($params['book_url']) ? esc_url_raw($params['book_url']) : null;
        $file_url = ($type === 'oficial') ? $pdf_url : $book_url;
        $model = sanitize_text_field($params['ai_model'] ?? 'gemini-2.0-flash');

        $response = $this->gemini->generate_content($model, $user_prompt, $system_instruction, $file_url);

        if (is_wp_error($response)) wp_send_json_error($response->get_error_message());
        if (!isset($response['questions']) || empty($response['questions'])) wp_send_json_error('IA não retornou questões válidas.');

        // 3. Processamento e Inserção
        $results = [];
        foreach ($response['questions'] as $q_data) {
            $post_id = $this->insert_question($q_data, $type, $params);
            if ($post_id) {
                $has_img = (isset($q_data['com_imagem']) && $q_data['com_imagem'] == '1');
                $this->log_history($q_data['codigo_questao'], $type, $model, $has_img);
                
                // Se for similar e tiver imagem, lógica de geração de imagem (placeholder)
                if ($type === 'similar' && $has_img && !empty($q_data['imagePrompt'])) {
                   // $this->generate_and_upload_image($q_data['imagePrompt'], $post_id);
                }

                $results[] = ['code' => $q_data['codigo_questao'], 'id' => $post_id];
            }
        }

        wp_send_json_success($results);
    }

    private function insert_question($data, $type, $params) {
        global $wpdb;

        // Gerar Código Único se a IA falhou ou para garantir
        $disc_abbr = strtoupper(substr($params['disciplina'], 0, 3)); // Ex: PRT
        // Se a IA não mandou código correto, geramos
        if (empty($data['codigo_questao'])) {
            $data['codigo_questao'] = 'Q' . $disc_abbr . rand(10000000000, 99999999999);
        }

        // Post Principal
        $post_id = wp_insert_post([
            'post_title' => $data['codigo_questao'],
            'post_content' => $data['enunciado'],
            'post_status' => 'publish',
            'post_type' => 'questoes'
        ]);
        if (is_wp_error($post_id)) return false;

        // Metadados Básicos
        update_post_meta($post_id, 'resolucao', $data['resolucao']);
        update_post_meta($post_id, 'tipo_questao', $type === 'oficial' ? 'Questão Oficial' : 'Questão Similar');
        update_post_meta($post_id, 'avaliacao', 'experimental');
        update_post_meta($post_id, 'condicao', ''); // Nenhuma
        update_post_meta($post_id, 'com_imagem', $data['com_imagem'] ?? '0');
        
        // Dados Oficiais
        if ($type === 'oficial') {
            update_post_meta($post_id, 'numero_oficial', $data['numero_oficial'] ?? 0);
            update_post_meta($post_id, 'tipo_de_questao_radio', 'oficial');
        } else {
            update_post_meta($post_id, 'tipo_de_questao_radio', 'similar');
        }

        // Taxonomias (Assuntos) - Lógica de Novos Assuntos
        if (!empty($data['subjects_data'])) {
            $term_ids = [];
            foreach ($data['subjects_data'] as $subject) {
                $term = term_exists($subject, 'assunto');
                if (!$term) {
                    $t = wp_insert_term($subject, 'assunto');
                    if (!is_wp_error($t)) {
                        $term_ids[] = $t['term_id'];
                        $this->log_new_subject($data['codigo_questao'], $subject);
                    }
                } else {
                    $term_ids[] = $term['term_id'];
                }
            }
            wp_set_object_terms($post_id, $term_ids, 'assunto');
        }

        // Tags Extras
        if (!empty($data['extra_tags'])) {
            wp_set_post_tags($post_id, $data['extra_tags'], true);
        }

        // Inserção Normalizada nas Tabelas Personalizadas do Plugin Principal
        // 1. Questão na tabela `pqp_table_name`
        // Precisamos dos IDs de disciplina, instituição, etc.
        $disc_id = $this->get_or_create_normalized_id('pqp_disciplines', $params['disciplina']);
        $inst_id = $this->get_or_create_normalized_id('pqp_institutions', $params['instituicao']);
        $year_id = $this->get_or_create_normalized_id('pqp_years', $params['ano']);
        $ver_id = $this->get_or_create_normalized_id('pqp_versions', $params['versao'], ['institution_id' => $inst_id]);
        $day_id = isset($params['dia']) ? $this->get_or_create_normalized_id('pqp_days', $params['dia']) : null;
        $diff_id = $this->get_or_create_normalized_id('pqp_difficulty_levels', $params['nivel_dificuldade'], ['order' => 0]);

        $wpdb->insert(PQP_TABLE_NAME, [
            'codigo_questao' => $data['codigo_questao'],
            'enunciado' => $data['enunciado'],
            'resolucao' => $data['resolucao'],
            'discipline_id' => $disc_id,
            'institution_id' => $inst_id,
            'year_id' => $year_id,
            'version_id' => $ver_id,
            'day_id' => $day_id,
            'difficulty_level_id' => $diff_id,
            'tipo_questao' => $type === 'oficial' ? 'Questão Oficial' : 'Questão Similar',
            'com_imagem' => $data['com_imagem'],
            'question_author' => get_current_user_id(),
            'education_level_id' => 1, // Default Nível Médio
            'com_texto_adicional' => !empty($data['support_texts_data']) ? 1 : 0
        ]);
        $db_question_id = $wpdb->insert_id;

        // 2. Alternativas
        foreach ($data['alternativas_data'] as $idx => $alt) {
            $wpdb->insert($wpdb->prefix . 'pqp_alternatives', [
                'question_id' => $db_question_id,
                'text' => $alt['text'],
                'is_correct' => $alt['is_correct'],
                'order' => $idx
            ]);
        }

        // 3. Textos de Apoio (Complexo)
        if (!empty($data['support_texts_data'])) {
            foreach ($data['support_texts_data'] as $idx => $txt) {
                // Insere o texto base
                $wpdb->insert(PQP_SUPPORT_TEXTS_TABLE_NAME, [
                    'question_id' => $db_question_id,
                    'content' => $txt['content'] ?? '', // Pode ser vazio se for só referência
                    'ordem' => $idx
                ]);
                $text_id = $wpdb->insert_id;

                // Processa e linka os metadados do texto (Author, Book, Genre, etc.)
                $this->process_text_meta($text_id, 'pqp_authors', 'pqp_support_text_authors', 'author_id', $txt['author_name']);
                $this->process_text_meta($text_id, 'pqp_books', 'pqp_support_text_books', 'book_id', $txt['book_title'], $txt['author_name']); // Livro precisa de autor
                $this->process_text_meta($text_id, 'pqp_nationalities', 'pqp_support_text_nationalities', 'nationality_id', $txt['nationality_name']);
                $this->process_text_meta($text_id, 'pqp_text_genres', 'pqp_support_text_genres', 'genre_id', $txt['genre_name']);
                $this->process_text_meta($text_id, 'pqp_text_subgenres', 'pqp_support_text_subgenres', 'subgenre_id', $txt['subgenre_name'], null, $txt['genre_name']); // Subgenero precisa de genero
                $this->process_text_meta($text_id, 'pqp_text_types', 'pqp_support_text_text_types', 'text_type_id', $txt['text_type_name']);
                $this->process_text_meta($text_id, 'pqp_text_forms', 'pqp_support_text_forms', 'text_form_id', $txt['text_form_name']);
                $this->process_text_meta($text_id, 'pqp_text_typologies', 'pqp_support_text_typologies', 'typology_id', $txt['typology_name']);
                $this->process_text_meta($text_id, 'pqp_literary_periods', 'pqp_support_text_literary_periods', 'literary_period_id', $txt['literary_period_name']);
                $this->process_text_meta($text_id, 'pqp_text_titles', 'pqp_support_text_titles', 'text_title_id', $txt['text_title_name']);
            }
            
            // Salva JSON no post meta para compatibilidade com sistema legado se houver
            update_post_meta($post_id, 'textos_apoio', $data['support_texts_data']);
        }
        
        // Salva alternativas no post meta também
        update_post_meta($post_id, 'alternativas', $data['alternativas_data']);

        return $post_id;
    }

    // Helper para normalizar dados (Busca ID ou Cria)
    private function get_or_create_normalized_id($table_suffix, $name, $extra_cols = []) {
        if (empty($name)) return 0;
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
        
        if (!$id) {
            $insert_data = ['name' => $name];
            if (!empty($extra_cols)) $insert_data = array_merge($insert_data, $extra_cols);
            
            // Tratamento especial para tabelas que exigem chave estrangeira na criação (Ex: Version precisa de Institution)
            // Para simplificar, assumimos que o banco aceita 0 ou NULL temporariamente ou inserimos defaults
            // No caso de pqp_versions, exige institution_id.
            
            $wpdb->insert($table, $insert_data);
            $id = $wpdb->insert_id;
        }
        return $id;
    }

    // Helper específico para linkar Text Details (N:N)
    private function process_text_meta($support_text_id, $main_table, $link_table, $fk_column, $value, $parent_val = null, $grandparent_val = null) {
        if (empty($value) || $value === 'Desconhecido') return;
        
        global $wpdb;
        $main_tbl_full = $wpdb->prefix . $main_table;
        $link_tbl_full = $wpdb->prefix . $link_table;

        // 1. Obter ID do termo principal (ex: Autor 'Machado')
        // Se precisar de parent (ex: Livro precisa de Autor), logica complexa omitida para brevidade,
        // mas idealmente buscaria o author_id antes. Aqui simplificamos buscando apenas pelo nome.
        
        $term_id = $this->get_or_create_normalized_id($main_table, $value);

        // 2. Inserir na tabela de ligação
        $wpdb->insert($link_tbl_full, [
            'support_text_id' => $support_text_id,
            $fk_column => $term_id
        ]);
    }

    private function get_complex_system_instruction($type) {
        // Base JSON Structure
        $json_structure = <<<'EOD'
        {
            "questions": [
                {
                    "codigo_questao": "Q[DISC][RANDOM]",
                    "enunciado": "HTML",
                    "resolucao": "HTML (No emojis, no bullet points, detailed in PT-BR, KaTeX formulas between $)",
                    "numero_oficial": 1,
                    "com_imagem": "0 or 1",
                    "com_texto_adicional": "0 or 1",
                    "subjects_data": ["Assunto 1", "Assunto 2"],
                    "extra_tags": "Tag1, Tag2, ... (5 to 10 tags)",
                    "alternativas_data": [
                        { "text": "HTML", "is_correct": "1 or 0", "order": 0 }
                    ],
                    "support_texts_data": [
                        {
                            "content": "HTML or Empty if just reference",
                            "author_name": "Name",
                            "book_title": "Title",
                            "nationality_name": "Country",
                            "text_type_name": "Literário/Não Literário/Híbrido",
                            "text_form_name": "Prosa/Verso/Híbrido",
                            "typology_name": "Narrativo/...",
                            "genre_name": "Gênero",
                            "subgenre_name": "Subgênero",
                            "literary_period_name": "Period",
                            "text_title_name": "Title of text"
                        }
                    ],
                    "imagePrompt": "Description in English if image needed"
                }
            ]
        }
EOD;

        $rules = "Atue como um Especialista em Vestibulares Brasileiros. ";
        $rules .= "Regras de Ouro: ";
        $rules .= "1. Formato JSON estrito. ";
        $rules .= "2. Resolução deve ser profunda, técnica, sem emojis, sem listas com bullets, em PT-BR. Use LaTeX ($...$) para fórmulas. ";
        $rules .= "3. Para 'support_texts_data': Analise profundamente. Se for Híbrido/Literário, preencha Período, Gênero, Subgênero. Se for 'texto sobre texto' (ex: resenha de livro), crie dois objetos em 'support_texts_data': um para o texto principal e outro para a obra referenciada (preenchendo Autor/Livro). ";
        $rules .= "4. Autor nunca deve ser 'Desconhecido' se houver pistas. ";
        $rules .= "5. Gere de 5 a 10 'extra_tags'. ";
        
        if ($type === 'oficial') {
            $rules .= "CONTEXTO: Você receberá um PDF. Leia as 'instruções' do usuário para saber quais questões extrair e qual o gabarito. Extraia fielmente o enunciado e alternativas. Gere a resolução baseada no gabarito informado.";
        } else {
            $rules .= "CONTEXTO: Gere questões INÉDITAS baseadas nos parâmetros. Se houver PDF de livro, use-o como base para o texto de apoio.";
        }

        return $rules . " Estrutura JSON esperada: " . $json_structure;
    }

    private function build_user_prompt($type, $params) {
        if ($type === 'oficial') {
            return "Instruções do Usuário: " . $params['instrucoes'] . ". Metadados: Disciplina {$params['disciplina']}, Instituição {$params['instituicao']}, Ano {$params['ano']}.";
        } else {
            $qtd = $params['quantidade'];
            $req = "Gere $qtd questões inéditas. Disciplina: {$params['disciplina']}. Assunto: {$params['assunto']}. Nível: {$params['nivel_dificuldade']}. ";
            if(!empty($params['tipo_texto'])) $req .= "Tipo Texto: {$params['tipo_texto']}. ";
            if(!empty($params['autor'])) $req .= "Autor base: {$params['autor']}. ";
            
            $req .= ($params['com_imagem'] == '1') ? "A questão DEVE exigir imagem (gere prompt)." : "Sem imagem.";
            return $req;
        }
    }

    // Métodos auxiliares de Log e Histórico
    private function log_history($code, $type, $model, $has_image) {
        global $wpdb;
        $table = $wpdb->prefix . 'gva_history'; // Certifique-se que esta tabela existe ou crie no Activator
        // Fallback simples se tabela não existir (apenas para exemplo)
        // $wpdb->insert($table, [...]);
    }
    
    private function log_new_subject($code, $subject) {
        // Implementar logica de salvar em option ou tabela dedicada
        $list = get_option('gva_new_subjects_log', []);
        array_unshift($list, ['code' => $code, 'subject' => $subject, 'date' => current_time('mysql')]);
        update_option('gva_new_subjects_log', array_slice($list, 0, 100));
    }
    
    public function get_history() {
        // Mock ou Query real
        wp_send_json_success([]); 
    }
    
    public function get_new_subjects() {
        wp_send_json_success(get_option('gva_new_subjects_log', []));
    }
}