<?php
/**
 * Plugin Name: FlexMile
 * Plugin URI: https://flexmile.pl
 * Description: Headless WordPress API for FlexMile
 * Version: 1.0.0
 * Author: FlexMile Team
 * Text Domain: flexmile
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('FLEXMILE_VERSION', '1.0.0');
define('FLEXMILE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLEXMILE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'FlexMile\\';
    $base_dir = FLEXMILE_PLUGIN_DIR . 'includes/';

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

/**
 * Main plugin class
 */
class FlexMile_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->load_components();
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    private function load_components() {
        // Frontend blocker (headless mode)
        new FlexMile\Core\Frontend_Blocker();

        // Custom Post Types
        new FlexMile\PostTypes\Offers();
        new FlexMile\PostTypes\Reservations();
        new FlexMile\PostTypes\Orders();

        // REST API
        new FlexMile\API\Offers_Endpoint();
        new FlexMile\API\Reservations_Endpoint();
        new FlexMile\API\Contact_Endpoint();
        new FlexMile\API\Banners_Endpoint();

        // Admin
        new FlexMile\Admin\Admin_Menu();
        new FlexMile\Admin\Sample_Data_Importer();
    }

    public function load_textdomain() {
        load_plugin_textdomain('flexmile', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        // Register CPT before flush
        new FlexMile\PostTypes\Offers();
        new FlexMile\PostTypes\Reservations();
        new FlexMile\PostTypes\Orders();

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize plugin
FlexMile_Plugin::get_instance();