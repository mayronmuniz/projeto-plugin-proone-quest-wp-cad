<?php
namespace ProOneAI\Core;

class Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'p1ai_history';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL para criar a tabela de histórico
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            question_code varchar(50) NOT NULL,
            type varchar(20) NOT NULL,
            ai_model varchar(50) NOT NULL,
            has_image boolean DEFAULT 0,
            status varchar(20) DEFAULT 'success',
            details text,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Garante diretório temporário
        $upload_dir = wp_upload_dir();
        $p1ai_dir = $upload_dir['basedir'] . '/p1ai_temp';
        if (!file_exists($p1ai_dir)) {
            mkdir($p1ai_dir, 0755, true);
            file_put_contents($p1ai_dir . '/index.php', '<?php // Silence');
            file_put_contents($p1ai_dir . '/.htaccess', 'deny from all');
        }
    }
}