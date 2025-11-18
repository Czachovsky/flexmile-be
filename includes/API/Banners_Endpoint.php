<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Bannerów
 * Zwraca maksymalnie 3 bannery (label + description)
 */
class Banners_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'banners';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpoint API
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_banners'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Pobiera listę bannerów
     */
    public function get_banners($request) {
        $banners = [];

        // Pobierz 3 bannery z opcji WordPress
        for ($i = 1; $i <= 3; $i++) {
            $label = get_option("flexmile_banner_{$i}_label", '');
            $description = get_option("flexmile_banner_{$i}_description", '');

            // Dodaj tylko jeśli ma wypełniony label
            if (!empty($label)) {
                $banners[] = [
                    'label' => $label,
                    'description' => $description,
                ];
            }
        }

        return new \WP_REST_Response($banners, 200);
    }
}