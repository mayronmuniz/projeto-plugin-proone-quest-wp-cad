jQuery(document).ready(function($) {
    
    // Configurações Globais
    const ajaxUrl = p1ai_vars.ajax_url;
    const nonce = p1ai_vars.nonce;

    // --- Lógica de Abas ---
    $('.p1ai-tab').click(function(e) {
        e.preventDefault();
        $('.p1ai-tab').removeClass('active');
        $('.p1ai-section').removeClass('active');
        $(this).addClass('active');
        $($(this).data('target')).addClass('active');
        
        if($(this).data('target') === '#historico') loadHistory();
        if($(this).data('target') === '#novos-assuntos') loadNewSubjects();
    });

    // --- Toggle Texto Adicional ---
    $('input[name="com_texto"]').change(function() {
        if($(this).val() === '1') {
            $('#detalhes-texto-container').slideDown();
        } else {
            $('#detalhes-texto-container').slideUp();
        }
    });

    // --- Toggle Imagem e Seletor de Modelo ---
    $('input[name="com_imagem"]').change(function() {
        if($(this).val() === '1') {
            $('#image-model-selector').slideDown();
        } else {
            $('#image-model-selector').slideUp();
        }
    });

    // --- Lógica de Dropdowns UI ---
    $(document).on('click', '.pqp-singleselect-btn, .pqp-creatable-select-btn', function(e) {
        e.stopPropagation();
        var targetId = $(this).data('target');
        $('#' + targetId).toggle();
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
            container.find('input[type="hidden"]').val(term).trigger('change');
            container.find('.pqp-creatable-select-btn').text(term);
            $(this).closest('.pqp-creatable-select-dropdown').hide();
        }
    });

    $(document).on('click', '.pqp-creatable-select-options a', function(e) {
        e.preventDefault();
        var value = $(this).data('value');
        var container = $(this).closest('.pqp-creatable-select');
        container.find('input[type="hidden"]').val(value).trigger('change');
        container.find('.pqp-creatable-select-btn').text(value);
        $(this).closest('.pqp-creatable-select-dropdown').hide();
    });

    // --- População Dinâmica de Dropdowns ---
    function populateDropdown(action, targetId, extraParams = {}) {
        var params = { action: 'p1ai_' + action, nonce: nonce }; 
        $.extend(params, extraParams);
        
        $.get(ajaxUrl, params, function(response) {
            if(response.success) {
                var html = '';
                response.data.forEach(function(item) {
                    html += '<a href="#" data-value="' + item.name + '">' + item.name + '</a>';
                });
                $('#' + targetId + ' .pqp-creatable-select-options, #' + targetId + '.pqp-singleselect-dropdown').html(html);
            }
        });
    }

    // Carregamento Inicial
    populateDropdown('get_institutions', 'cad_inst_dropdown');
    populateDropdown('get_years', 'cad_ano_dropdown');
    populateDropdown('get_institutions', 'ger_inst_dropdown');
    populateDropdown('get_years', 'ger_ano_dropdown');
    populateDropdown('get_authors', 'ger_autor_dropdown'); 
    populateDropdown('get_books', 'ger_livro_dropdown'); 

    // Dependências
    $('#cad_disciplina, #ger_disciplina').change(function() {
        var disc = $(this).val();
        var prefix = $(this).attr('id').split('_')[0]; 
        if(disc) {
            populateDropdown('get_subjects', prefix + '_assunto_dropdown', { discipline: disc });
        }
    });

    $('#cad_instituicao, #ger_instituicao').on('change', function() {
        var inst = $(this).val();
        var prefix = $(this).attr('id').split('_')[0];
        if(inst) {
             $('#' + prefix + '_versao_btn').text('Selecione...');
             populateDropdown('get_versions_by_inst', prefix + '_versao_dropdown', { institution: inst });
        }
    });

    $('#ger_genero').change(function() {
        var genre = $(this).val();
        if(genre) {
            $('#ger_subgenero_btn').text('Selecione...');
            populateDropdown('get_subgenres_by_genre', 'ger_subgenero_dropdown', { genre: genre });
        }
    });

    // --- Upload PDF e Livro ---
    $('#upload_pdf_btn').click(function(e) {
        e.preventDefault();
        var pdfUploader = wp.media({ 
            title: 'Selecionar Prova (PDF)',
            button: { text: 'Usar este PDF' },
            multiple: false
        }).on('select', function() {
            var attachment = pdfUploader.state().get('selection').first().toJSON();
            $('#pdf_url').val(attachment.url);
            $('#pdf_filename').text(attachment.filename);
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

    // --- Cadastrar Questão (Oficial via PDF) ---
    $('#form-cadastrar-oficial').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var log = $('#result-cadastrar');

        var data = {
            action: 'p1ai_process_request',
            nonce: nonce,
            type: 'oficial',
            params: {
                disciplina: form.find('input[name="disciplina"]').val(),
                nivel_ensino: form.find('input[name="nivel_ensino"]').val(),
                instituicao: form.find('input[name="instituicao"]').val(),
                ano: form.find('input[name="ano"]').val(),
                versao: form.find('input[name="versao"]').val(),
                dia: form.find('input[name="dia"]').val(),
                instrucoes: form.find('textarea[name="instrucoes"]').val(),
                pdf_url: form.find('#pdf_url').val(),
                tipo_questao_radio: form.find('input[name="tipo_questao_radio"]').val(),
                avaliacao: form.find('select[name="avaliacao"]').val(), // NOVO CAMPO AVALIAÇÃO
                ai_model: form.find('select[name="ai_model"]').val(),
                temperature: form.find('input[name="temperature"]').val()
            }
        };

        btn.prop('disabled', true).html('Processando PDF...');
        log.html('<div class="p1ai-loader">Analisando documento...</div>');

        $.post(ajaxUrl, data, function(response) {
            btn.prop('disabled', false).text('Cadastrar');
            if(response.success) {
                var html = '<ul>';
                response.data.forEach(function(q) {
                    html += '<li>Questão <strong>' + q.code + '</strong> cadastrada com sucesso!</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory();
            } else {
                log.html('<div class="error">' + (response.data || 'Erro') + '</div>');
            }
        }).fail(function() {
             btn.prop('disabled', false).text('Cadastrar');
             log.html('<div class="error">Erro de conexão.</div>');
        });
    });

    // --- Gerar Questão Inédita ---
    $('#form-gerar-similar').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        var log = $('#result-gerar');

        var data = {
            action: 'p1ai_process_request',
            nonce: nonce,
            type: 'similar',
            params: {
                disciplina: form.find('input[name="disciplina"]').val(),
                nivel_ensino: form.find('input[name="nivel_ensino"]').val(),
                assunto: form.find('input[name="assunto"]').val(),
                instituicao: form.find('input[name="instituicao"]').val(),
                estilo_questao: form.find('textarea[name="estilo_questao"]').val(),
                ano: form.find('input[name="ano"]').val(),
                versao: form.find('input[name="versao"]').val(),
                nivel_dificuldade: form.find('input[name="nivel_dificuldade"]').val(),
                tipo_questao_radio: form.find('input[name="tipo_questao_radio"]').val(),
                avaliacao: form.find('select[name="avaliacao"]').val(), // NOVO CAMPO AVALIAÇÃO
                
                // Detalhes de Texto
                com_texto: form.find('input[name="com_texto"]:checked').val(),
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

                // Imagem e Modelos
                com_imagem: form.find('input[name="com_imagem"]:checked').val(),
                image_model: form.find('select[name="image_model"]').val(), 
                quantidade: form.find('input[name="quantidade"]').val(),
                ai_model: form.find('select[name="ai_model"]').val(),
                temperature: form.find('input[name="temperature"]').val()
            }
        };

        btn.prop('disabled', true).html('Processando...');
        log.html('<div class="p1ai-loader">Conectando ao modelo...</div>');

        $.post(ajaxUrl, data, function(response) {
            btn.prop('disabled', false).text('Gerar');
            if(response.success) {
                var html = '<ul>';
                response.data.forEach(function(q) {
                    html += '<li>Questão <strong>' + q.code + '</strong> criada! Imagem: ' + q.img_status + '</li>';
                });
                html += '</ul>';
                log.html(html);
                loadHistory();
            } else {
                log.html('<div class="error">' + (response.data || 'Erro') + '</div>');
            }
        }).fail(function() {
             btn.prop('disabled', false).text('Gerar');
             log.html('<div class="error">Erro de conexão. Verifique se a IA está respondendo.</div>');
        });
    });

    // --- Salvar Configurações ---
    $('#form-config').submit(function(e) {
        e.preventDefault();
        $.post(ajaxUrl, {
            action: 'p1ai_save_settings',
            nonce: nonce,
            groq_key: $('#p1ai_groq_api_key').val(),
            hf_key: $('#p1ai_huggingface_api_key').val() 
        }, function(res) {
            alert(res.success ? 'Salvo!' : 'Erro: ' + res.data);
        });
    });

    function loadHistory() {
        $.get(ajaxUrl, { action: 'p1ai_get_history', nonce: nonce }, function(res) {
            if(res.success) {
                var rows = '';
                res.data.forEach(function(h) {
                    rows += '<tr><td>'+h.time+'</td><td>'+h.question_code+'</td><td>'+h.type+'</td><td>'+h.ai_model+'</td><td>'+(h.has_image == 1 ? 'Sim' : 'Não')+'</td><td>'+h.status+'</td></tr>';
                });
                $('#tbody-historico').html(rows);
            }
        });
    }

    function loadNewSubjects() {
        $.get(ajaxUrl, { action: 'p1ai_get_new_subjects', nonce: nonce }, function(res) {
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