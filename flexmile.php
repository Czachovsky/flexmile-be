<?php
/**
 * Plugin Name: FlexMile
 * Plugin URI: https://flexmile.pl
 * Description: Headless WordPress API for FlexMile
 * Version: 1.0.7
 * Author: MR
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

if (!defined('FLEXMILE_CSV_IMPORT_ENABLED')) {
    define('FLEXMILE_CSV_IMPORT_ENABLED', false);
}

/**
 * Autoloader for plugin classes
 * Ulepszony z lepszą obsługą błędów i cache'owaniem ścieżek
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
        require_once $file;
        
        // Sprawdź czy klasa rzeczywiście została załadowana
        if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
            // Jeśli klasa nie istnieje po załadowaniu, może być problem z namespace
            error_log(sprintf(
                'FlexMile Autoloader: Klasa %s nie została znaleziona w pliku %s',
                $class,
                $file
            ));
        }
    } else {
        // Loguj tylko jeśli to naprawdę nasza klasa (nie z innych pluginów)
        if (strpos($class, $prefix) === 0) {
            error_log(sprintf(
                'FlexMile Autoloader: Nie znaleziono pliku dla klasy %s w ścieżce %s',
                $class,
                $file
            ));
        }
    }
}, true, true); // true, true = prepend queue i throw exceptions

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
        // Upewnij się, że REST API jest włączone
        add_filter('rest_enabled', '__return_true', 999);
        add_filter('rest_jsonp_enabled', '__return_true', 999);
        
        // REST API Fix (musi być pierwsze, aby naprawić permalinki)
        $this->load_class('FlexMile\Core\REST_API_Fix', 'Core/REST_API_Fix.php');
        new FlexMile\Core\REST_API_Fix();

        // Email configuration (musi być przed innymi komponentami wysyłającymi emaile)
        $this->load_class('FlexMile\Core\Email_Config', 'Core/Email_Config.php');
        new FlexMile\Core\Email_Config();

        // Frontend blocker (headless mode)
        $this->load_class('FlexMile\Core\Frontend_Blocker', 'Core/Frontend_Blocker.php');
        new FlexMile\Core\Frontend_Blocker();

        // Custom Post Types
        $this->load_class('FlexMile\PostTypes\Offers', 'PostTypes/Offers.php');
        new FlexMile\PostTypes\Offers();
        $this->load_class('FlexMile\PostTypes\Reservations', 'PostTypes/Reservations.php');
        new FlexMile\PostTypes\Reservations();
        $this->load_class('FlexMile\PostTypes\Orders', 'PostTypes/Orders.php');
        new FlexMile\PostTypes\Orders();

        // REST API
        $this->load_class('FlexMile\API\Offers_Endpoint', 'API/Offers_Endpoint.php');
        new FlexMile\API\Offers_Endpoint();
        $this->load_class('FlexMile\API\Reservations_Endpoint', 'API/Reservations_Endpoint.php');
        new FlexMile\API\Reservations_Endpoint();
        $this->load_class('FlexMile\API\Contact_Endpoint', 'API/Contact_Endpoint.php');
        new FlexMile\API\Contact_Endpoint();
        $this->load_class('FlexMile\API\Banners_Endpoint', 'API/Banners_Endpoint.php');
        new FlexMile\API\Banners_Endpoint();

        // Admin
        $this->load_class('FlexMile\Admin\Admin_Menu', 'Admin/Admin_Menu.php');
        new FlexMile\Admin\Admin_Menu();
        $this->load_class('FlexMile\Admin\Sample_Data_Importer', 'Admin/Sample_Data_Importer.php');
        new FlexMile\Admin\Sample_Data_Importer();
        $this->load_class('FlexMile\Admin\Dashboard_Widgets', 'Admin/Dashboard_Widgets.php');
        new FlexMile\Admin\Dashboard_Widgets();
        $this->load_class('FlexMile\Admin\Email_Tester', 'Admin/Email_Tester.php');
        new FlexMile\Admin\Email_Tester();
    }

    private function load_class($class_name, $relative_path) {
        if (!class_exists($class_name, false)) {
            $file_path = FLEXMILE_PLUGIN_DIR . 'includes/' . $relative_path;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log(sprintf(
                    'FlexMile: Nie można załadować klasy %s - plik nie istnieje: %s',
                    $class_name,
                    $file_path
                ));
            }
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('flexmile', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        // Ensure permalinks are enabled
        $permalink_structure = get_option('permalink_structure');
        if (empty($permalink_structure)) {
            update_option('permalink_structure', '/%postname%/');
        }

        // Register CPT before flush
        $this->load_class('FlexMile\PostTypes\Offers', 'PostTypes/Offers.php');
        new FlexMile\PostTypes\Offers();
        $this->load_class('FlexMile\PostTypes\Reservations', 'PostTypes/Reservations.php');
        new FlexMile\PostTypes\Reservations();
        $this->load_class('FlexMile\PostTypes\Orders', 'PostTypes/Orders.php');
        new FlexMile\PostTypes\Orders();

        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients
        delete_transient('flexmile_rewrite_flushed');
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize plugin
FlexMile_Plugin::get_instance();
