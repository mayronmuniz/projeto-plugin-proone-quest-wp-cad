<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ProOneAI
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Drop AI History Table
$table_name = $wpdb->prefix . 'p1ai_history';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// 2. Delete Plugin Options
delete_option('p1ai_groq_api_key');
delete_option('p1ai_new_subjects_log');

// 3. Clean up temporary files
$upload_dir = wp_upload_dir();
$p1ai_dir = $upload_dir['basedir'] . '/p1ai_temp';

if (is_dir($p1ai_dir)) {
    $iterator = new RecursiveDirectoryIterator($p1ai_dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($p1ai_dir);
}