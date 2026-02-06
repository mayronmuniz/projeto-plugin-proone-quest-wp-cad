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

        // Gerações e Histórico
        add_action('wp_ajax_gva_process_request', [$this, 'handle_request']);
        add_action('wp_ajax_gva_get_history', [$this, 'get_history']);
        add_action('wp_ajax_gva_get_new_subjects', [$this, 'get_new_subjects']);
        
        // Nova Action para Salvar Configurações via AJAX
        add_action('wp_ajax_gva_save_settings', [$this, 'save_settings']);
        
        // Endpoints Dropdowns
        add_action('wp_ajax_gva_get_institutions', function() { $this->get_terms_simple('pqp_institutions'); });
        add_action('wp_ajax_gva_get_years', function() { $this->get_terms_simple('pqp_years'); });
        add_action('wp_ajax_gva_get_versions', function() { $this->get_terms_simple('pqp_versions'); });
        add_action('wp_ajax_gva_get_authors', function() { $this->get_terms_simple('pqp_authors'); });
        add_action('wp_ajax_gva_get_books', function() { $this->get_terms_simple('pqp_books', 'title'); });
        add_action('wp_ajax_gva_get_subjects', [$this, 'get_subjects_by_discipline']);
    }

    // --- Nova Função de Salvamento ---
    public function save_settings() {
        // Verifica o nonce para segurança (usa a constante definida no plugin principal)
        check_ajax_referer(GVA_NONCE, 'nonce');

        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Você não tem permissão para realizar esta ação.');
        }

        // Sanitiza e Salva
        if (isset($_POST['api_key'])) {
            $api_key = sanitize_text_field($_POST['api_key']);
            update_option('gva_gemini_api_key', $api_key);
            wp_send_json_success('Chave API salva com sucesso!');
        } else {
            wp_send_json_error('Nenhuma chave fornecida.');
        }
    }

    private function get_terms_simple($table_suffix, $col = 'name') {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        $results = $wpdb->get_results("SELECT $col as name FROM $table ORDER BY $col ASC LIMIT 500");
        wp_send_json_success($results);
    }
    
    public function get_subjects_by_discipline() {
        global $wpdb;
        $disc_name = sanitize_text_field($_GET['discipline']);
        $table_sub = $wpdb->prefix . 'pqp_subjects';
        $table_disc = $wpdb->prefix . 'pqp_disciplines';
        
        $sql = $wpdb->prepare("
            SELECT s.name 
            FROM $table_sub s 
            JOIN $table_disc d ON s.discipline_id = d.id 
            WHERE d.name = %s 
            ORDER BY s.name ASC", 
            $disc_name
        );
        
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }

    public function handle_request() {
        check_ajax_referer(GVA_NONCE, 'nonce');
        
        // Aumentar tempo de execução
        set_time_limit(300);

        $type = sanitize_text_field($_POST['type']);
        $params = $_POST['params'];
        $model = sanitize_text_field($params['ai_model']);

        // 1. Construir Prompt
        $system_instruction = $this->get_complex_system_instruction($type);
        $user_prompt = $this->build_user_prompt($type, $params);

        // 2. Arquivo (PDF)
        $file_url = ($type === 'oficial') ? $params['pdf_url'] : ($params['book_url'] ?? null);

        // 3. Chamar Gemini
        $response = $this->gemini->generate_content($model, $user_prompt, $system_instruction, $file_url);

        if (is_wp_error($response)) wp_send_json_error($response->get_error_message());
        
        $json_data = $response; // Já decodificado no Service
        if (!isset($json_data['questions']) || empty($json_data['questions'])) {
            wp_send_json_error('A IA não retornou questões válidas. Verifique o PDF ou instruções.');
        }

        // 4. Processar e Salvar
        $results = [];
        foreach ($json_data['questions'] as $q_data) {
            $post_id = $this->insert_question($q_data, $type, $params);
            
            if ($post_id) {
                $has_img = (isset($q_data['com_imagem']) && $q_data['com_imagem'] == '1');
                
                // Geração de Imagem (apenas para Similar)
                if ($type === 'similar' && $has_img && !empty($q_data['imagePrompt'])) {
                    // Implementação futura de geração de imagem DALL-E/Imagen
                    // Por enquanto apenas logamos a necessidade
                }

                $this->log_history($q_data['codigo_questao'], $type, $model, $has_img);
                $results[] = ['code' => $q_data['codigo_questao'], 'id' => $post_id];
            }
        }

        wp_send_json_success($results);
    }

    private function insert_question($data, $type, $params) {
        global $wpdb;

        // Gerar Código
        $disc_abbr = strtoupper(substr($params['disciplina'], 0, 3));
        if (empty($data['codigo_questao']) || $type === 'similar') {
             $data['codigo_questao'] = 'Q' . $disc_abbr . mt_rand(10000000000, 99999999999);
        }

        // Post WP
        $post_id = wp_insert_post([
            'post_title' => $data['codigo_questao'],
            'post_content' => $data['enunciado'],
            'post_status' => 'publish',
            'post_type' => 'questoes'
        ]);
        if (is_wp_error($post_id)) return false;

        // Metadados
        update_post_meta($post_id, 'resolucao', $data['resolucao']);
        update_post_meta($post_id, 'tipo_questao', $type === 'oficial' ? 'Questão Oficial' : 'Questão Similar');
        update_post_meta($post_id, 'avaliacao', 'experimental');
        update_post_meta($post_id, 'condicao', ''); 
        update_post_meta($post_id, 'com_imagem', $data['com_imagem'] ?? '0');
        update_post_meta($post_id, 'tipo_de_questao_radio', $type === 'oficial' ? 'oficial' : 'similar');
        
        if ($type === 'oficial') {
            update_post_meta($post_id, 'numero_oficial', $data['numero_oficial'] ?? 0);
        }

        // --- Taxonomias / Tabelas Personalizadas ---
        
        // 1. Assuntos (Novos ou Existentes)
        if (!empty($data['subjects_data'])) {
            $this->process_subjects($post_id, $data['subjects_data'], $params['disciplina'], $data['codigo_questao']);
        }

        // 2. Tags Extras
        if (!empty($data['extra_tags'])) {
            // Se vier como string separada por vírgula
            if(is_array($data['extra_tags'])) $tags = implode(',', $data['extra_tags']);
            else $tags = $data['extra_tags'];
            
            wp_set_post_tags($post_id, $tags, true);
            update_post_meta($post_id, 'extra_tags', $tags);
        }

        // 3. IDs Normalizados
        $disc_id = $this->get_or_create_normalized_id('pqp_disciplines', $params['disciplina'], 'abbreviation', substr($params['disciplina'], 0, 3));
        $inst_id = $this->get_or_create_normalized_id('pqp_institutions', $params['instituicao']);
        $year_id = $this->get_or_create_normalized_id('pqp_years', $params['ano']);
        // Versão precisa de Instituição
        $ver_id = $this->get_or_create_normalized_id('pqp_versions', $params['versao'], 'institution_id', $inst_id);
        
        $day_id = isset($params['dia']) ? $this->get_or_create_normalized_id('pqp_days', $params['dia']) : null;
        
        // Dificuldade: IA decide no oficial, User decide no similar (mas IA confirma)
        $diff_name = $data['nivel_dificuldade'] ?? $params['nivel_dificuldade'] ?? 'Médio';
        $diff_id = $this->get_or_create_normalized_id('pqp_difficulty_levels', $diff_name, 'order', 1);

        // Inserir na tabela principal PQP_TABLE_NAME (definida no main plugin)
        // Fallback constante
        if(!defined('PQP_TABLE_NAME')) define('PQP_TABLE_NAME', $wpdb->prefix . 'pqp_questions');

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
            'education_level_id' => 1, 
            'com_texto_adicional' => !empty($data['support_texts_data']) ? 1 : 0,
            'avaliacao' => 'experimental',
            'visibilidade' => 'visivel',
            'published' => 1
        ]);
        $db_question_id = $wpdb->insert_id;

        // 4. Alternativas
        if(!defined('PQP_ALTERNATIVES_TABLE_NAME')) define('PQP_ALTERNATIVES_TABLE_NAME', $wpdb->prefix . 'pqp_alternatives');
        foreach ($data['alternativas_data'] as $idx => $alt) {
            $wpdb->insert(PQP_ALTERNATIVES_TABLE_NAME, [
                'question_id' => $db_question_id,
                'text' => $alt['text'],
                'is_correct' => $alt['is_correct'],
                'order' => $idx
            ]);
        }
        update_post_meta($post_id, 'alternativas', $data['alternativas_data']);

        // 5. Textos de Apoio (Complexo)
        if (!empty($data['support_texts_data'])) {
            $this->process_support_texts($db_question_id, $data['support_texts_data']);
            update_post_meta($post_id, 'textos_apoio', $data['support_texts_data']);
        }

        return $post_id;
    }

    private function process_subjects($post_id, $subjects, $discipline_name, $q_code) {
        global $wpdb;
        $disc_id = $this->get_or_create_normalized_id('pqp_disciplines', $discipline_name);
        
        foreach ($subjects as $sub_name) {
            // Verificar se existe na tabela pqp_subjects ligado à disciplina
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}pqp_subjects WHERE name = %s AND discipline_id = %d", 
                $sub_name, $disc_id
            ));

            if (!$exists) {
                // Criar Novo Assunto
                $wpdb->insert($wpdb->prefix . 'pqp_subjects', ['name' => $sub_name, 'discipline_id' => $disc_id]);
                $this->log_new_subject($q_code, $sub_name);
            }
            
            // Linkar WP Term (assunto)
            $term = term_exists($sub_name, 'assunto');
            if (!$term) {
                $term = wp_insert_term($sub_name, 'assunto');
            }
            if (!is_wp_error($term)) {
                wp_set_object_terms($post_id, (int)$term['term_id'], 'assunto', true);
            }
        }
    }

    private function process_support_texts($db_q_id, $texts_data) {
        global $wpdb;
        if(!defined('PQP_SUPPORT_TEXTS_TABLE_NAME')) define('PQP_SUPPORT_TEXTS_TABLE_NAME', $wpdb->prefix . 'pqp_support_texts');

        foreach ($texts_data as $idx => $txt) {
            // IDs principais
            $typ_id = $this->get_or_create_normalized_id('pqp_text_typologies', $txt['typology_name'] ?? null);
            $gen_id = $this->get_or_create_normalized_id('pqp_text_genres', $txt['genre_name'] ?? null);
            $sub_id = $this->get_or_create_normalized_id('pqp_text_subgenres', $txt['subgenre_name'] ?? null, 'genre_id', $gen_id);
            $per_id = $this->get_or_create_normalized_id('pqp_literary_periods', $txt['literary_period_name'] ?? null);

            $wpdb->insert(PQP_SUPPORT_TEXTS_TABLE_NAME, [
                'question_id' => $db_q_id,
                'content' => $txt['content'] ?? '',
                'typology_id' => $typ_id,
                'genre_id' => $gen_id,
                'subgenre_id' => $sub_id,
                'literary_period_id' => $per_id,
                'ordem' => $idx
            ]);
            $text_id = $wpdb->insert_id;

            // Relacionamentos N:N
            $this->process_text_meta($text_id, 'pqp_authors', 'pqp_support_text_authors', 'author_id', $txt['author_name'] ?? null);
            
            // Livro precisa de Autor ID? Simplificado aqui para criar se não existir
            // Se Author for null, book pode ficar orfão de autor na tabela book, mas aqui focamos na ligação
            $author_id_for_book = $this->get_or_create_normalized_id('pqp_authors', $txt['author_name'] ?? 'Desconhecido');
            $this->process_text_meta($text_id, 'pqp_books', 'pqp_support_text_books', 'book_id', $txt['book_title'] ?? null, 'author_id', $author_id_for_book);
            
            $this->process_text_meta($text_id, 'pqp_nationalities', 'pqp_support_text_nationalities', 'nationality_id', $txt['nationality_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_types', 'pqp_support_text_text_types', 'text_type_id', $txt['text_type_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_forms', 'pqp_support_text_forms', 'text_form_id', $txt['text_form_name'] ?? null);
            $this->process_text_meta($text_id, 'pqp_text_titles', 'pqp_support_text_titles', 'text_title_id', $txt['text_title_name'] ?? null);
        }
    }

    private function get_or_create_normalized_id($table_suffix, $name, $extra_col = null, $extra_val = null) {
        if (empty($name) || $name === 'null') return 0; // null string check
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;
        
        // Verifica se existe
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
        
        if (!$id) {
            $data = ['name' => $name];
            if ($extra_col && $extra_val) {
                $data[$extra_col] = $extra_val;
            }
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        return $id;
    }

    private function process_text_meta($support_text_id, $main_table, $link_table, $fk_column, $value, $parent_col = null, $parent_val = null) {
        if (empty($value) || $value === 'null') return;
        global $wpdb;
        $link_tbl_full = $wpdb->prefix . $link_table;

        $term_id = $this->get_or_create_normalized_id($main_table, $value, $parent_col, $parent_val);
        
        $wpdb->insert($link_tbl_full, [
            'support_text_id' => $support_text_id,
            $fk_column => $term_id
        ]);
    }

    private function get_complex_system_instruction($type) {
        $schema = [
            "questions" => [[
                "codigo_questao" => "Q[DISC][RANDOM]",
                "enunciado" => "HTML",
                "resolucao" => "HTML (PT-BR, Detalhado, Sem emojis/bullets, KaTeX $...$)",
                "numero_oficial" => 1,
                "com_imagem" => "0 ou 1",
                "nivel_dificuldade" => "Fácil, Médio ou Difícil",
                "subjects_data" => ["Assunto 1", "Assunto 2"],
                "extra_tags" => ["Tag1", "Tag2", "Tag3", "Tag4", "Tag5"],
                "alternativas_data" => [["text" => "HTML", "is_correct" => "1 ou 0", "order" => 0]],
                "support_texts_data" => [[
                    "content" => "HTML ou vazio",
                    "text_title_name" => "Título",
                    "author_name" => "Nome ou Desconhecido (Evitar)",
                    "book_title" => "Livro",
                    "nationality_name" => "País",
                    "text_type_name" => "Literário, Não Literário ou Híbrido",
                    "text_form_name" => "Prosa, Verso ou Híbrido",
                    "typology_name" => "Narrativo, etc",
                    "genre_name" => "Gênero",
                    "subgenre_name" => "Subgênero",
                    "literary_period_name" => "Período"
                ]]
            ]]
        ];

        $json_str = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = "Atue como um Especialista em Vestibulares Brasileiros (ProOne). \n";
        $prompt .= "OBJETIVO: Retornar um JSON VÁLIDO estritamente seguindo o schema abaixo.\n";
        $prompt .= "SCHEMA JSON: $json_str \n\n";
        
        $prompt .= "REGRAS CRÍTICAS:\n";
        $prompt .= "1. **Fórmulas**: Use KaTeX entre cifrões (ex: \$E=mc^2\$).\n";
        $prompt .= "2. **Resolução**: Profunda, didática, em PT-BR. NUNCA use listas com bolinhas ou emojis. Texto corrido em parágrafos HTML.\n";
        $prompt .= "3. **Assuntos**: Identifique o assunto com precisão. Se não existir na lista padrão, crie um específico.\n";
        $prompt .= "4. **Tags**: Gere entre 5 a 10 tags relevantes.\n";
        $prompt .= "5. **Detalhes do Texto (IMPORTANTE)**:\n";
        $prompt .= "   - Se o texto for 'Sobre outro texto' (ex: Resenha de um Livro), crie DOIS objetos em 'support_texts_data'. \n";
        $prompt .= "     Objeto 1: O texto principal (ex: Resenha, Não Literário, Autor da Resenha).\n";
        $prompt .= "     Objeto 2: A obra referenciada (Preencha apenas author_name e book_title, demais null).\n";
        $prompt .= "   - Preencha TODOS os campos possíveis. Evite 'Desconhecido' para Autor. Pesquise se necessário.\n";
        $prompt .= "   - Nacionalidade é OBRIGATÓRIA (exceto se autor desconhecido).\n";
        $prompt .= "   - Se 'Literário' ou 'Híbrido', Período, Gênero e Subgênero são OBRIGATÓRIOS.\n";
        
        if ($type === 'oficial') {
            $prompt .= "CONTEXTO: Analise o PDF fornecido. Extraia as questões indicadas nas instruções. Copie fielmente o enunciado e alternativas. Gere a resolução baseada no gabarito.\n";
        } else {
            $prompt .= "CONTEXTO: Gere questões INÉDITAS (Similares). Use o livro PDF anexo como base se houver, ou crie textos fictícios baseados nos parâmetros.\n";
        }

        return $prompt;
    }

    private function build_user_prompt($type, $params) {
        if ($type === 'oficial') {
            return "Instruções: {$params['instrucoes']}. Metadados: Disciplina {$params['disciplina']}, Instituição {$params['instituicao']}, Ano {$params['ano']}.";
        } else {
            $req = "Gerar {$params['quantidade']} questões. Disciplina: {$params['disciplina']}. Assunto: {$params['assunto']}. ";
            $req .= "Detalhes Texto: Tipo {$params['tipo_texto']}, Gênero {$params['genero']}, Autor {$params['autor']}, Livro {$params['livro']}. ";
            $req .= ($params['com_imagem'] == '1') ? "COM IMAGEM (Descreva no imagePrompt)." : "SEM IMAGEM.";
            return $req;
        }
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
        $log = get_option('gva_new_subjects_log', []);
        array_unshift($log, ['code' => $code, 'subject' => $subject, 'date' => current_time('mysql')]);
        update_option('gva_new_subjects_log', array_slice($log, 0, 100));
    }

    public function get_history() {
        global $wpdb;
        $table = $wpdb->prefix . 'gva_history';
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC LIMIT 50");
        wp_send_json_success($results);
    }

    public function get_new_subjects() {
        wp_send_json_success(get_option('gva_new_subjects_log', []));
    }
}