<?php
namespace GeminiVestibularAI\Admin;

class AdminPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_plugin_page() {
        add_menu_page(
            'Gemini Vestibular AI',
            'Gemini AI',
            'manage_options',
            'gemini-vestibular-ai',
            [$this, 'create_admin_page'],
            'dashicons-superhero',
            60
        );
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
        
        // Enqueue Media Uploader scripts
        wp_enqueue_media();
    }

    public function create_admin_page() {
        // Fetch Subjects (Terms) to verify mapping
        $subjects = get_terms(['taxonomy' => 'assunto', 'hide_empty' => false]); // Ajuste 'assunto' para sua taxonomia real
        $institutions = get_terms(['taxonomy' => 'instituicao', 'hide_empty' => false]);
        
        require_once GVA_PLUGIN_DIR . 'views/admin-main.php';
    }
}