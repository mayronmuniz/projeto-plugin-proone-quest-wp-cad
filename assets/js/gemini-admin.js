jQuery(document).ready(function($) {
    
    // --- Lógica de Abas (Tabs) ---
    $('.gva-tab').click(function(e) {
        e.preventDefault();
        
        // Remove classe ativa de todas as abas e seções
        $('.gva-tab').removeClass('active');
        $('.gva-section').removeClass('active');
        
        // Adiciona classe ativa na aba clicada e na seção alvo
        $(this).addClass('active');
        $($(this).data('target')).addClass('active');
        
        // Se a aba clicada for Histórico ou Novos Assuntos, carrega os dados
        if($(this).data('target') === '#historico') {
            loadHistory();
        }
        // if($(this).data('target') === '#novos-assuntos') { loadNewSubjects(); }
    });

    // --- Upload de PDF (Prova) ---
    $('#upload_pdf_btn').click(function(e) {
        e.preventDefault();
        var pdfUploader = wp.media({ 
            title: 'Selecionar Prova (PDF)',
            button: { text: 'Usar este PDF' },
            multiple: false
        }).on('select', function() {
            var attachment = pdfUploader.state().get('selection').first().toJSON();
            $('#pdf_url').val(attachment.url);
            $('#pdf_filename').text(attachment.filename).css('color', '#0218fa');
        }).open();
    });

    // --- Upload de Livro (PDF) - Seção Gerar ---
    $('#upload_book_btn').click(function(e) {
        e.preventDefault();
        var bookUploader = wp.media({ 
            title: 'Selecionar Livro Base (PDF)',
            button: { text: 'Usar este Livro' },
            multiple: false
        }).on('select', function() {
            var attachment = bookUploader.state().get('selection').first().toJSON();
            $('#book_url').val(attachment.url);
            $(this).after('<span style="display:block;margin-top:5px;color:#0218fa">' + attachment.filename + '</span>');
        }).open();
    });

    // --- Salvar Estilo de Questão (LocalStorage simples para UX) ---
    $('#btn-save-style').click(function() {
        var style = $('textarea[name="estilo_questao"]').val();
        if(style) {
            // Em uma implementação real, salvaríamos via AJAX em user_meta ou options
            alert('Estilo salvo localmente (simulação).');
        }
    });

    // --- Submissão do Formulário: Cadastrar Oficial ---
    $('#form-cadastrar-oficial').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var log = $('#result-cadastrar');
        
        // Coleta de dados
        var data = {
            action: 'gva_process_request',
            nonce: gva_vars.nonce,
            type: 'oficial',
            params: {
                disciplina: form.find('select[name="disciplina"]').val(),
                instituicao: form.find('input[name="instituicao"]').val(),
                ano: form.find('input[name="ano"]').val(),
                versao: form.find('input[name="versao"]').val(),
                dia: form.find('select[name="dia"]').val(),
                numero_inicial: form.find('input[name="numero_inicial"]').val(),
                quantidade: form.find('input[name="quantidade"]').val(),
                pdf_url: $('#pdf_url').val(),
                ai_model: form.find('select[name="ai_model"]').val()
            }
        };

        // UI Feedback
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processando...');
        log.html('<div class="gva-loader">Conectando ao Gemini... Isso pode levar alguns segundos.</div>');

        // Requisição AJAX
        $.post(gva_vars.ajax_url, data, function(response) {
            btn.prop('disabled', false).text('Processar Questões');
            
            if(response.success) {
                var html = '<ul class="success-list">';
                if(Array.isArray(response.data)) {
                    response.data.forEach(function(q) {
                        html += '<li><strong>' + q.code + '</strong>: Cadastrada com sucesso. (ID: ' + q.id + ')</li>';
                    });
                } else {
                    html += '<li>' + response.data + '</li>';
                }
                html += '</ul>';
                log.html(html);
                
                // Opcional: Limpar formulário ou atualizar histórico
            } else {
                log.html('<div class="error"><strong>Erro:</strong> ' + (response.data || 'Erro desconhecido') + '</div>');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Processar Questões');
            log.html('<div class="error">Erro de conexão com o servidor.</div>');
        });
    });

    // --- Submissão do Formulário: Gerar Similar ---
    $('#form-gerar-similar').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var log = $('#result-gerar');

        var data = {
            action: 'gva_process_request',
            nonce: gva_vars.nonce,
            type: 'similar',
            params: {
                disciplina: form.find('select[name="disciplina"]').val(),
                assunto: form.find('input[name="assunto"]').val(),
                estilo_questao: form.find('textarea[name="estilo_questao"]').val(),
                num_textos: form.find('input[name="num_textos"]').val(),
                genero: form.find('select[name="genero"]').val(),
                subgenero: form.find('input[name="subgenero"]').val(),
                book_url: $('#book_url').val(), // URL do livro PDF
                com_imagem: form.find('input[name="com_imagem"]:checked').val(),
                quantidade: form.find('input[name="quantidade"]').val(),
                ai_model: 'gemini-2.0-flash' // Default ou adicionar select na UI
            }
        };

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Gerando...');
        log.html('<div class="gva-loader">Criando questões inéditas...</div>');

        $.post(gva_vars.ajax_url, data, function(response) {
            btn.prop('disabled', false).text('Gerar Similar');
            if(response.success) {
                var html = '<ul class="success-list">';
                response.data.forEach(function(q) {
                    html += '<li>Questão Similar <strong>' + q.code + '</strong> gerada!</li>';
                });
                html += '</ul>';
                log.html(html);
            } else {
                log.html('<div class="error">' + response.data + '</div>');
            }
        });
    });

    // Função Placeholder para carregar histórico (implementar endpoint AJAX 'gva_get_history')
    function loadHistory() {
        var tbody = $('#tbody-historico');
        if(tbody.children().length === 0) {
            tbody.html('<tr><td colspan="6">Carregando histórico...</td></tr>');
            // $.get(gva_vars.ajax_url, { action: 'gva_get_history', nonce: gva_vars.nonce }, function(res) { ... });
        }
    }

});