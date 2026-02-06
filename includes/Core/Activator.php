<?php
namespace GeminiVestibularAI\Core;

class Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gva_history';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL para criar a tabela de histórico de gerações
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            question_code varchar(50) NOT NULL,
            type varchar(20) NOT NULL, -- oficial ou similar
            ai_model varchar(50) NOT NULL,
            has_image boolean DEFAULT 0,
            status varchar(20) DEFAULT 'success',
            details text,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Garante que o diretório temporário exista e seja seguro
        $upload_dir = wp_upload_dir();
        $gva_dir = $upload_dir['basedir'] . '/gva_temp';
        if (!file_exists($gva_dir)) {
            mkdir($gva_dir, 0755, true);
            // Cria arquivo index.php vazio para evitar listagem de diretório
            file_put_contents($gva_dir . '/index.php', '<?php // Silence is golden');
            // Cria .htaccess para negar acesso direto via navegador (Apache)
            file_put_contents($gva_dir . '/.htaccess', 'deny from all');
        }
    }
}