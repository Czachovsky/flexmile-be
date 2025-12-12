<?php
namespace FlexMile\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Frontend Blocking
 * WordPress works only as headless CMS with REST API
 */
class Frontend_Blocker {

    public function __construct() {
        // Wykryj REST API bardzo wcześnie - przed wszystkimi innymi akcjami
        add_action('plugins_loaded', [$this, 'early_rest_api_check'], 1);
        add_action('parse_request', [$this, 'early_rest_api_check'], 1);
        add_action('template_redirect', [$this, 'block_frontend'], 1);
        add_action('wp_head', [$this, 'remove_frontend_assets'], 1);

        // Remove unnecessary generators and links
        $this->clean_wp_head();
    }

    /**
     * Wczesne wykrywanie REST API przed template_redirect
     */
    public function early_rest_api_check() {
        // Nie definiuj REST_REQUEST - WordPress już to robi
        // Tylko sprawdź czy to jest REST API request
        if ($this->is_rest_api_request()) {
            // WordPress automatycznie ustawi REST_REQUEST w rest-api.php
            return;
        }
    }

    /**
     * Blocks access to all frontend pages
     */
    public function block_frontend() {
        // Exceptions: admin panel, REST API, wp-login, wp-cron
        if (is_admin() ||
            $this->is_rest_api_request() ||
            $this->is_login_page() ||
            (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        // Return JSON response for headless
        wp_send_json([
            'error' => 'Frontend disabled',
            'message' => 'Strona dostępna na flexmile.pl'
        ], 403);
        exit;
    }

    /**
     * Checks if it's a REST API request
     */
    private function is_rest_api_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // Check URL - multiple methods for better compatibility
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Usuń query string dla dokładniejszego sprawdzenia
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        if ($request_path === null) {
            $request_path = $request_uri;
        }
        
        // Check for wp-json in URL (z lub bez końcowego slasha)
        if (strpos($request_path, '/wp-json/') !== false || 
            strpos($request_path, '/wp-json') !== false && $request_path !== '/wp-json') {
            return true;
        }
        
        // Check using WordPress function (jeśli dostępna)
        if (function_exists('rest_get_url_prefix')) {
            $rest_prefix = rest_get_url_prefix();
            if ($rest_prefix && (strpos($request_path, '/' . $rest_prefix . '/') !== false || 
                strpos($request_path, '/' . $rest_prefix) !== false && $request_path !== '/' . $rest_prefix)) {
                return true;
            }
        }

        // Sprawdź też przez zmienne globalne WordPress
        global $wp;
        if (isset($wp->query_vars['rest_route'])) {
            return true;
        }

        return false;
    }

    /**
     * Checks if it's a login page
     */
    private function is_login_page() {
        return in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php']);
    }

    /**
     * Removes unnecessary assets from header
     */
    public function remove_frontend_assets() {
        if (is_admin()) {
            return;
        }

        // Remove WP styles and scripts
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
    }

    /**
     * Cleans wp_head from unnecessary items
     */
    private function clean_wp_head() {
        // Remove generators and meta
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');

        // Remove feed links if not needed
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}
