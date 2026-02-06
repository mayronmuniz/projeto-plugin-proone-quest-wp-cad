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

    // --- Toggle Texto Adicional (Solicitação 14) ---
    $('input[name="com_texto"]').change(function() {
        if($(this).val() === '1') {
            $('#detalhes-texto-container').slideDown();
        } else {
            $('#detalhes-texto-container').slideUp();
        }
    });

    // --- Lógica de Dropdowns ---
    $(document).on('click', '.pqp-singleselect-btn, .pqp-creatable-select-btn', function(e) {
        e.stopPropagation();
        var targetId = $(this).data('target');
        var dropdown = $('#' + targetId);
        $('.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown').not(dropdown).hide();
        dropdown.toggle();
    });

    $(document).click(function() {
        $('.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown').hide();
    });

    $(document).on('click', '.pqp-singleselect-dropdown, .pqp-creatable-select-dropdown', function(e) {
        e.stopPropagation();
    });

    $(document).on('click', '.pqp-singleselect-dropdown a', function(e) {
        e.preventDefault();
        var value = $(this).data('value');
        var text = $(this).text();
        var container = $(this).closest('.pqp-singleselect');
        
        container.find('input[type="hidden"]').val(value).trigger('change');
        container.find('.pqp-singleselect-btn').text(text);
        $(this).closest('.pqp-singleselect-dropdown').hide();
        $(this).siblings().removeClass('selected');
        $(this).addClass('selected');
    });

    // Filtro e Input em Creatable
    $(document).on('keyup', '.pqp-creatable-select-search', function(e) {
        var term = $(this).val().toLowerCase();
        var container = $(this).closest('.pqp-creatable-select');
        var optionsDiv = $(this).siblings('.pqp-creatable-select-options');
        
        optionsDiv.find('a').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(term) > -1);
        });

        if(e.key === 'Enter' && term.length > 0) {
            container.find('input[type="hidden"]').val(term).trigger('change'); // Trigger change
            container.find('.pqp-creatable-select-btn').text(term);
            $(this).closest('.pqp-creatable-select-dropdown').hide();
        }
    });

    $(document).on('click', '.pqp-creatable-select-options a', function(e) {
        e.preventDefault();
        var value = $(this).data('value');
        var container = $(this).closest('.pqp-creatable-select');
        container.find('input[type="hidden"]').val(value).trigger('change'); // Trigger change
        container.find('.pqp-creatable-select-btn').text(value);
        $(this).closest('.pqp-creatable-select-dropdown').hide();
    });

    // --- População Dinâmica de Dropdowns ---
    function populateDropdown(action, targetId, extraParams = {}) {
        var params = { action: action, nonce: gva_vars.nonce };
        $.extend(params, extraParams);
        
        $.get(gva_vars.ajax_url, params, function(response) {
            if(response.success) {
                var html = '';
                response.data.forEach(function(item) {
                    html += '<a href="#" data-value="' + item.name + '">' + item.name + '</a>';
                });
                $('#' + targetId + ' .pqp-creatable-select-options, #' + targetId + '.pqp-singleselect-dropdown').html(html); // Suporte a ambos
            }
        });
    }

    // Carregar iniciais
    populateDropdown('gva_get_institutions', 'cad_inst_dropdown');
    populateDropdown('gva_get_years', 'cad_ano_dropdown');
    
    // Gerar
    populateDropdown('gva_get_institutions', 'ger_inst_dropdown');
    populateDropdown('gva_get_years', 'ger_ano_dropdown');
    populateDropdown('gva_get_authors', 'ger_autor_dropdown');
    populateDropdown('gva_get_books', 'ger_livro_dropdown');

    // Dependência Disciplina -> Assunto
    $('#cad_disciplina, #ger_disciplina').change(function() {
        var disc = $(this).val();
        var prefix = $(this).attr('id').split('_')[0]; // cad ou ger
        if(disc) {
            populateDropdown('gva_get_subjects', prefix + '_assunto_dropdown', { discipline: disc });
        }
    });

    // Dependência Instituição -> Versão (Solicitação 04 e 05)
    $('#cad_instituicao, #ger_instituicao').on('change', function() {
        var inst = $(this).val();
        var prefix = $(this).attr('id').split('_')[0];
        if(inst) {
             $('#' + prefix + '_versao_btn').text('Selecione...');
             populateDropdown('gva_get_versions_by_inst', prefix + '_versao_dropdown', { institution: inst });
        }
    });

    // Dependência Gênero -> Subgênero (Solicitação 05 Gerar)
    $('#ger_genero').change(function() {
        var genre = $(this).val();
        if(genre) {
            $('#ger_subgenero_btn').text('Selecione...');
            populateDropdown('gva_get_subgenres_by_genre', 'ger_subgenero_dropdown', { genre: genre });
        }
    });

    // --- Uploaders ---
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
            btn.prop('disabled', false).text('Cadastrar');
            if(response.success) {
                var html = '<ul class="success-list">';
                response.data.forEach(function(q) {
                    html += '<li><strong>' + q.code + '</strong>: Cadastrada com sucesso. (ID: ' + q.id + ')</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory(); 
                loadNewSubjects();
            } else {
                log.html('<div class="error"><strong>Erro:</strong> ' + (response.data || 'Erro desconhecido') + '</div>');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Cadastrar');
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
                estilo_questao: form.find('input[name="estilo_questao"]').val(), // Novo campo
                ano: form.find('input[name="ano"]').val(),
                versao: form.find('input[name="versao"]').val(),
                nivel_dificuldade: form.find('input[name="nivel_dificuldade"]').val(),
                com_texto: form.find('input[name="com_texto"]:checked').val(), // Verifica se deve criar texto
                
                // Detalhes de Texto (serão ignorados no PHP se com_texto for 0)
                titulo_texto: form.find('input[name="titulo_texto"]').val(),
                tipo_texto: form.find('input[name="tipo_texto"]').val(),
                genero: form.find('input[name="genero"]').val(),
                subgenero: form.find('input[name="subgenero"]').val(),
                autor: form.find('input[name="autor"]').val(),
                livro: form.find('input[name="livro"]').val(),
                nacionalidade: form.find('input[name="nacionalidade"]').val(),
                forma_texto: form.find('input[name="forma_texto"]').val(),
                periodo: form.find('input[name="periodo"]').val(),
                tipologia: form.find('input[name="tipologia"]').val(),
                
                book_url: $('#book_url').val(),
                com_imagem: form.find('input[name="com_imagem"]:checked').val(),
                quantidade: form.find('input[name="quantidade"]').val(),
                ai_model: form.find('select[name="ai_model"]').val()
            }
        };

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Gerando...');
        log.html('<div class="gva-loader">Criando questões inéditas...</div>');

        $.post(gva_vars.ajax_url, data, function(response) {
            btn.prop('disabled', false).text('Gerar');
            if(response.success) {
                var html = '<ul class="success-list">';
                response.data.forEach(function(q) {
                    // Correção ID e Código
                    html += '<li>Questão Inédita <strong>' + q.code + '</strong> gerada! (ID: ' + q.id + ')</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory();
            } else {
                log.html('<div class="error">' + (response.data || 'Erro desconhecido') + '</div>');
            }
        }).fail(function() {
             btn.prop('disabled', false).text('Gerar');
             log.html('<div class="error">Erro de conexão.</div>');
        });
    });

    // --- Salvar Configurações (AJAX) ---
    $('#form-config').submit(function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        var feedback = $('#config-feedback');
        var apiKey = $('#gva_gemini_api_key').val();

        btn.prop('disabled', true).text('Salvando...');
        feedback.text('').removeClass('success error');

        $.post(gva_vars.ajax_url, {
            action: 'gva_save_settings',
            nonce: gva_vars.nonce,
            api_key: apiKey
        }, function(response) {
            btn.prop('disabled', false).text('Salvar Configurações');
            
            if(response.success) {
                feedback.text(response.data).css('color', 'green');
                setTimeout(function() { feedback.text(''); }, 3000);
            } else {
                feedback.text('Erro: ' + response.data).css('color', 'red');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Salvar Configurações');
            feedback.text('Erro de conexão.').css('color', 'red');
        });
    });

    function loadHistory() {
        $.get(gva_vars.ajax_url, { action: 'gva_get_history', nonce: gva_vars.nonce }, function(res) {
            if(res.success) {
                var rows = '';
                res.data.forEach(function(h) {
                    var imgStatus = h.has_image == '1' ? '<span style="color:red; font-weight:bold;">Manual</span>' : 'Não';
                    if(h.type === 'similar' && h.has_image == '1') imgStatus = '<span style="color:green;">Gerada</span>';
                    
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