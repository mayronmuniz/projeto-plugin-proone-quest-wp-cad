<?php
namespace GeminiVestibularAI\Core;

class Deactivator {
    public static function deactivate() {
        // Limpa regras de reescrita se houver Custom Post Types
        flush_rewrite_rules();
        
        // Remove arquivos temporários de extração de PDF se existirem
        $upload_dir = wp_upload_dir();
        $gva_dir = $upload_dir['basedir'] . '/gva_temp';
        
        if (is_dir($gva_dir)) {
            // Lógica simples de limpeza (opcional)
            // array_map('unlink', glob("$gva_dir/*.*"));
            // rmdir($gva_dir);
        }
    }
}