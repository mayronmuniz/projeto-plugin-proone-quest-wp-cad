<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package GeminiVestibularAI
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Drop Custom Database Tables
$table_name = $wpdb->prefix . 'gva_history';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// 2. Delete Plugin Options
delete_option('gva_gemini_api_key');

// 3. Clean up temporary files (Security & Cleanup)
$upload_dir = wp_upload_dir();
$gva_dir = $upload_dir['basedir'] . '/gva_temp';

if (is_dir($gva_dir)) {
    $iterator = new RecursiveDirectoryIterator($gva_dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($gva_dir);
}