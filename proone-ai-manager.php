<?php
/**
 * Plugin Name: Plataforma de Questões ProOne - Register Manager
 * Plugin URI: https://proone.io
 * Description: Cadastramento e Geração de Questões usando Groq Cloud e Pollinations.ai. Suporte Híbrido Multimodal com metadados literários completos.
 * Version: 2.1.0
 * Author: mayronmuniz.dev
 * Author URI: https://proone.io
 * Text Domain: proone-ai-manager
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Constants
define('P1AI_VERSION', '2.1.0');
define('P1AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('P1AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('P1AI_NONCE', 'p1ai_secure_action');

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ProOneAI\\';
    $base_dir = P1AI_PLUGIN_DIR . 'includes/';

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
register_activation_hook(__FILE__, ['ProOneAI\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['ProOneAI\\Core\\Deactivator', 'deactivate']);

// Initialize Plugin
function p1ai_init() {
    // Admin Interface
    if (is_admin()) {
        $admin = new \ProOneAI\Admin\AdminPage();
        $admin->run();
    }

    // AJAX Handling
    $ajax = new \ProOneAI\Admin\AjaxHandler();
    $ajax->run();

    // Frontend Shortcode
    $shortcode = new \ProOneAI\Frontend\Shortcode();
    $shortcode->run();
}
add_action('plugins_loaded', 'p1ai_init');