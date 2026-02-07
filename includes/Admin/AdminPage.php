<?php
namespace ProOneAI\Admin;

class AdminPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_plugin_page() {
        add_menu_page(
            'ProOne AI Manager',
            'ProOne AI',
            'manage_options',
            'proone-ai-manager',
            [$this, 'create_admin_page'],
            'dashicons-superhero',
            60
        );
    }

    public function register_settings() {
        register_setting('p1ai_options_group', 'p1ai_groq_api_key');
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_proone-ai-manager') {
            return;
        }

        wp_enqueue_style('p1ai-style', P1AI_PLUGIN_URL . 'assets/css/proone-style.css', [], P1AI_VERSION);
        wp_enqueue_script('p1ai-script', P1AI_PLUGIN_URL . 'assets/js/proone-admin.js', ['jquery'], P1AI_VERSION, true);
        
        wp_localize_script('p1ai-script', 'p1ai_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(P1AI_NONCE)
        ]);
        
        wp_enqueue_media();
    }

    public function create_admin_page() {
        require_once P1AI_PLUGIN_DIR . 'views/admin-main.php';
    }
}