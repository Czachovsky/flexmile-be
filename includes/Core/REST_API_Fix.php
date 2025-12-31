<?php
namespace FlexMile\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Fix & Diagnostics
 * Naprawia problemy z REST API na serwerze produkcyjnym
 */
class REST_API_Fix {

    public function __construct() {
        // Wykonaj naprawę przy inicjalizacji wtyczki
        add_action('init', [$this, 'ensure_permalinks_enabled'], 1);
        add_action('rest_api_init', [$this, 'verify_routes_registered'], 999);
        
        // Flush rewrite rules jeśli potrzeba (tylko raz)
        add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        
        // Dodaj endpoint diagnostyczny
        add_action('rest_api_init', [$this, 'register_diagnostics_route']);
    }

    /**
     * Sprawdza czy permalinki są włączone
     */
    public function ensure_permalinks_enabled() {
        // Sprawdź czy permalinki są włączone
        $permalink_structure = get_option('permalink_structure');
        
        if (empty($permalink_structure)) {
            // Jeśli permalinki nie są włączone, spróbuj je włączyć automatycznie
            // Ustaw domyślną strukturę permalinków (Post name)
            update_option('permalink_structure', '/%postname%/');
            
            // Flush rewrite rules
            flush_rewrite_rules(false);
        }
    }

    /**
     * Weryfikuje czy route'y są poprawnie zarejestrowane
     */
    public function verify_routes_registered() {
        $server = rest_get_server();
        $routes = $server->get_routes();
        
        $expected_routes = [
            '/flexmile/v1',
            '/flexmile/v1/offers',
            '/flexmile/v1/reservations',
            '/flexmile/v1/contact',
            '/flexmile/v1/banners',
        ];
        
        $missing_routes = [];
        foreach ($expected_routes as $route) {
            if (!isset($routes[$route])) {
                $missing_routes[] = $route;
            }
        }
        
        // Jeśli brakuje route'ów, spróbuj je zarejestrować ponownie
        if (!empty($missing_routes) && !get_transient('flexmile_rewrite_flushed')) {
            flush_rewrite_rules(false);
            set_transient('flexmile_rewrite_flushed', true, HOUR_IN_SECONDS);
        }
    }

    /**
     * Flush rewrite rules jeśli potrzeba (tylko raz dziennie)
     */
    public function maybe_flush_rewrite_rules() {
        // Sprawdź czy trzeba zaktualizować rewrite rules
        $last_flush = get_option('flexmile_last_rewrite_flush', 0);
        $current_time = time();
        
        // Flush raz dziennie maksymalnie
        if (($current_time - $last_flush) > DAY_IN_SECONDS) {
            // Sprawdź czy permalinki są włączone
            $permalink_structure = get_option('permalink_structure');
            
            if (!empty($permalink_structure)) {
                flush_rewrite_rules(false);
                update_option('flexmile_last_rewrite_flush', $current_time);
            }
        }
    }

    /**
     * Rejestruje endpoint diagnostyczny
     */
    public function register_diagnostics_route() {
        register_rest_route('flexmile/v1', '/diagnostics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_diagnostics'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Zwraca informacje diagnostyczne
     */
    public function get_diagnostics() {
        $server = rest_get_server();
        $routes = $server->get_routes();
        
        $permalink_structure = get_option('permalink_structure');
        $rest_prefix = rest_get_url_prefix();
        
        $flexmile_routes = [];
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/flexmile/v1') === 0) {
                $flexmile_routes[] = $route;
            }
        }
        
        return [
            'permalink_structure' => $permalink_structure ?: 'NOT SET (Plain)',
            'rest_prefix' => $rest_prefix,
            'rest_url' => rest_url('flexmile/v1'),
            'flexmile_routes_count' => count($flexmile_routes),
            'flexmile_routes' => $flexmile_routes,
            'all_routes_count' => count($routes),
            'rewrite_rules_flushed' => get_option('flexmile_last_rewrite_flush', 0),
            'recommendations' => $this->get_recommendations($permalink_structure, $flexmile_routes),
        ];
    }

    /**
     * Zwraca rekomendacje na podstawie diagnostyki
     */
    private function get_recommendations($permalink_structure, $flexmile_routes) {
        $recommendations = [];
        
        if (empty($permalink_structure)) {
            $recommendations[] = 'Permalinki nie są włączone. Przejdź do Ustawienia → Permalinki i wybierz dowolną opcję oprócz "Plain".';
        }
        
        if (count($flexmile_routes) < 4) {
            $recommendations[] = 'Niektóre route\'y FlexMile nie są zarejestrowane. Spróbuj deaktywować i ponownie aktywować wtyczkę.';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Wszystko wygląda dobrze!';
        }
        
        return $recommendations;
    }
}






