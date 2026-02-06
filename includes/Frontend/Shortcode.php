<?php
namespace GeminiVestibularAI\Frontend;

class Shortcode {

    public function run() {
        add_shortcode('pqp_cadastro', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        // Segurança: Verificar permissões. 
        // Como o AjaxHandler verifica 'manage_options', o formulário só deve ser exibido para quem tem essa permissão.
        if (!current_user_can('manage_options')) {
            return '<p class="gva-error">Acesso negado: Você não tem permissão para acessar esta ferramenta de cadastro.</p>';
        }

        // Carregar Assets necessários apenas quando o shortcode é usado
        $this->enqueue_assets();

        // Buffering de saída para capturar o HTML da view existente
        ob_start();
        
        // Inclui a mesma view do admin para consistência
        // Nota: A view admin-main.php usa classes do WP Admin (.wrap), pode precisar de ajustes CSS no frontend dependendo do tema.
        require GVA_PLUGIN_DIR . 'views/admin-main.php';
        
        return ob_get_clean();
    }

    private function enqueue_assets() {
        // Carrega o CSS
        wp_enqueue_style('gva-style', GVA_PLUGIN_URL . 'assets/css/gemini-style.css', [], GVA_VERSION);
        
        // Carrega o JS (jQuery como dependência)
        wp_enqueue_script('gva-script', GVA_PLUGIN_URL . 'assets/js/gemini-admin.js', ['jquery'], GVA_VERSION, true);
        
        // Passa as variáveis PHP para o JS (Nonce e URL Ajax)
        wp_localize_script('gva-script', 'gva_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(GVA_NONCE)
        ]);
        
        // Importante: Carrega scripts de mídia do WordPress para o upload de PDF funcionar no Frontend
        if (!did_action('wp_enqueue_media')) {
            wp_enqueue_media();
        }
    }
}