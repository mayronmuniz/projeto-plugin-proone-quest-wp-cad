<?php
namespace GeminiVestibularAI\Admin;

class AdminPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']); // Registro adicionado
    }

    public function add_plugin_page() {
        add_menu_page(
            'Register Manager AI Proone', // Título atualizado
            'Gemini AI',
            'manage_options',
            'gemini-vestibular-ai',
            [$this, 'create_admin_page'],
            'dashicons-superhero',
            60
        );
    }

    public function register_settings() {
        register_setting('gva_options_group', 'gva_gemini_api_key');
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_gemini-vestibular-ai') {
            return;
        }

        wp_enqueue_style('gva-style', GVA_PLUGIN_URL . 'assets/css/gemini-style.css', [], GVA_VERSION);
        wp_enqueue_script('gva-script', GVA_PLUGIN_URL . 'assets/js/gemini-admin.js', ['jquery'], GVA_VERSION, true);
        
        wp_localize_script('gva-script', 'gva_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(GVA_NONCE)
        ]);
        
        wp_enqueue_media();
    }

    public function create_admin_page() {
        require_once GVA_PLUGIN_DIR . 'views/admin-main.php';
    }
}