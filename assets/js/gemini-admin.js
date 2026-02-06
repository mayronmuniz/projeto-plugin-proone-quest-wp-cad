jQuery(document).ready(function($) {
    
    // --- Lógica de Abas ---
    $('.gva-tab').click(function(e) {
        e.preventDefault();
        $('.gva-tab').removeClass('active');
        $('.gva-section').removeClass('active');
        $(this).addClass('active');
        $($(this).data('target')).addClass('active');
        
        if($(this).data('target') === '#historico') loadHistory();
        if($(this).data('target') === '#novos-assuntos') loadNewSubjects();
    });

    // --- Lógica de Dropdowns Personalizados (Single e Creatable) ---
    
    // Toggle dropdown
    $(document).on('click', '.pqp-singleselect-btn, .pqp-creatable-select-btn', function(e) {
        e.stopPropagation();
        var targetId = $(this).data('target');
        var dropdown = $('#' + targetId);
        $('.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown').not(dropdown).hide(); // Fecha outros
        dropdown.toggle();
    });

    // Fechar ao clicar fora
    $(document).click(function() {
        $('.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown').hide();
    });

    // Impedir fechamento ao clicar dentro do dropdown
    $(document).on('click', '.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown', function(e) {
        e.stopPropagation();
    });

    // Seleção Single
    $(document).on('click', '.pqp-singleselect-dropdown a', function(e) {
        e.preventDefault();
        var value = $(this).data('value');
        var text = $(this).text();
        var container = $(this).closest('.pqp-singleselect');
        
        container.find('input[type="hidden"]').val(value).trigger('change');
        container.find('.pqp-singleselect-btn').text(text);
        $(this).closest('.pqp-singleselect-dropdown').hide();
        
        // Remove 'selected' class from siblings and add to current
        $(this).siblings().removeClass('selected');
        $(this).addClass('selected');
    });

    // Seleção Creatable (Lógica simplificada: busca via AJAX se necessário ou popula local)
    // Para simplificar neste addon, vamos assumir que as opções já estão ou serão carregadas
    // Se for um novo valor digitado:
    $(document).on('keyup', '.pqp-creatable-select-search', function(e) {
        var term = $(this).val().toLowerCase();
        var optionsDiv = $(this).siblings('.pqp-creatable-select-options');
        var container = $(this).closest('.pqp-creatable-select');
        
        // Filtra opções existentes (se houver items carregados)
        // Se pressionar Enter, cria novo
        if(e.key === 'Enter' && term.length > 0) {
            // Define o valor como o termo digitado
            container.find('input[type="hidden"]').val(term);
            container.find('.pqp-creatable-select-btn').text(term);
            $(this).closest('.pqp-creatable-select-dropdown').hide();
        }
    });

    // Carregar opções dinâmicas para Creatable Selects (Instituição, Ano, etc.)
    // Função auxiliar para popular dropdowns via AJAX
    function populateDropdown(action, targetId) {
        $.get(gva_vars.ajax_url, { action: action, nonce: gva_vars.nonce }, function(response) {
            if(response.success) {
                var html = '';
                response.data.forEach(function(item) {
                    html += '<a href="#" data-value="' + item.name + '">' + item.name + '</a>';
                });
                $('#' + targetId + ' .pqp-creatable-select-options').html(html);
            }
        });
    }

    // Inicializar dropdowns dinâmicos
    populateDropdown('gva_get_institutions', 'cad_inst_dropdown');
    populateDropdown('gva_get_years', 'cad_ano_dropdown');
    populateDropdown('gva_get_versions', 'cad_versao_dropdown');
    populateDropdown('gva_get_subjects', 'ger_assunto_dropdown'); // Exemplo
    populateDropdown('gva_get_authors', 'ger_autor_dropdown');
    populateDropdown('gva_get_books', 'ger_livro_dropdown');
    
    // Reutiliza para o form de Gerar
    populateDropdown('gva_get_institutions', 'ger_inst_dropdown');
    populateDropdown('gva_get_years', 'ger_ano_dropdown');


    // Lógica de clique em opção creatable
    $(document).on('click', '.pqp-creatable-select-options a', function(e) {
        e.preventDefault();
        var value = $(this).data('value');
        var container = $(this).closest('.pqp-creatable-select');
        container.find('input[type="hidden"]').val(value);
        container.find('.pqp-creatable-select-btn').text(value);
        $(this).closest('.pqp-creatable-select-dropdown').hide();
    });


    // --- Uploaders WP Media ---
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

    $('#upload_book_btn').click(function(e) {
        e.preventDefault();
        var bookUploader = wp.media({ 
            title: 'Selecionar Livro Base (PDF)',
            button: { text: 'Usar este Livro' },
            multiple: false
        }).on('select', function() {
            var attachment = bookUploader.state().get('selection').first().toJSON();
            $('#book_url').val(attachment.url);
            $('#book_filename').text(attachment.filename).css('color', '#0218fa');
        }).open();
    });

    // --- Submissão Cadastrar (Oficial) ---
    $('#form-cadastrar-oficial').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var log = $('#result-cadastrar');
        
        var data = {
            action: 'gva_process_request',
            nonce: gva_vars.nonce,
            type: 'oficial',
            params: {
                disciplina: form.find('input[name="disciplina"]').val(),
                instituicao: form.find('input[name="instituicao"]').val(),
                ano: form.find('input[name="ano"]').val(),
                versao: form.find('input[name="versao"]').val(),
                dia: form.find('input[name="dia"]').val(),
                nivel_dificuldade: form.find('input[name="nivel_dificuldade"]').val(),
                instrucoes: form.find('textarea[name="instrucoes"]').val(),
                pdf_url: $('#pdf_url').val(),
                ai_model: form.find('select[name="ai_model"]').val()
            }
        };

        if(!data.params.pdf_url) {
            alert('Por favor, anexe o PDF da prova.');
            return;
        }

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processando PDF com IA...');
        log.html('<div class="gva-loader">Lendo PDF e identificando questões...</div>');

        $.post(gva_vars.ajax_url, data, function(response) {
            btn.prop('disabled', false).text('Processar Questões');
            if(response.success) {
                var html = '<ul class="success-list">';
                response.data.forEach(function(q) {
                    html += '<li><strong>' + q.code + '</strong>: Cadastrada com sucesso. (ID: ' + q.id + ')</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory(); // Atualiza histórico
                loadNewSubjects();
            } else {
                log.html('<div class="error"><strong>Erro:</strong> ' + (response.data || 'Erro desconhecido') + '</div>');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Processar Questões');
            log.html('<div class="error">Erro de conexão. Tente novamente.</div>');
        });
    });

    // --- Submissão Gerar (Similar) ---
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
                disciplina: form.find('input[name="disciplina"]').val(),
                assunto: form.find('input[name="assunto"]').val(),
                instituicao: form.find('input[name="instituicao"]').val(),
                ano: form.find('input[name="ano"]').val(),
                nivel_dificuldade: form.find('input[name="nivel_dificuldade"]').val(),
                
                // Detalhes de Texto
                tipo_texto: form.find('input[name="tipo_texto"]').val(),
                genero: form.find('input[name="genero"]').val(),
                autor: form.find('input[name="autor"]').val(),
                livro: form.find('input[name="livro"]').val(),
                
                book_url: $('#book_url').val(),
                com_imagem: form.find('input[name="com_imagem"]:checked').val(),
                quantidade: form.find('input[name="quantidade"]').val()
            }
        };

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Gerando...');
        log.html('<div class="gva-loader">Criando questões inéditas...</div>');

        $.post(gva_vars.ajax_url, data, function(response) {
            btn.prop('disabled', false).text('Gerar Questão');
            if(response.success) {
                var html = '<ul class="success-list">';
                response.data.forEach(function(q) {
                    html += '<li>Questão Inédita <strong>' + q.code + '</strong> gerada!</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory();
            } else {
                log.html('<div class="error">' + response.data + '</div>');
            }
        });
    });

    function loadHistory() {
        $.get(gva_vars.ajax_url, { action: 'gva_get_history', nonce: gva_vars.nonce }, function(res) {
            if(res.success) {
                var rows = '';
                res.data.forEach(function(h) {
                    var imgStatus = h.has_image == '1' ? '<span style="color:red; font-weight:bold;">Sim (Manual)</span>' : 'Não';
                    if(h.type === 'similar' && h.has_image == '1') imgStatus = '<span style="color:green;">Gerada (Avif)</span>';
                    
                    rows += '<tr><td>'+h.time+'</td><td>'+h.question_code+'</td><td>'+h.type+'</td><td>'+h.ai_model+'</td><td>'+imgStatus+'</td><td>'+h.status+'</td></tr>';
                });
                $('#tbody-historico').html(rows);
            }
        });
    }

    function loadNewSubjects() {
        $.get(gva_vars.ajax_url, { action: 'gva_get_new_subjects', nonce: gva_vars.nonce }, function(res) {
            if(res.success) {
                var rows = '';
                res.data.forEach(function(s) {
                    rows += '<tr><td>'+s.code+'</td><td>'+s.subject+'</td><td>'+s.date+'</td></tr>';
                });
                $('#tbody-novos-assuntos').html(rows);
            }
        });
    }
});