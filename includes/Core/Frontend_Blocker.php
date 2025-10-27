<?php
namespace FlexMile\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blokada dostępu do frontendu WordPressa
 * WordPress działa tylko jako headless CMS z REST API
 */
class Frontend_Blocker {

    public function __construct() {
        add_action('template_redirect', [$this, 'block_frontend'], 1);
        add_action('wp_head', [$this, 'remove_frontend_assets'], 1);
        
        // Usuń niepotrzebne generatory i linki
        $this->clean_wp_head();
    }

    /**
     * Blokuje dostęp do wszystkich stron frontendowych
     */
    public function block_frontend() {
        // Wyjątki: panel admina, REST API, wp-login, wp-cron
        if (is_admin() || 
            $this->is_rest_api_request() || 
            $this->is_login_page() ||
            (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        // Zwróć odpowiedź JSON dla headless
        wp_send_json([
            'error' => 'Frontend disabled',
            'message' => 'This is a headless WordPress installation. Please use the REST API.',
            'api_url' => rest_url('flexmile/v1/'),
            'docs' => FLEXMILE_PLUGIN_URL . 'docs/'
        ], 403);
        exit;
    }

    /**
     * Sprawdza czy to zapytanie do REST API
     */
    private function is_rest_api_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // Sprawdź URL
        $rest_prefix = rest_get_url_prefix();
        if (strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Sprawdza czy to strona logowania
     */
    private function is_login_page() {
        return in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php']);
    }

    /**
     * Usuwa zbędne assety z headera
     */
    public function remove_frontend_assets() {
        if (is_admin()) {
            return;
        }

        // Usuń style i skrypty WP
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
    }

    /**
     * Czyści wp_head z niepotrzebnych rzeczy
     */
    private function clean_wp_head() {
        // Usuń generatory i meta
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        
        // Usuń feed links jeśli nie są potrzebne
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}
