<?php
namespace ProOneAI\Frontend;

class Shortcode {

    public function run() {
        add_shortcode('pqp_cadastro', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        // Segurança: Verificar permissões. 
        if (!current_user_can('manage_options')) {
            return '<p class="p1ai-error">Acesso negado: Você não tem permissão para acessar esta ferramenta de cadastro.</p>';
        }

        // Carregar Assets necessários apenas quando o shortcode é usado
        $this->enqueue_assets();

        // Buffering de saída para capturar o HTML da view existente
        ob_start();
        
        // Inclui a mesma view do admin para consistência
        require P1AI_PLUGIN_DIR . 'views/admin-main.php';
        
        return ob_get_clean();
    }

    private function enqueue_assets() {
        // Carrega o CSS
        wp_enqueue_style('p1ai-style', P1AI_PLUGIN_URL . 'assets/css/proone-style.css', [], P1AI_VERSION);
        
        // Carrega o JS
        wp_enqueue_script('p1ai-script', P1AI_PLUGIN_URL . 'assets/js/proone-admin.js', ['jquery'], P1AI_VERSION, true);
        
        // Passa as variáveis PHP para o JS
        wp_localize_script('p1ai-script', 'p1ai_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(P1AI_NONCE)
        ]);
        
        // Carrega scripts de mídia do WordPress para o upload de PDF
        if (!did_action('wp_enqueue_media')) {
            wp_enqueue_media();
        }
    }
}