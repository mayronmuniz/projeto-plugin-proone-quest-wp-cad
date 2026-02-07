<?php
namespace ProOneAI\Services;

class ImageService {

    /**
     * Processa uma imagem, salva na Biblioteca de Mídia e anexa ao Post.
     *
     * @param string $image_url URL da imagem (pode ser remota ou local gerada pelo AIService).
     * @param int    $post_id   ID do post (Questão) para anexar a imagem.
     * @param string $desc      Descrição/Título da imagem.
     * @return int|false        ID do anexo (attachment_id) ou false em caso de erro.
     */
    public function process_image($image_url, $post_id, $desc = '') {
        // 1. Carrega dependências do WP necessárias
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        // 2. Valida URL
        if (empty($image_url)) {
            error_log('P1AI ImageService: URL da imagem vazia.');
            return false;
        }

        $temp_file = '';
        $upload_dir = wp_upload_dir();

        // 3. CORREÇÃO DE LOOPBACK: Verifica se é um arquivo local gerado pelo AIService
        // Se a URL contiver o domínio base do site, tentamos copiar direto do disco
        if (strpos($image_url, $upload_dir['baseurl']) !== false) {
            // Converte URL em Caminho Absoluto (Path)
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
            
            // Corrige separadores de diretório para Windows/Linux
            $local_path = wp_normalize_path($local_path);

            if (file_exists($local_path)) {
                // Cria um arquivo temporário do WP seguro para manipulação
                $temp_file = wp_tempnam($image_url);
                if ($temp_file) {
                    // Copia o conteúdo do arquivo gerado para o temp
                    copy($local_path, $temp_file);
                }
            }
        }

        // 4. Se não for local ou falhou a cópia local, tenta download via HTTP (método padrão)
        if (empty($temp_file)) {
            $temp_file = download_url( $image_url );
        }

        if ( is_wp_error( $temp_file ) ) {
            error_log('P1AI ImageService: Erro ao obter imagem (Download/Cópia) - ' . $temp_file->get_error_message());
            return false;
        }

        // 5. Prepara o array de arquivo simulando um upload $_FILES
        $file_array = array(
            'name'     => basename( $image_url ),
            'tmp_name' => $temp_file
        );

        // Se o nome do arquivo não tiver extensão, força .jpg
        if (strpos($file_array['name'], '.') === false) {
            $file_array['name'] .= '.jpg';
        }

        // 6. Usa media_handle_sideload para mover para uploads/, criar anexo e gerar metadados
        $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );

        // 7. Verifica erros
        if ( is_wp_error( $attachment_id ) ) {
            // Remove o arquivo temporário em caso de erro
            @unlink( $file_array['tmp_name'] );
            error_log('P1AI ImageService: Erro no Sideload - ' . $attachment_id->get_error_message());
            return false;
        }

        return $attachment_id;
    }
}