<?php
global $wpdb;

// Buscando dados iniciais do banco
$disciplinas = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}pqp_disciplines ORDER BY name");
$application_days = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_application_days ORDER BY id ASC");
$niveis = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_difficulty_levels ORDER BY `order`");
$education_levels = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_education_levels ORDER BY id");
$tipos_texto = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_types ORDER BY name");
$formas = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_forms ORDER BY name");
$tipologias = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_typologies ORDER BY name");
$generos = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_genres ORDER BY name");
$subgeneros = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_subgenres ORDER BY name");
$periodos = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_literary_periods ORDER BY id");
$nacionalidades = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_nationalities ORDER BY name");

// --- MODELOS DISPONÍVEIS ---
$ai_models = [
    'GROQ CLOUD' => [
        'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Groq)',
        'llama-3.1-70b-versatile' => 'Llama 3.1 70B (Groq)',
        'llama-3.1-8b-instant'    => 'Llama 3.1 8B (Groq)',
        'mixtral-8x7b-32768'      => 'Mixtral 8x7B (Groq)'
    ],
    'HUGGING FACE (Texto)' => [
        'hf_meta-llama/Llama-3.2-3B-Instruct'    => 'Llama 3.2 3B Instruct',
        'hf_Qwen/Qwen2.5-72B-Instruct'           => 'Qwen 2.5 72B Instruct',
        'hf_mistralai/Mistral-7B-Instruct-v0.3'  => 'Mistral 7B Instruct v0.3',
        'hf_microsoft/Phi-3-mini-4k-instruct'    => 'Phi-3 Mini 4k'
    ]
];

// --- MODELOS DE IMAGEM (Hugging Face) ---
$image_models = [
    'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL Base 1.0',
    'black-forest-labs/FLUX.1-schnell'         => 'FLUX.1 Schnell',
    'runwayml/stable-diffusion-v1-5'           => 'Stable Diffusion v1.5',
    'stabilityai/sdxl-turbo'                   => 'SDXL Turbo'
];
?>

<div class="wrap p1ai-wrapper pqp-wrap"> 
    <h1><span class="dashicons dashicons-superhero"></span> ProOne AI Manager</h1>
    
    <div class="p1ai-tabs">
        <button class="p1ai-tab active" data-target="#cadastrar">Cadastrar</button>
        <button class="p1ai-tab" data-target="#gerar">Gerar</button> 
        <button class="p1ai-tab" data-target="#novos-assuntos">Novos Assuntos</button>
        <button class="p1ai-tab" data-target="#historico">Histórico</button>
        <button class="p1ai-tab" data-target="#config">Configurações</button>
    </div>

    <div class="p1ai-content">
        
        <div id="cadastrar" class="p1ai-section active">
            <h2>Cadastrar Questão via PDF</h2>
            <form id="form-cadastrar-oficial">
                <div class="p1ai-grid">
                    <div class="p1ai-field">
                        <label>Disciplina</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="disciplina" id="cad_disciplina" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="cad_disciplina_dropdown">Selecione</button>
                            <div id="cad_disciplina_dropdown" class="pqp-singleselect-dropdown">
                                <a href="#" data-value="">Selecione</a>
                                <?php foreach ($disciplinas as $d) echo "<a href='#' data-value='" . esc_attr($d->name) . "'>" . esc_html($d->name) . "</a>"; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Instituição</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="instituicao" id="cad_instituicao" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="cad_inst_dropdown">Digite ou selecione</button>
                            <div id="cad_inst_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p1ai-field">
                        <label>Nível de Ensino</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="nivel_ensino" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="cad_nivel_ensino_dropdown">Selecione</button>
                            <div id="cad_nivel_ensino_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($education_levels as $el) echo "<a href='#' data-value='" . esc_attr($el) . "'>" . esc_html($el) . "</a>"; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Ano</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="ano" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="cad_ano_dropdown">Digite ou selecione</button>
                            <div id="cad_ano_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Versão</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="versao" required>
                            <button type="button" class="pqp-creatable-select-btn" id="cad_versao_btn" data-target="cad_versao_dropdown">Selecione a Instituição primeiro</button>
                            <div id="cad_versao_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Dia</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="dia" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="cad_dia_dropdown">Selecione</button>
                            <div id="cad_dia_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($application_days as $day) echo "<a href='#' data-value='" . esc_attr($day) . "'>" . esc_html($day) . "</a>"; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Avaliação</label>
                        <select name="avaliacao" style="width:100%; height:40px; border:1px solid #ddd; border-radius:4px;">
                            <option value="experimental" selected>Experimental</option>
                        </select>
                    </div>
                </div>

                <div class="p1ai-upload-wrapper">
                    <label style="font-weight:bold; display:block; margin-bottom:10px;">Anexar Prova (PDF)</label>
                    <div style="display:flex; align-items: center; gap: 15px;">
                        <span id="pdf_filename" style="color: #666; font-style: italic;">Nenhum arquivo selecionado</span>
                        <input type="hidden" name="pdf_url" id="pdf_url">
                    </div>
                    <button type="button" class="button button-secondary" id="upload_pdf_btn" style="margin-top: 10px;">
                        <span class="dashicons dashicons-paperclip"></span> Selecionar PDF
                    </button>
                </div>

                <div class="p1ai-field full-width" style="margin-top:20px;">
                    <label>Instruções (Gabarito e Questões)</label>
                    <textarea name="instrucoes" rows="6" placeholder="Ex: Cadastrar Questões 45, 46 e 47..." required></textarea>
                </div>

                <input type="hidden" name="tipo_questao_radio" value="oficial">

                <div class="p1ai-actions">
                    <div class="p1ai-field" style="width: 300px; margin-right: 20px;">
                        <label>Modelo de Texto:</label>
                        <select name="ai_model" id="cad_ai_model">
                            <?php foreach($ai_models as $group => $models): ?>
                                <optgroup label="<?php echo $group; ?>">
                                    <?php foreach($models as $key => $val): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="p1ai-field p1ai-temp-control" style="width: 150px; margin-right: 20px;">
                        <label>Temperatura: <span id="temp-val-cad">0.2</span></label>
                        <input type="range" name="temperature" min="0" max="1" step="0.1" value="0.2" oninput="document.getElementById('temp-val-cad').innerText = this.value">
                    </div>
                    <button type="submit" class="button button-primary button-hero">Cadastrar</button>
                </div>
            </form>
            <div id="result-cadastrar" class="p1ai-log"></div>
        </div>

        <div id="gerar" class="p1ai-section">
            <h2>Gerar Questão Inédita</h2> 
            <form id="form-gerar-similar">
                <div class="p1ai-grid">
                    <div class="p1ai-field">
                        <label>Disciplina</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="disciplina" id="ger_disciplina" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_disciplina_dropdown">Selecione</button>
                            <div id="ger_disciplina_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($disciplinas as $d) echo "<a href='#' data-value='" . esc_attr($d->name) . "'>" . esc_html($d->name) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p1ai-field">
                        <label>Nível de Ensino</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="nivel_ensino" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_nivel_ensino_dropdown">Selecione</button>
                            <div id="ger_nivel_ensino_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($education_levels as $el) echo "<a href='#' data-value='" . esc_attr($el) . "'>" . esc_html($el) . "</a>"; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Assunto</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="assunto" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="ger_assunto_dropdown">Digite ou selecione</button>
                            <div id="ger_assunto_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                    <div class="p1ai-field">
                        <label>Instituição (Estilo)</label> 
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="instituicao" id="ger_instituicao">
                            <button type="button" class="pqp-creatable-select-btn" data-target="ger_inst_dropdown">Digite ou selecione</button>
                            <div id="ger_inst_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-field">
                        <label>Versão</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="versao">
                            <button type="button" class="pqp-creatable-select-btn" id="ger_versao_btn" data-target="ger_versao_dropdown">Selecione a Instituição primeiro</button>
                            <div id="ger_versao_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p1ai-field">
                        <label>Nível de Dificuldade</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="nivel_dificuldade" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_nivel_dropdown">Selecione</button>
                            <div id="ger_nivel_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($niveis as $n) echo "<a href='#' data-value='" . esc_attr($n) . "'>" . esc_html($n) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    <div class="p1ai-field"><label>Ano (Referência)</label><input type="text" name="ano" class="regular-text" style="width:100%" placeholder="Ex: 2024"></div>

                    <div class="p1ai-field">
                        <label>Avaliação</label>
                        <select name="avaliacao" style="width:100%; height:40px; border:1px solid #ddd; border-radius:4px;">
                            <option value="experimental" selected>Experimental</option>
                        </select>
                    </div>
                </div>

                <div class="p1ai-field full-width" style="margin-bottom: 20px;">
                    <label>Estilo da Questão (Instruções Adicionais)</label>
                    <textarea name="estilo_questao" class="p1ai-large-textarea" placeholder="Ex: Foco em interpretação, interdisciplinar, contextualizada com atualidades..."></textarea>
                </div>

                <div class="p1ai-toggle" style="margin: 20px 0; border-top: 1px solid #eee; padding-top: 15px;">
                    <label style="font-weight:bold; font-size:14px; margin-right:10px;">Criar Texto Base?</label>
                    <input type="radio" name="com_texto" value="1" id="txt_sim"> <label for="txt_sim" style="margin-right:15px;">Sim</label>
                    <input type="radio" name="com_texto" value="0" id="txt_nao" checked> <label for="txt_nao">Não</label>
                </div>

                <div id="detalhes-texto-container" style="display:none;">
                    <h3>Detalhes do Texto</h3> 
                    <div class="p1ai-grid">
                        <div class="p1ai-field">
                            <label>Título do Texto</label>
                            <input type="text" name="titulo_texto" class="regular-text" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                        </div>
                         <div class="p1ai-field">
                            <label>Autor</label>
                            <div class="pqp-creatable-select">
                                <input type="hidden" name="autor">
                                <button type="button" class="pqp-creatable-select-btn" data-target="ger_autor_dropdown">Digite ou selecione</button>
                                <div id="ger_autor_dropdown" class="pqp-creatable-select-dropdown">
                                    <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                    <div class="pqp-creatable-select-options"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Livro</label>
                            <div class="pqp-creatable-select">
                                <input type="hidden" name="livro">
                                <button type="button" class="pqp-creatable-select-btn" data-target="ger_livro_dropdown">Digite ou selecione</button>
                                <div id="ger_livro_dropdown" class="pqp-creatable-select-dropdown">
                                    <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                    <div class="pqp-creatable-select-options"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Nacionalidade</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="nacionalidade">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_nacionalidade_dropdown">Todos</button>
                                <div id="ger_nacionalidade_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($nacionalidades as $n) echo "<a href='#' data-value='" . esc_attr($n) . "'>" . esc_html($n) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Tipo de Texto</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="tipo_texto">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_tipo_texto_dropdown">Todos</button>
                                <div id="ger_tipo_texto_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($tipos_texto as $t) echo "<a href='#' data-value='" . esc_attr($t) . "'>" . esc_html($t) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Forma</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="forma_texto">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_forma_dropdown">Todos</button>
                                <div id="ger_forma_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($formas as $f) echo "<a href='#' data-value='" . esc_attr($f) . "'>" . esc_html($f) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Período</label> 
                            <div class="pqp-singleselect">
                                <input type="hidden" name="periodo">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_periodo_dropdown">Todos</button>
                                <div id="ger_periodo_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($periodos as $p) echo "<a href='#' data-value='" . esc_attr($p) . "'>" . esc_html($p) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Tipologia</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="tipologia">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_tipologia_dropdown">Todos</button>
                                <div id="ger_tipologia_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($tipologias as $t) echo "<a href='#' data-value='" . esc_attr($t) . "'>" . esc_html($t) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Gênero</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="genero" id="ger_genero">
                                <button type="button" class="pqp-singleselect-btn" data-target="ger_genero_dropdown">Todos</button>
                                <div id="ger_genero_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    <?php foreach ($generos as $g) echo "<a href='#' data-value='" . esc_attr($g) . "'>" . esc_html($g) . "</a>"; ?>
                                </div>
                            </div>
                        </div>
                        <div class="p1ai-field">
                            <label>Subgênero</label>
                            <div class="pqp-singleselect">
                                <input type="hidden" name="subgenero">
                                <button type="button" class="pqp-singleselect-btn" id="ger_subgenero_btn" data-target="ger_subgenero_dropdown">Selecione o Gênero</button>
                                <div id="ger_subgenero_dropdown" class="pqp-singleselect-dropdown">
                                    <a href="#" data-value="">Todos</a>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <div class="p1ai-upload-wrapper" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 8px;">
                        <label style="font-weight:bold; display:block; margin-bottom:10px;">Selecionar Livro (PDF) - Opcional</label> <div style="display:flex; align-items: center; gap: 15px;">
                            <span id="book_filename" style="color: #666; font-style: italic;">Nenhum arquivo selecionado</span>
                            <input type="hidden" name="book_url" id="book_url">
                        </div>
                        <button type="button" class="button button-secondary" id="upload_book_btn" style="margin-top:10px;">
                            <span class="dashicons dashicons-book"></span> Selecionar
                        </button>
                    </div>
                </div>

                <div class="p1ai-toggle" style="margin-top:20px; background: #f0f0f1; padding: 15px; border-radius: 5px;">
                    <label style="font-weight:bold; font-size:14px; margin-right:10px;">Gerar com Imagem?</label>
                    <input type="radio" name="com_imagem" value="1" id="img_sim"> <label for="img_sim" style="margin-right:15px;">Sim</label>
                    <input type="radio" name="com_imagem" value="0" id="img_nao" checked> <label for="img_nao">Não</label>

                    <div id="image-model-selector" style="display: none; margin-top: 15px;">
                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">Modelo de Imagem (Hugging Face):</label>
                        <select name="image_model" id="ger_image_model" style="width: 100%; max-width: 400px;">
                            <?php foreach($image_models as $key => $val): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($val); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="tipo_questao_radio" value="similar">

                <div class="p1ai-actions">
                    <div class="p1ai-field" style="width: 100px; margin-right:15px;">
                        <label>Qtd:</label>
                        <input type="number" name="quantidade" value="1" max="5" min="1" style="width: 100%;">
                    </div>
                     <div class="p1ai-field" style="width: 300px; margin-right:15px;">
                        <label>Modelo de Texto:</label>
                        <select name="ai_model" id="ger_ai_model">
                             <?php foreach($ai_models as $group => $models): ?>
                                <optgroup label="<?php echo $group; ?>">
                                    <?php foreach($models as $key => $val): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="p1ai-field p1ai-temp-control" style="width: 150px; margin-right: 20px;">
                        <label>Temperatura: <span id="temp-val-ger">0.5</span></label>
                        <input type="range" name="temperature" min="0" max="1" step="0.1" value="0.5" oninput="document.getElementById('temp-val-ger').innerText = this.value">
                    </div>
                    <button type="submit" class="button button-primary button-hero">Gerar</button>
                </div>
            </form>
            <div id="result-gerar" class="p1ai-log"></div>
        </div>

        <div id="novos-assuntos" class="p1ai-section">
            <h2>Novos Assuntos</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Código</th><th>Novo Assunto</th><th>Data</th></tr></thead>
                <tbody id="tbody-novos-assuntos"></tbody>
            </table>
        </div>

        <div id="historico" class="p1ai-section">
            <h2>Histórico de Operações</h2>
            <div style="overflow-x:auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Data</th><th>Código</th><th>Tipo</th><th>Modelo IA</th><th>Imagem</th><th>Status</th></tr></thead>
                    <tbody id="tbody-historico"></tbody>
                </table>
            </div>
        </div>

        <div id="config" class="p1ai-section">
            <h2>Configurações de API</h2>
            <form id="form-config">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Groq Cloud API Key</th>
                        <td>
                            <input type="password" name="p1ai_groq_api_key" id="p1ai_groq_api_key" value="<?php echo esc_attr(get_option('p1ai_groq_api_key')); ?>" class="regular-text" style="width: 100%; max-width: 400px;"/>
                            <p class="description">Para modelos Llama e Mixtral (Groq).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Hugging Face API Token</th>
                        <td>
                            <input type="password" name="p1ai_huggingface_api_key" id="p1ai_huggingface_api_key" value="<?php echo esc_attr(get_option('p1ai_huggingface_api_key')); ?>" class="regular-text" style="width: 100%; max-width: 400px;"/>
                            <p class="description">Necessário para modelos de imagem (Stable Diffusion/Flux) e modelos de texto via HF Inference.</p>
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary">Salvar Configurações</button>
                    <span id="config-feedback" style="margin-left: 10px; font-weight: bold;"></span>
                </div>
            </form>
        </div>

    </div>
</div>