<div class="wrap gva-wrapper">
    <h1><span class="dashicons dashicons-superhero"></span> Gemini Vestibular AI - Gerenciador</h1>
    
    <div class="gva-tabs">
        <button class="gva-tab active" data-target="#cadastrar">Cadastrar Questão (Oficial)</button>
        <button class="gva-tab" data-target="#gerar">Gerar Questão (Similar)</button>
        <button class="gva-tab" data-target="#novos-assuntos">Novos Assuntos</button>
        <button class="gva-tab" data-target="#historico">Histórico</button>
        <button class="gva-tab" data-target="#config">Configurações</button>
    </div>

    <div class="gva-content">
        
        <!-- SECTION: CADASTRAR OFICIAL -->
        <div id="cadastrar" class="gva-section active">
            <h2>Cadastrar Questão Oficial (PDF)</h2>
            <form id="form-cadastrar-oficial">
                <div class="gva-grid">
                    <div class="gva-field">
                        <label>Disciplina</label>
                        <select name="disciplina" id="cad_disciplina" required>
                            <option value="PRT">Língua Portuguesa</option>
                            <option value="MTM">Matemática</option>
                            <!-- Add others -->
                        </select>
                    </div>
                    <div class="gva-field">
                        <label>Instituição</label>
                        <input type="text" name="instituicao" placeholder="Ex: UEMA" required>
                    </div>
                    <div class="gva-field">
                        <label>Ano</label>
                        <input type="number" name="ano" placeholder="Ex: 2026" required>
                    </div>
                    <div class="gva-field">
                        <label>Versão</label>
                        <input type="text" name="versao" placeholder="Ex: PAES">
                    </div>
                    <div class="gva-field">
                        <label>Dia</label>
                        <select name="dia">
                            <option value="1º Dia">1º Dia</option>
                            <option value="2º Dia">2º Dia</option>
                        </select>
                    </div>
                    <div class="gva-field">
                        <label>Nº Inicial</label>
                        <input type="number" name="numero_inicial" placeholder="Ex: 1">
                    </div>
                    <div class="gva-field">
                        <label>Quantidade</label>
                        <input type="number" name="quantidade" value="5" max="10" min="1">
                    </div>
                </div>

                <div class="gva-upload-area">
                    <label>Anexar Prova (PDF)</label>
                    <button type="button" class="button" id="upload_pdf_btn">Selecionar PDF</button>
                    <input type="hidden" name="pdf_url" id="pdf_url">
                    <span id="pdf_filename">Nenhum arquivo selecionado</span>
                </div>

                <div class="gva-actions">
                    <label>Modelo IA:</label>
                    <select name="ai_model">
                        <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                        <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                    </select>
                    <button type="submit" class="button button-primary button-hero">Processar Questões</button>
                </div>
            </form>
            <div id="result-cadastrar" class="gva-log"></div>
        </div>

        <!-- SECTION: GERAR SIMILAR -->
        <div id="gerar" class="gva-section">
            <h2>Gerar Questão Similar</h2>
            <form id="form-gerar-similar">
                <div class="gva-grid">
                    <div class="gva-field">
                        <label>Disciplina</label>
                        <select name="disciplina" required>
                            <option value="PRT">Língua Portuguesa</option>
                            <!-- Others -->
                        </select>
                    </div>
                    <div class="gva-field">
                        <label>Assunto (Existente)</label>
                        <input type="text" name="assunto" placeholder="Ex: Modernismo">
                    </div>
                    <div class="gva-field full-width">
                        <label>Estilo da Questão / Detalhes Adicionais</label>
                        <textarea name="estilo_questao" rows="3" placeholder="Descreva o estilo, foco, ou cole um exemplo..."></textarea>
                        <button type="button" class="button button-small" id="btn-save-style">Salvar Estilo</button>
                    </div>
                </div>

                <h3>Detalhes do Texto (Opcional)</h3>
                <div class="gva-grid">
                    <div class="gva-field">
                        <label>Nº de Textos</label>
                        <input type="number" name="num_textos" value="1">
                    </div>
                    <div class="gva-field">
                        <label>Gênero</label>
                        <select name="genero">
                            <option value="">Nenhum</option>
                            <option value="Literário">Literário</option>
                            <!-- Add logic from user.js to populate subgenres -->
                        </select>
                    </div>
                    <div class="gva-field">
                        <label>Subgênero</label>
                        <input type="text" name="subgenero" placeholder="Ex: Romance">
                    </div>
                </div>

                <div class="gva-upload-area">
                    <label>Anexar Livro Base (PDF)</label>
                    <button type="button" class="button" id="upload_book_btn">Selecionar Livro</button>
                    <input type="hidden" name="book_url" id="book_url">
                </div>

                <div class="gva-toggle">
                    <label>Gerar Imagem?</label>
                    <input type="radio" name="com_imagem" value="1"> Sim
                    <input type="radio" name="com_imagem" value="0" checked> Não
                </div>

                <div class="gva-actions">
                    <label>Quantidade:</label>
                    <input type="number" name="quantidade" value="1" max="5">
                    <button type="submit" class="button button-primary button-hero">Gerar Similar</button>
                </div>
            </form>
            <div id="result-gerar" class="gva-log"></div>
        </div>

        <!-- SECTION: NOVOS ASSUNTOS -->
        <div id="novos-assuntos" class="gva-section">
            <h2>Novos Assuntos Criados pela IA</h2>
            <p>Abaixo estão os assuntos que não existiam na base e foram criados automaticamente.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Código Questão</th><th>Novo Assunto</th><th>Data</th></tr></thead>
                <tbody id="tbody-novos-assuntos">
                    <!-- Populated via JS/AJAX -->
                </tbody>
            </table>
        </div>

        <!-- SECTION: HISTÓRICO -->
        <div id="historico" class="gva-section">
            <h2>Histórico de Operações</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Data</th><th>Código</th><th>Tipo</th><th>Modelo IA</th><th>Imagem</th><th>Status</th></tr></thead>
                <tbody id="tbody-historico">
                    <!-- Populated via JS/AJAX -->
                </tbody>
            </table>
        </div>

        <!-- SECTION: CONFIG -->
        <div id="config" class="gva-section">
            <form method="post" action="options.php">
                <?php settings_fields('gva_options_group'); ?>
                <?php do_settings_sections('gva_options_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td><input type="password" name="gva_gemini_api_key" value="<?php echo esc_attr(get_option('gva_gemini_api_key')); ?>" class="regular-text"/></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

    </div>
</div>