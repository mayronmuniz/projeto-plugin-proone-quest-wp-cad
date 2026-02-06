<?php
/**
 * Plugin Name: Plataforma de Questões ProOne - Register-Manager
 * Plugin URI: https://proone.io
 * Description: Cadastramento e Geração de Questões de Vestibular usando Google Gemini. Suporte a PDF, AVIF e Taxonomias Dinâmicas.
 * Version: 1.1.0
 * Author: mayronmuniz.dev
 * Author URI: https://proone.io
 * Text Domain: gemini-vestibular-ai
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Constants
define('GVA_VERSION', '1.0.0');
define('GVA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GVA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GVA_NONCE', 'gva_secure_action');

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'GeminiVestibularAI\\';
    $base_dir = GVA_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation/Deactivation Hooks
register_activation_hook(__FILE__, ['GeminiVestibularAI\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['GeminiVestibularAI\\Core\\Deactivator', 'deactivate']);

// Initialize Plugin
function gva_init() {
    // Admin Interface
    if (is_admin()) {
        $admin = new \GeminiVestibularAI\Admin\AdminPage();
        $admin->run();
    }

    // AJAX Handling (Admin & Frontend if needed)
    $ajax = new \GeminiVestibularAI\Admin\AjaxHandler();
    $ajax->run();

    // Frontend Shortcode
    $shortcode = new \GeminiVestibularAI\Frontend\Shortcode();
    $shortcode->run();
}
add_action('plugins_loaded', 'gva_init');