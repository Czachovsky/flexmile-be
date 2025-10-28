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
        add_action('template_redirect', [$this, 'block_frontend'], 1);
        add_action('wp_head', [$this, 'remove_frontend_assets'], 1);

        // Remove unnecessary generators and links
        $this->clean_wp_head();
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
            'message' => 'This is a headless WordPress installation. Please use the REST API.',
            'api_url' => rest_url('flexmile/v1/'),
            'docs' => FLEXMILE_PLUGIN_URL . 'docs/'
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

        // Check URL
        $rest_prefix = rest_get_url_prefix();
        if (strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false) {
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
