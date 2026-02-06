<?php
// views/admin-main.php

global $wpdb;
// Buscando dados iniciais
$disciplinas = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}pqp_disciplines ORDER BY name");
$application_days = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_application_days ORDER BY id ASC");
$niveis = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_difficulty_levels ORDER BY `order`");
$tipos_texto = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_types ORDER BY name");
$formas = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_forms ORDER BY name");
$tipologias = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_typologies ORDER BY name");
$generos = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_genres ORDER BY name");
$subgeneros = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_text_subgenres ORDER BY name"); // Novo
$periodos = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_literary_periods ORDER BY id");
$nacionalidades = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}pqp_nationalities ORDER BY name");

// Modelos Gemini Atualizados
$gemini_models = [
    'gemini-3-pro-preview' => 'Gemini 3 Pro Preview',
    'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
    'gemini-2.5-pro' => 'Gemini 2.5 Pro',
    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
    'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite'
];
?>

<div class="wrap gva-wrapper pqp-wrap"> 
    <h1><span class="dashicons dashicons-superhero"></span> Register Manager AI Proone</h1>
    
    <div class="gva-tabs">
        <button class="gva-tab active" data-target="#cadastrar">Cadastrar Questão</button>
        <button class="gva-tab" data-target="#gerar">Gerar Questão</button>
        <button class="gva-tab" data-target="#novos-assuntos">Novos Assuntos</button>
        <button class="gva-tab" data-target="#historico">Histórico</button>
        <button class="gva-tab" data-target="#config">Configurações</button>
    </div>

    <div class="gva-content">
        
        <div id="cadastrar" class="gva-section active">
            <h2>Cadastrar Questão (via PDF)</h2>
            <form id="form-cadastrar-oficial">
                <div class="gva-grid">
                    <div class="gva-field">
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

                    <div class="gva-field">
                        <label>Instituição</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="instituicao" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="cad_inst_dropdown">Digite ou selecione</button>
                            <div id="cad_inst_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>

                    <div class="gva-field">
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

                    <div class="gva-field">
                        <label>Versão</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="versao" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="cad_versao_dropdown">Digite ou selecione</button>
                            <div id="cad_versao_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar ou adicionar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>

                    <div class="gva-field">
                        <label>Dia</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="dia" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="cad_dia_dropdown">Selecione</button>
                            <div id="cad_dia_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($application_days as $day) echo "<a href='#' data-value='" . esc_attr($day) . "'>" . esc_html($day) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    </div>

                <div class="gva-upload-wrapper">
                    <label style="font-weight:bold; display:block; margin-bottom:10px;">Anexar Prova (PDF)</label>
                    <div style="display:flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary" id="upload_pdf_btn">
                            <span class="dashicons dashicons-paperclip"></span> Selecionar PDF
                        </button>
                        <span id="pdf_filename" style="color: #666; font-style: italic;">Nenhum arquivo selecionado</span>
                        <input type="hidden" name="pdf_url" id="pdf_url">
                    </div>
                </div>

                <div class="gva-field full-width" style="margin-top:20px;">
                    <label>Instruções (Gabarito e Questões)</label>
                    <textarea name="instrucoes" rows="6" placeholder="Ex: Cadastrar Questões 45, 46 e 47.&#10;Gabarito:&#10;45 - A&#10;46 - C&#10;47 - E&#10;Observação: A questão 47 foi anulada, ignorar." required></textarea>
                </div>

                <div class="gva-actions">
                    <div class="gva-field" style="width: 250px;">
                        <label>Modelo IA:</label>
                        <select name="ai_model">
                            <?php foreach($gemini_models as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="button button-primary button-hero">Cadastrar</button>
                </div>
            </form>
            <div id="result-cadastrar" class="gva-log"></div>
        </div>

        <div id="gerar" class="gva-section">
            <h2>Gerar Questão (Inédita/Similar)</h2>
            <form id="form-gerar-similar">
                <div class="gva-grid">
                    <div class="gva-field">
                        <label>Disciplina</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="disciplina" id="ger_disciplina" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_disciplina_dropdown">Selecione</button>
                            <div id="ger_disciplina_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($disciplinas as $d) echo "<a href='#' data-value='" . esc_attr($d->name) . "'>" . esc_html($d->name) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    <div class="gva-field">
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
                    <div class="gva-field">
                        <label>Instituição (Estilo)</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="instituicao">
                            <button type="button" class="pqp-creatable-select-btn" data-target="ger_inst_dropdown">Digite ou selecione</button>
                            <div id="ger_inst_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                    <div class="gva-field">
                        <label>Nível de Dificuldade</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="nivel_dificuldade" required>
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_nivel_dropdown">Selecione</button>
                            <div id="ger_nivel_dropdown" class="pqp-singleselect-dropdown">
                                <?php foreach ($niveis as $n) echo "<a href='#' data-value='" . esc_attr($n) . "'>" . esc_html($n) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                     <div class="gva-field">
                        <label>Ano (Referência)</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="ano" required>
                            <button type="button" class="pqp-creatable-select-btn" data-target="ger_ano_dropdown">Digite ou selecione</button>
                            <div id="ger_ano_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                    <div class="gva-field">
                        <label>Versão</label>
                        <div class="pqp-creatable-select">
                            <input type="hidden" name="versao">
                            <button type="button" class="pqp-creatable-select-btn" data-target="ger_versao_dropdown">Digite ou selecione</button>
                            <div id="ger_versao_dropdown" class="pqp-creatable-select-dropdown">
                                <input type="text" class="pqp-creatable-select-search" placeholder="Pesquisar...">
                                <div class="pqp-creatable-select-options"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <h3>Detalhes do Texto Base (Opcional - Para Geração)</h3>
                <div class="gva-grid">
                    <div class="gva-field">
                        <label>Título do Texto</label>
                        <input type="text" name="titulo_texto" class="regular-text" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                     <div class="gva-field">
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
                    <div class="gva-field">
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
                    <div class="gva-field">
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
                    <div class="gva-field">
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
                    <div class="gva-field">
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
                    <div class="gva-field">
                        <label>Período Literário</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="periodo">
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_periodo_dropdown">Todos</button>
                            <div id="ger_periodo_dropdown" class="pqp-singleselect-dropdown">
                                <a href="#" data-value="">Todos</a>
                                <?php foreach ($periodos as $p) echo "<a href='#' data-value='" . esc_attr($p) . "'>" . esc_html($p) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    <div class="gva-field">
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
                    <div class="gva-field">
                        <label>Gênero</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="genero">
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_genero_dropdown">Todos</button>
                            <div id="ger_genero_dropdown" class="pqp-singleselect-dropdown">
                                <a href="#" data-value="">Todos</a>
                                <?php foreach ($generos as $g) echo "<a href='#' data-value='" . esc_attr($g) . "'>" . esc_html($g) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                    <div class="gva-field">
                        <label>Subgênero</label>
                        <div class="pqp-singleselect">
                            <input type="hidden" name="subgenero">
                            <button type="button" class="pqp-singleselect-btn" data-target="ger_subgenero_dropdown">Todos</button>
                            <div id="ger_subgenero_dropdown" class="pqp-singleselect-dropdown">
                                <a href="#" data-value="">Todos</a>
                                <?php foreach ($subgeneros as $s) echo "<a href='#' data-value='" . esc_attr($s) . "'>" . esc_html($s) . "</a>"; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gva-upload-wrapper" style="margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 8px;">
                    <label style="font-weight:bold; display:block; margin-bottom:10px;">Anexar Livro Base (PDF) - Opcional</label>
                    <div style="display:flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary" id="upload_book_btn">
                            <span class="dashicons dashicons-book"></span> Selecionar Livro
                        </button>
                        <span id="book_filename" style="color: #666; font-style: italic;">Nenhum arquivo selecionado</span>
                        <input type="hidden" name="book_url" id="book_url">
                    </div>
                </div>

                <div class="gva-toggle">
                    <label>Gerar com Imagem?</label>
                    <input type="radio" name="com_imagem" value="1" id="img_sim"> <label for="img_sim">Sim</label>
                    <input type="radio" name="com_imagem" value="0" id="img_nao" checked> <label for="img_nao">Não</label>
                </div>

                <div class="gva-actions">
                    <div class="gva-field" style="width: 250px; display:inline-block; vertical-align:middle; margin-right:15px;">
                        <label>Quantidade:</label>
                        <input type="number" name="quantidade" value="1" max="5" min="1" style="width: 100%;">
                    </div>
                     <div class="gva-field" style="width: 250px; display:inline-block; vertical-align:middle; margin-right:15px;">
                        <label>Modelo IA:</label>
                        <select name="ai_model">
                             <?php foreach($gemini_models as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="button button-primary button-hero">Gerar Questão</button>
                </div>
            </form>
            <div id="result-gerar" class="gva-log"></div>
        </div>

        <div id="novos-assuntos" class="gva-section">
            <h2>Novos Assuntos Criados pela IA</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Código Questão</th><th>Novo Assunto</th><th>Data</th></tr></thead>
                <tbody id="tbody-novos-assuntos"></tbody>
            </table>
        </div>

        <div id="historico" class="gva-section">
            <h2>Histórico de Operações</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Data</th><th>Código</th><th>Tipo</th><th>Modelo IA</th><th>Imagem</th><th>Status</th></tr></thead>
                <tbody id="tbody-historico"></tbody>
            </table>
        </div>

        <div id="config" class="gva-section">
            <h2>Configurações do Plugin</h2>
            <form id="form-config">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <input type="password" name="gva_gemini_api_key" id="gva_gemini_api_key" value="<?php echo esc_attr(get_option('gva_gemini_api_key')); ?>" class="regular-text" style="width: 100%; max-width: 400px;"/>
                            <p class="description">Insira sua chave da API Google Gemini AI Studio.</p>
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