<?php
namespace ProOneAI\Admin;

use ProOneAI\Services\AIService;
use ProOneAI\Services\ImageService;

class AjaxHandler {

    private $ai_service;
    private $image_service;

    public function run() {
        $this->ai_service = new AIService();
        $this->image_service = new ImageService(); 

        add_action('wp_ajax_p1ai_process_request', [$this, 'handle_request']);
        add_action('wp_ajax_p1ai_get_history', [$this, 'get_history']);
        add_action('wp_ajax_p1ai_get_new_subjects', [$this, 'get_new_subjects']);
        add_action('wp_ajax_p1ai_save_settings', [$this, 'save_settings']);
        
        // Ajax actions para preencher selects
        add_action('wp_ajax_p1ai_get_institutions', function() { $this->get_terms_simple('pqp_institutions'); });
        add_action('wp_ajax_p1ai_get_years', function() { $this->get_terms_simple('pqp_years'); });
        add_action('wp_ajax_p1ai_get_authors', function() { $this->get_terms_simple('pqp_authors'); }); 
        add_action('wp_ajax_p1ai_get_books', function() { $this->get_terms_simple('pqp_books', 'title'); }); 
        
        // CORREÇÃO: Adicionado o hook que faltava para carregar os Níveis de Ensino
        add_action('wp_ajax_p1ai_get_education_levels', function() { $this->get_terms_simple('pqp_education_levels'); });
        
        add_action('wp_ajax_p1ai_get_subjects', [$this, 'get_subjects_by_discipline']);
        add_action('wp_ajax_p1ai_get_versions_by_inst', [$this, 'get_versions_by_institution']);
        add_action('wp_ajax_p1ai_get_subgenres_by_genre', [$this, 'get_subgenres_by_genre']);
    }

    public function save_settings() {
        check_ajax_referer(P1AI_NONCE, 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Sem permissão.');

        if (isset($_POST['groq_key'])) update_option('p1ai_groq_api_key', sanitize_text_field($_POST['groq_key']));
        if (isset($_POST['hf_key'])) update_option('p1ai_huggingface_api_key', sanitize_text_field($_POST['hf_key']));

        wp_send_json_success('Configurações salvas com sucesso!');
    }

    // --- Métodos Auxiliares de BD ---
    private function get_terms_simple($table_suffix, $col = 'name') {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        // Verifica se a tabela existe antes de consultar para evitar erros fatais
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_success([]); // Retorna vazio se a tabela não existir
            return;
        }
        $results = $wpdb->get_results("SELECT $col as name FROM $table ORDER BY $col ASC LIMIT 500");
        wp_send_json_success($results);
    }

    public function get_versions_by_institution() {
        global $wpdb;
        $inst_name = sanitize_text_field($_GET['institution']);
        $sql = $wpdb->prepare("SELECT v.name FROM {$wpdb->prefix}pqp_versions v JOIN {$wpdb->prefix}pqp_institutions i ON v.institution_id = i.id WHERE i.name = %s ORDER BY v.name ASC", $inst_name);
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }
    public function get_subjects_by_discipline() {
        global $wpdb;
        $disc_name = sanitize_text_field($_GET['discipline']);
        $sql = $wpdb->prepare("SELECT s.name FROM {$wpdb->prefix}pqp_subjects s JOIN {$wpdb->prefix}pqp_disciplines d ON s.discipline_id = d.id WHERE d.name = %s ORDER BY s.name ASC", $disc_name);
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }
    public function get_subgenres_by_genre() {
        global $wpdb;
        $genre_name = sanitize_text_field($_GET['genre']);
        $sql = $wpdb->prepare("SELECT s.name FROM {$wpdb->prefix}pqp_text_subgenres s JOIN {$wpdb->prefix}pqp_text_genres g ON s.genre_id = g.id WHERE g.name = %s ORDER BY s.name ASC", $genre_name);
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }

    public function handle_request() {
        check_ajax_referer(P1AI_NONCE, 'nonce');
        set_time_limit(300);

        $type = sanitize_text_field($_POST['type']);
        $params = $_POST['params'];
        $text_model = sanitize_text_field($params['ai_model']);
        $temperature = isset($params['temperature']) ? floatval($params['temperature']) : 0.2;

        if (isset($params['com_texto']) && $params['com_texto'] == '0') {
            $params['titulo_texto'] = ''; $params['autor'] = ''; $params['livro'] = ''; $params['genero'] = '';
            $params['subgenero'] = ''; $params['nacionalidade'] = ''; $params['forma_texto'] = '';
            $params['periodo'] = ''; $params['tipologia'] = ''; $params['tipo_texto'] = ''; $params['book_url'] = ''; 
        }

        $system_instruction = $this->get_system_instruction($type, $params);
        $user_prompt = $this->build_user_prompt($type, $params);

        $response = $this->ai_service->generate_content($text_model, $user_prompt, $system_instruction, $temperature);

        if (is_wp_error($response)) wp_send_json_error($response->get_error_message());
        
        $json_data = $response;
        if (!isset($json_data['questions']) || empty($json_data['questions'])) {
            wp_send_json_error('A IA não retornou JSON válido ou questões não foram encontradas.');
        }

        $results = [];
        foreach ($json_data['questions'] as $q_data) {
            $img_status = 'Não solicitada';
            $generated_image_url = null;
            $com_imagem = ($params['com_imagem'] == '1');
            
            // Geração de Imagem
            if ($com_imagem && !empty($q_data['imagePrompt'])) {
                $image_model = isset($params['image_model']) ? sanitize_text_field($params['image_model']) : '';
                if (!empty($image_model)) {
                    $image_model = str_replace('hf_img_', '', $image_model);
                    
                    // Chama a API da HF e recebe a URL de um arquivo TEMP local
                    $generated_image_url = $this->ai_service->generate_huggingface_image($image_model, $q_data['imagePrompt']);
                    
                    if ($generated_image_url) {
                        $img_status = 'Gerada com Sucesso';
                    } else {
                        $img_status = 'Erro na Geração da Imagem';
                        $generated_image_url = null;
                    }
                }
            }

            // Insere a questão e processa a imagem
            $inserted = $this->insert_question($q_data, $type, $params, $generated_image_url);
            
            if ($inserted) {
                // Atualiza status se houve falha no processamento interno da imagem
                if ($com_imagem && $img_status == 'Gerada com Sucesso' && !$inserted['has_image_attached']) {
                    $img_status = 'Erro ao Anexar Imagem';
                }

                $this->log_history($inserted['code'], $type, $text_model, $inserted['has_image_attached']);
                $results[] = ['code' => $inserted['code'], 'id' => $inserted['id'], 'img_status' => $img_status];
            }
        }

        wp_send_json_success($results);
    }

    private function insert_question($data, $type, $params, $image_temp_url = null) {
        global $wpdb;

        $disc_name = $params['disciplina'];
        $abbr = $wpdb->get_var($wpdb->prepare("SELECT abbreviation FROM {$wpdb->prefix}pqp_disciplines WHERE name = %s", $disc_name));
        $disc_abbr = $abbr ? $abbr : strtoupper(substr($disc_name, 0, 3)); 
        
        $data['codigo_questao'] = 'Q' . $disc_abbr . mt_rand(10000000000, 99999999999);

        if (isset($params['com_texto']) && $params['com_texto'] == '0') {
             $data['support_texts_data'] = [];
        }

        // Monta o enunciado texto base
        $extra_content = '';
        if (!empty($data['support_texts_data'])) {
            foreach ($data['support_texts_data'] as $txt) {
                 if (!empty($txt['content']) && strpos($data['enunciado'], substr($txt['content'], 0, 20)) === false) {
                     $extra_content .= '<div class="support-text-content">' . $txt['content'] . '</div><br>';
                 }
            }
        }
        $final_enunciado = $extra_content . $data['enunciado'];

        // Insere o Post primeiro
        $post_id = wp_insert_post([
            'post_title' => $data['codigo_questao'],
            'post_content' => $final_enunciado,
            'post_status' => 'publish',
            'post_type' => 'questoes'
        ]);
        if (is_wp_error($post_id)) return false;

        // --- PROCESSAMENTO DA IMAGEM ---
        $has_image_attached = 0;
        
        if ($image_temp_url) {
            // Processa usando ImageService atualizado
            $attach_id = $this->image_service->process_image($image_temp_url, $post_id, 'imagem-' . $data['codigo_questao']);
            
            if ($attach_id && !is_wp_error($attach_id)) {
                $local_img_url = wp_get_attachment_url($attach_id);
                
                // VERIFICAÇÃO DE SEGURANÇA: Só injeta se a URL for válida
                if ($local_img_url) {
                    $has_image_attached = 1;

                    // Cria HTML da imagem
                    $image_html = '
                    <div class="questao-imagem-container" style="text-align: center; margin: 20px 0;">
                        <img src="' . esc_url($local_img_url) . '" alt="Imagem da Questão ' . esc_attr($data['codigo_questao']) . '" class="aligncenter size-full wp-image-' . $attach_id . '" style="max-width: 100%; height: auto; border-radius: 8px;">
                    </div>';
                    
                    // Injeta no início
                    $final_enunciado = $image_html . $final_enunciado;
                    
                    // Atualiza o post
                    wp_update_post(['ID' => $post_id, 'post_content' => $final_enunciado]);
                } else {
                    error_log('P1AI: Anexo criado (ID '.$attach_id.'), mas wp_get_attachment_url retornou vazio.');
                }
                
                // Limpa temp
                $upload_dir = wp_upload_dir();
                $temp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_temp_url);
                if (file_exists($temp_path)) {
                    @unlink($temp_path);
                }
            }
        }

        $tipo_questao_db = ($type === 'oficial') ? 'Questão Oficial' : 'Questão Similar';
        $tipo_de_questao_radio = $params['tipo_questao_radio'] ?? 'similar';
        $avaliacao = $params['avaliacao'] ?? 'experimental';

        update_post_meta($post_id, 'resolucao', $data['resolucao']);
        update_post_meta($post_id, 'tipo_questao', $tipo_questao_db);
        update_post_meta($post_id, 'avaliacao', $avaliacao); 
        update_post_meta($post_id, 'com_imagem', $has_image_attached);
        
        // --- INSERÇÃO PQP (Tabela Customizada) ---
        $disc_id = $this->get_or_create_normalized_id('pqp_disciplines', $params['disciplina']);
        $inst_id = $this->get_or_create_normalized_id('pqp_institutions', $params['instituicao']);
        $year_id = $this->get_or_create_normalized_id('pqp_years', $params['ano']);
        $ver_id = $this->get_or_create_normalized_id('pqp_versions', $params['versao'], 'institution_id', $inst_id);
        
        $edu_name = $params['nivel_ensino'] ?? null;
        $edu_id = $this->get_or_create_normalized_id('pqp_education_levels', $edu_name);
        if ($edu_id == 0) $edu_id = 1; 

        $diff_name = $data['nivel_dificuldade'] ?? $params['nivel_dificuldade'] ?? 'Médio';
        $diff_id = $this->get_or_create_normalized_id('pqp_difficulty_levels', $diff_name);
        
        $extra_tags_str = '';
        if (!empty($data['extra_tags'])) {
            $extra_tags_str = is_array($data['extra_tags']) ? implode(', ', $data['extra_tags']) : $data['extra_tags'];
        }

        if(!defined('PQP_TABLE_NAME')) define('PQP_TABLE_NAME', $wpdb->prefix . 'pqp_questions');
        
        // Insere na tabela customizada do PQP
        $wpdb->insert(PQP_TABLE_NAME, [
            'codigo_questao' => $data['codigo_questao'],
            'education_level_id' => $edu_id, 
            'enunciado' => $final_enunciado, // Salva o enunciado final com imagem
            'resolucao' => $data['resolucao'],
            'discipline_id' => $disc_id,
            'institution_id' => $inst_id,
            'year_id' => $year_id,
            'version_id' => $ver_id,
            'difficulty_level_id' => $diff_id,
            'tipo_questao' => $tipo_questao_db,
            'tipo_de_questao_radio' => $tipo_de_questao_radio,
            'avaliacao' => $avaliacao,
            'com_imagem' => $has_image_attached,
            'com_texto_adicional' => !empty($data['support_texts_data']) ? 1 : 0,
            'published' => 1,
            'visibilidade' => 'visivel',
            'question_author' => get_current_user_id(),
            'data_criacao' => current_time('mysql'),
            'extra_tags' => $extra_tags_str
        ]);
        $db_question_id = $wpdb->insert_id;

        // Insere alternativas
        if(!defined('PQP_ALTERNATIVES_TABLE_NAME')) define('PQP_ALTERNATIVES_TABLE_NAME', $wpdb->prefix . 'pqp_alternatives');
        foreach ($data['alternativas_data'] as $idx => $alt) {
            $wpdb->insert(PQP_ALTERNATIVES_TABLE_NAME, [
                'question_id' => $db_question_id,
                'text' => $alt['text'],
                'is_correct' => $alt['is_correct'],
                'order' => $idx
            ]);
        }

        // Processa textos de apoio
        if (!empty($data['support_texts_data'])) {
            $this->process_support_texts($db_question_id, $data['support_texts_data']);
            update_post_meta($post_id, 'textos_apoio', $data['support_texts_data']);
        }
        
        // Processa assuntos
        $subjects_to_process = !empty($data['subjects_data']) ? $data['subjects_data'] : [];
        if (empty($subjects_to_process) && !empty($params['assunto'])) {
            $subjects_to_process = [$params['assunto']];
        }

        if (!empty($subjects_to_process)) {
             $this->process_subjects($post_id, $db_question_id, $subjects_to_process, $params['disciplina'], $data['codigo_questao']);
        }
        
        if (!empty($data['extra_tags'])) {
             $this->process_subjects($post_id, $db_question_id, $data['extra_tags'], $params['disciplina'], $data['codigo_questao']);
        }

        return ['id' => $post_id, 'code' => $data['codigo_questao'], 'has_image_attached' => $has_image_attached];
    }

    private function process_support_texts($db_q_id, $texts_data) {
        global $wpdb;
        if(!defined('PQP_SUPPORT_TEXTS_TABLE_NAME')) define('PQP_SUPPORT_TEXTS_TABLE_NAME', $wpdb->prefix . 'pqp_support_texts');

        foreach ($texts_data as $idx => $txt) {
            $typ_id = $this->get_or_create_normalized_id('pqp_text_typologies', $txt['typology_name'] ?? null);
            $gen_id = $this->get_or_create_normalized_id('pqp_text_genres', $txt['genre_name'] ?? null);
            $sub_id = $this->get_or_create_normalized_id('pqp_text_subgenres', $txt['subgenre_name'] ?? null, 'genre_id', $gen_id);
            $per_id = $this->get_or_create_normalized_id('pqp_literary_periods', $txt['literary_period_name'] ?? null);

            $wpdb->insert(PQP_SUPPORT_TEXTS_TABLE_NAME, [
                'question_id' => $db_q_id,
                'content' => '', 
                'typology_id' => $typ_id,
                'genre_id' => $gen_id,
                'subgenre_id' => $sub_id,
                'literary_period_id' => $per_id,
                'ordem' => $idx
            ]);
            $text_id = $wpdb->insert_id;

            $auth_id = $this->get_or_create_normalized_id('pqp_authors', $txt['author_name'] ?? 'Desconhecido');
            $this->process_text_meta($text_id, 'pqp_authors', 'pqp_support_text_authors', 'author_id', $txt['author_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_books', 'pqp_support_text_books', 'book_id', $txt['book_title'] ?? null, 'author_id', $auth_id);
            $this->process_text_meta($text_id, 'pqp_nationalities', 'pqp_support_text_nationalities', 'nationality_id', $txt['nationality_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_types', 'pqp_support_text_text_types', 'text_type_id', $txt['text_type_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_forms', 'pqp_support_text_forms', 'text_form_id', $txt['text_form_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_titles', 'pqp_support_text_titles', 'text_title_id', $txt['text_title_name'] ?? null);
        }
    }

    private function get_or_create_normalized_id($table_suffix, $name, $extra_col = null, $extra_val = null) {
        if (empty($name) || $name === 'null' || strtolower($name) === 'desconhecido') {
             if($table_suffix !== 'pqp_authors') return 0;
        }
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
        if (!$id && !empty($name)) {
            $data = ['name' => $name];
            if ($extra_col && $extra_val) $data[$extra_col] = $extra_val;
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        return $id ?: 0;
    }

    private function process_text_meta($support_text_id, $main_table, $link_table, $fk_column, $value, $parent_col = null, $parent_val = null) {
        if (empty($value) || $value === 'null') return;
        global $wpdb;
        $link_tbl_full = $wpdb->prefix . $link_table;
        $term_id = $this->get_or_create_normalized_id($main_table, $value, $parent_col, $parent_val);
        if($term_id) {
            $wpdb->insert($link_tbl_full, ['support_text_id' => $support_text_id, $fk_column => $term_id]);
        }
    }

    private function process_subjects($post_id, $db_question_id, $subjects, $discipline_name, $q_code) {
        global $wpdb;
        
        $disc_id = $this->get_or_create_normalized_id('pqp_disciplines', $discipline_name);
        
        $table_subjects = $wpdb->prefix . 'pqp_subjects';
        if(!defined('PQP_QUESTION_SUBJECTS_TABLE_NAME')) define('PQP_QUESTION_SUBJECTS_TABLE_NAME', $wpdb->prefix . 'pqp_question_subjects');
        $table_q_subjects = PQP_QUESTION_SUBJECTS_TABLE_NAME;

        foreach ($subjects as $sub_name) {
            $sub_name = sanitize_text_field(trim($sub_name));
            if(empty($sub_name)) continue;

            $sub_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_subjects WHERE name = %s AND discipline_id = %d", 
                $sub_name, $disc_id
            ));

            if (!$sub_id) {
                $wpdb->insert($table_subjects, [
                    'name' => $sub_name, 
                    'discipline_id' => $disc_id
                ]);
                $sub_id = $wpdb->insert_id;
                $this->log_new_subject($q_code, $sub_name);
            }

            $link_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_q_subjects WHERE question_id = %d AND subject_id = %d",
                $db_question_id, $sub_id
            ));

            if (!$link_exists) {
                $wpdb->insert($table_q_subjects, [
                    'question_id' => $db_question_id,
                    'subject_id' => $sub_id
                ]);
            }

            $term = term_exists($sub_name, 'assunto');
            if (!$term) $term = wp_insert_term($sub_name, 'assunto');
            if (!is_wp_error($term)) wp_set_object_terms($post_id, (int)$term['term_id'], 'assunto', true);
        }
    }

    private function log_new_subject($code, $subject) {
        $log = get_option('p1ai_new_subjects_log', []);
        array_unshift($log, ['code' => $code, 'subject' => $subject, 'date' => current_time('mysql')]);
        update_option('p1ai_new_subjects_log', array_slice($log, 0, 100));
    }
    
    private function log_history($code, $type, $model, $has_image) {
        global $wpdb;
        $table = $wpdb->prefix . 'p1ai_history';
        $wpdb->insert($table, [
            'time' => current_time('mysql'),
            'question_code' => $code,
            'type' => $type,
            'ai_model' => $model,
            'has_image' => $has_image,
            'status' => 'success'
        ]);
    }

    public function get_history() {
        global $wpdb;
        $table = $wpdb->prefix . 'p1ai_history';
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC LIMIT 50");
        wp_send_json_success($results);
    }

    public function get_new_subjects() {
        wp_send_json_success(get_option('p1ai_new_subjects_log', []));
    }

    private function get_system_instruction($type, $params) {
        $schema = [
            "questions" => [[
                "enunciado" => "HTML completo do enunciado",
                "resolucao" => "Explicação detalhada (PT-BR) SEM bullet points, SEM emojis.",
                "imagePrompt" => "Descrição visual em Inglês para modelo generativo (se imagem solicitada)",
                "nivel_dificuldade" => "Fácil, Médio ou Difícil",
                "subjects_data" => ["Assunto Principal"],
                "extra_tags" => ["Tag1", "Tag2", "Tag3", "Tag4", "Tag5"], 
                "alternativas_data" => [
                    ["text" => "Texto da alternativa (SEM A, B, C...)", "is_correct" => "1", "order" => 0],
                    ["text" => "Texto da alternativa (SEM A, B, C...)", "is_correct" => "0", "order" => 1],
                    ["text" => "Texto da alternativa (SEM A, B, C...)", "is_correct" => "0", "order" => 2],
                    ["text" => "Texto da alternativa (SEM A, B, C...)", "is_correct" => "0", "order" => 3],
                    ["text" => "Texto da alternativa (SEM A, B, C...)", "is_correct" => "0", "order" => 4]
                ]
            ]]
        ];

        if (isset($params['com_texto']) && $params['com_texto'] == '1') {
            $schema['questions'][0]['support_texts_data'] = [[
                "content" => "Texto base completo",
                "text_title_name" => "Título",
                "author_name" => "Nome",
                "book_title" => "Livro",
                "nationality_name" => "País",
                "text_type_name" => "Literário/Não Literário",
                "text_form_name" => "Prosa/Verso",
                "typology_name" => "Narrativo/Dissertativo",
                "genre_name" => "Gênero",
                "subgenre_name" => "Subgênero",
                "literary_period_name" => "Período"
            ]];
        }

        $json_str = json_encode($schema);

        return "Você é um Especialista em Vestibulares. Retorne APENAS JSON válido seguindo estritamente este schema: $json_str. " .
               "REGRAS CRÍTICAS: " .
               "1. Use KaTeX para fórmulas matemáticas. " .
               "2. Gere EXATAMENTE 5 alternativas. O campo 'text' da alternativa deve conter SOMENTE o conteúdo da resposta. NÃO inclua letras como (A), (B), [A] ou a). " .
               "3. Resolução OBRIGATÓRIA: Deve ser um texto corrido explicativo. PROIBIDO usar emojis ou bullet points na resolução. Use <b>negrito</b> para destaques. " .
               "4. Gere entre 5 a 10 'extra_tags' relacionadas ao tema. " .
               "5. Se solicitado imagem, crie um prompt em inglês no campo imagePrompt.";
    }

    private function build_user_prompt($type, $params) {
        if ($type === 'oficial') {
            return "Extrair do PDF. Disciplina: {$params['disciplina']}. Instruções: {$params['instrucoes']}.";
        } else {
            $req = "Gerar {$params['quantidade']} questões INÉDITAS. Disciplina: {$params['disciplina']}. Assunto: {$params['assunto']}. " .
                   "Instituição/Estilo: {$params['instituicao']}. Dicas de Estilo: " . ($params['estilo_questao'] ?? '') . ". ";
            
            if(isset($params['com_texto']) && $params['com_texto'] == '1') {
                $req .= "Baseado em texto com estas características: Tipo {$params['tipo_texto']}, Gênero {$params['genero']}, Subgênero {$params['subgenero']}, " .
                        "Autor {$params['autor']}, Livro {$params['livro']}, Nacionalidade {$params['nacionalidade']}, " .
                        "Forma {$params['forma_texto']}, Período {$params['periodo']}, Tipologia {$params['tipologia']}. ";
            } else {
                $req .= "Crie um enunciado direto, SEM TEXTO DE APOIO ADICIONAL, SEM AUTOR, SEM LIVRO. Apenas a pergunta.";
            }

            $req .= ($params['com_imagem'] == '1') ? " COM IMAGEM: Crie um prompt visual criativo para ilustrar a questão." : " SEM IMAGEM.";
            return $req;
        }
    }
}