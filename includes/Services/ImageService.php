<?php
namespace GeminiVestibularAI\Services;

class ImageService {

    /**
     * Downloads an image from a URL (or base64), converts to AVIF, and attaches to Media Library.
     */
    public function process_image($image_data, $post_id, $filename_base) {
        if (empty($image_data)) return false;

        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name($filename_base) . uniqid() . '.png'; // Salva temporariamente como PNG
        $file_path = $upload_dir['path'] . '/' . $filename;

        // Decode Base64 or Download URL
        if (strpos($image_data, 'data:image') === 0) {
            $data = explode(',', $image_data);
            file_put_contents($file_path, base64_decode($data[1]));
        } else {
            // Secure download using WP HTTP API
            $response = wp_remote_get($image_data, ['timeout' => 30]);
            if (is_wp_error($response)) return false;
            file_put_contents($file_path, wp_remote_retrieve_body($response));
        }

        // Convert to AVIF using WP Image Editor (if supported by server) or GD/Imagick fallback
        $avif_filename = str_replace('.png', '.avif', $filename);
        $avif_path = $upload_dir['path'] . '/' . $avif_filename;
        
        $editor = wp_get_image_editor($file_path);
        if (!is_wp_error($editor)) {
            // Tenta salvar como AVIF
            $result = $editor->save($avif_path, 'image/avif');
            
            // Se falhar (servidor não suporta avif), mantém o original mas renomeia a referencia
            if (is_wp_error($result)) {
                $final_path = $file_path;
                $final_filename = $filename;
                $mime_type = 'image/png';
            } else {
                unlink($file_path); // Remove o PNG temporário
                $final_path = $avif_path;
                $final_filename = $avif_filename;
                $mime_type = 'image/avif';
            }
        } else {
            return false;
        }

        // Insert into Media Library
        $attachment = [
            'guid'           => $upload_dir['url'] . '/' . $final_filename, 
            'post_mime_type' => $mime_type,
            'post_title'     => $filename_base,
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $final_path, $post_id);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $final_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }

        return false;
    }
}