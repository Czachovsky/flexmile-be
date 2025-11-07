<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Ofert
 * Z filtrowaniem po meta polach brand i model
 * Z reference ID w formacie FLX-LA-YYYY-XXX
 */
class Offers_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'offers';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpointy API
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_samochody'],
            'permission_callback' => '__return_true',
            'args' => $this->get_collection_params(),
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/reserved', [
            'methods' => 'GET',
            'callback' => [$this, 'get_zarezerwowane'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'description' => 'Numer strony',
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'description' => 'Liczba wyników na stronę',
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_samochod'],
            'permission_callback' => '__return_true',
        ]);

        // Nowy endpoint: lista dostępnych marek
        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/brands', [
            'methods' => 'GET',
            'callback' => [$this, 'get_brands'],
            'permission_callback' => '__return_true',
        ]);

        // Nowy endpoint: modele dla wybranej marki
        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/brands/(?P<brand_slug>[a-z0-9-]+)/models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_models_for_brand'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Pobiera listę samochodów z filtrowaniem
     */
    public function get_samochody($request) {
        $params = $request->get_params();

        $args = [
            'post_type' => 'offer',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1,
            'orderby' => isset($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date',
            'order' => isset($params['order']) ? sanitize_text_field($params['order']) : 'DESC',
            'meta_query' => [],
            'tax_query' => [],
        ];

        if (isset($params['only_reserved']) && $params['only_reserved'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_reservation_active',
                'value' => '1',
                'compare' => '=',
            ];
        } elseif (!isset($params['show_reserved']) || $params['show_reserved'] !== 'true') {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_reservation_active',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_reservation_active',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }

        // Filtrowanie po marce (meta pole zamiast taksonomii)
        if (!empty($params['car_brand'])) {
            $args['meta_query'][] = [
                'key' => '_car_brand_slug',
                'value' => sanitize_text_field($params['car_brand']),
                'compare' => '=',
            ];
        }

        // Filtrowanie po modelu (meta pole)
        if (!empty($params['car_model'])) {
            $args['meta_query'][] = [
                'key' => '_car_model',
                'value' => sanitize_text_field($params['car_model']),
                'compare' => '=',
            ];
        }

        if (!empty($params['body_type'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'body_type',
                'field' => 'slug',
                'terms' => sanitize_text_field($params['body_type']),
            ];
        }

        if (!empty($params['fuel_type'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'fuel_type',
                'field' => 'slug',
                'terms' => sanitize_text_field($params['fuel_type']),
            ];
        }

        if (!empty($params['year_from']) || !empty($params['year_to'])) {
            $rocznik_query = ['key' => '_year', 'type' => 'NUMERIC'];

            if (!empty($params['year_from'])) {
                $rocznik_query['value'] = intval($params['year_from']);
                $rocznik_query['compare'] = '>=';
            }

            if (!empty($params['year_to'])) {
                if (!empty($params['year_from'])) {
                    $rocznik_query['value'] = [intval($params['year_from']), intval($params['year_to'])];
                    $rocznik_query['compare'] = 'BETWEEN';
                } else {
                    $rocznik_query['value'] = intval($params['year_to']);
                    $rocznik_query['compare'] = '<=';
                }
            }

            $args['meta_query'][] = $rocznik_query;
        }

        if (!empty($params['max_mileage'])) {
            $args['meta_query'][] = [
                'key' => '_mileage',
                'value' => intval($params['max_mileage']),
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        if (!empty($params['price_from']) || !empty($params['price_to'])) {
            $cena_query = ['key' => '_lowest_price', 'type' => 'NUMERIC'];

            if (!empty($params['price_from']) && !empty($params['price_to'])) {
                $cena_query['value'] = [floatval($params['price_from']), floatval($params['price_to'])];
                $cena_query['compare'] = 'BETWEEN';
            } elseif (!empty($params['price_from'])) {
                $cena_query['value'] = floatval($params['price_from']);
                $cena_query['compare'] = '>=';
            } else {
                $cena_query['value'] = floatval($params['price_to']);
                $cena_query['compare'] = '<=';
            }

            $args['meta_query'][] = $cena_query;
        }

        $query = new \WP_Query($args);

        $samochody = [];
        foreach ($query->posts as $post) {
            $samochody[] = $this->prepare_samochod_data_minimal($post);
        }

        $response_data = [
            'offers' => $samochody,
            'meta' => [
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'current_page' => intval($params['page'] ?? 1),
                'per_page' => intval($params['per_page'] ?? 10),
            ],
        ];

        $response = new \WP_REST_Response($response_data);

        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Pobiera TYLKO zarezerwowane samochody (też lightweight)
     */
    public function get_zarezerwowane($request) {
        $params = $request->get_params();

        $args = [
            'post_type' => 'offer',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_reservation_active',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ];

        $query = new \WP_Query($args);

        $samochody = [];
        foreach ($query->posts as $post) {
            $samochody[] = $this->prepare_samochod_data_minimal($post);
        }

        $response_data = [
            'offers' => $samochody,
            'meta' => [
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'current_page' => intval($params['page'] ?? 1),
                'per_page' => intval($params['per_page'] ?? 10),
            ],
        ];

        $response = new \WP_REST_Response($response_data);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Pobiera pojedynczy samochód (PEŁNE DANE)
     */
    public function get_samochod($request) {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (!$post || $post->post_type !== 'offer') {
            return new \WP_Error('not_found', 'Oferta nie została znaleziona', ['status' => 404]);
        }

        return $this->prepare_samochod_data($post);
    }

    /**
     * Zwraca listę dostępnych marek z config.json
     */
    public function get_brands() {
        $config = $this->load_config();

        if (!$config || !isset($config['brands'])) {
            return new \WP_Error('config_error', 'Nie można załadować konfiguracji marek', ['status' => 500]);
        }

        $brands = [];
        foreach ($config['brands'] as $slug => $brand) {
            $brands[] = [
                'slug' => $slug,
                'name' => $brand['name']
            ];
        }

        return new \WP_REST_Response($brands);
    }

    /**
     * Zwraca modele dla wybranej marki
     */
    public function get_models_for_brand($request) {
        $brand_slug = sanitize_text_field($request['brand_slug']);

        $config = $this->load_config();

        if (!$config || !isset($config['brands'][$brand_slug])) {
            return new \WP_Error('not_found', 'Nie znaleziono marki', ['status' => 404]);
        }

        $models = $config['brands'][$brand_slug]['models'];

        return new \WP_REST_Response([
            'brand_slug' => $brand_slug,
            'brand_name' => $config['brands'][$brand_slug]['name'],
            'models' => $models
        ]);
    }

    /**
     * Ładuje config z JSON
     */
    private function load_config() {
        $config_file = FLEXMILE_PLUGIN_DIR . 'config.json';

        if (!file_exists($config_file)) {
            return null;
        }

        $json = file_get_contents($config_file);
        return json_decode($json, true);
    }

    /**
     * Przygotowuje MINIMALNE dane samochodu dla listy (lightweight)
     */
    private function prepare_samochod_data_minimal($post) {
        $data = [
            'id' => $post->ID,
            'car_reference_id' => get_post_meta($post->ID, '_car_reference_id', true) ?: null,
            'title' => $post->post_title,
            'slug' => $post->post_name,
        ];

        $data['image'] = [
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'medium' => get_the_post_thumbnail_url($post->ID, 'medium'),
            'large' => get_the_post_thumbnail_url($post->ID, 'large'),
        ];

        $data['engine'] = get_post_meta($post->ID, '_engine', true) ?: 'Brak danych';
        $data['horsepower'] = (int) get_post_meta($post->ID, '_horsepower', true);
        $data['transmission'] = get_post_meta($post->ID, '_transmission', true);
        $data['year'] = (int) get_post_meta($post->ID, '_year', true);
        $data['mileage'] = (int) get_post_meta($post->ID, '_mileage', true);

        // Marka i model z meta pól
        $brand_slug = get_post_meta($post->ID, '_car_brand_slug', true);
        $model = get_post_meta($post->ID, '_car_model', true);

        $config = $this->load_config();
        $brand_name = '';

        if ($config && isset($config['brands'][$brand_slug])) {
            $brand_name = $config['brands'][$brand_slug]['name'];
        }

        $data['brand'] = [
            'slug' => $brand_slug,
            'name' => $brand_name,
        ];

        $data['model'] = $model;

        $typ_nadwozia = wp_get_post_terms($post->ID, 'body_type');
        $data['body_type'] = !empty($typ_nadwozia) ? [
            'name' => $typ_nadwozia[0]->name,
            'slug' => $typ_nadwozia[0]->slug,
        ] : null;

        $paliwo = wp_get_post_terms($post->ID, 'fuel_type');
        $data['fuel_type'] = !empty($paliwo) ? [
            'name' => $paliwo[0]->name,
            'slug' => $paliwo[0]->slug,
        ] : null;

        $cena_najnizsza = (float) get_post_meta($post->ID, '_lowest_price', true);
        $data['price_from'] = $cena_najnizsza;

        $data['attributes'] = [
            'new' => get_post_meta($post->ID, '_new_car', true) === '1',
            'available_immediately' => get_post_meta($post->ID, '_available_immediately', true) === '1',
            'coming_soon' => get_post_meta($post->ID, '_coming_soon', true) === '1',
            'popular' => get_post_meta($post->ID, '_most_popular', true) === '1',
            'featured' => get_post_meta($post->ID, '_featured', true) === '1',
        ];

        $data['available'] = get_post_meta($post->ID, '_reservation_active', true) !== '1';

        return $data;
    }

    /**
     * Przygotowuje PEŁNE dane samochodu (dla pojedynczego widoku)
     */
    private function prepare_samochod_data($post) {
        $data = [
            'id' => $post->ID,
            'car_reference_id' => get_post_meta($post->ID, '_car_reference_id', true) ?: null,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'slug' => $post->post_name,
        ];

        $data['featured_image'] = get_the_post_thumbnail_url($post->ID, 'large');
        $data['thumbnail'] = get_the_post_thumbnail_url($post->ID, 'thumbnail');

        $gallery_ids = get_post_meta($post->ID, '_gallery', true);
        $data['gallery'] = [];
        if ($gallery_ids) {
            foreach (explode(',', $gallery_ids) as $img_id) {
                if ($img_id) {
                    $data['gallery'][] = [
                        'id' => (int) $img_id,
                        'url' => wp_get_attachment_url($img_id),
                        'thumbnail' => wp_get_attachment_image_url($img_id, 'thumbnail'),
                        'medium' => wp_get_attachment_image_url($img_id, 'medium'),
                        'large' => wp_get_attachment_image_url($img_id, 'large'),
                    ];
                }
            }
        }

        $data['specs'] = [
            'year' => (int) get_post_meta($post->ID, '_year', true),
            'mileage' => (int) get_post_meta($post->ID, '_mileage', true),
            'engine' => get_post_meta($post->ID, '_engine', true),
            'horsepower' => (int) get_post_meta($post->ID, '_horsepower', true),
            'engine_capacity' => (int) get_post_meta($post->ID, '_engine_capacity', true),
            'transmission' => get_post_meta($post->ID, '_transmission', true),
            'drivetrain' => get_post_meta($post->ID, '_drivetrain', true),
            'color' => get_post_meta($post->ID, '_color', true),
            'seats' => (int) get_post_meta($post->ID, '_seats', true),
            'doors' => (int) get_post_meta($post->ID, '_doors', true),
            'vin_number' => get_post_meta($post->ID, '_vin_number', true),
        ];

        // Marka i model z meta pól
        $brand_slug = get_post_meta($post->ID, '_car_brand_slug', true);
        $model = get_post_meta($post->ID, '_car_model', true);

        $config = $this->load_config();
        $brand_name = '';

        if ($config && isset($config['brands'][$brand_slug])) {
            $brand_name = $config['brands'][$brand_slug]['name'];
        }

        $data['brand'] = [
            'slug' => $brand_slug,
            'name' => $brand_name,
        ];

        $data['model'] = $model;

        $typ_nadwozia = wp_get_post_terms($post->ID, 'body_type');
        $data['body_type'] = !empty($typ_nadwozia) ? [
            'id' => $typ_nadwozia[0]->term_id,
            'name' => $typ_nadwozia[0]->name,
            'slug' => $typ_nadwozia[0]->slug,
        ] : null;

        $paliwo = wp_get_post_terms($post->ID, 'fuel_type');
        $data['fuel_type'] = !empty($paliwo) ? [
            'id' => $paliwo[0]->term_id,
            'name' => $paliwo[0]->name,
            'slug' => $paliwo[0]->slug,
        ] : null;

        $config_price = get_post_meta($post->ID, '_pricing_config', true);

        if (!empty($config_price)) {
            $data['pricing'] = [
                'rental_periods' => $config_price['rental_periods'],
                'mileage_limits' => $config_price['mileage_limits'],
                'price_matrix' => $config_price['prices'],
                'lowest_price' => (float) get_post_meta($post->ID, '_lowest_price', true),
            ];
        } else {
            $data['pricing'] = [
                'rental_periods' => [],
                'mileage_limits' => [],
                'price_matrix' => [],
                'lowest_price' => 0,
            ];
        }

        $wyposazenie_std_raw = get_post_meta($post->ID, '_standard_equipment', true);
        $data['standard_equipment'] = $this->parse_textarea_to_array($wyposazenie_std_raw);

        $wyposazenie_dod_raw = get_post_meta($post->ID, '_additional_equipment', true);
        $data['additional_equipment'] = $this->parse_textarea_to_array($wyposazenie_dod_raw);

        $data['attributes'] = [
            'new' => get_post_meta($post->ID, '_new_car', true) === '1',
            'available_immediately' => get_post_meta($post->ID, '_available_immediately', true) === '1',
            'coming_soon' => get_post_meta($post->ID, '_coming_soon', true) === '1',
            'popular' => get_post_meta($post->ID, '_most_popular', true) === '1',
            'featured' => get_post_meta($post->ID, '_featured', true) === '1',
        ];

        $data['available'] = get_post_meta($post->ID, '_reservation_active', true) !== '1';

        return $data;
    }

    /**
     * Parsuje textarea na tablicę (każda nowa linia to element)
     */
    private function parse_textarea_to_array($text) {
        if (empty($text)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function($line) {
            return !empty($line);
        });

        return array_values($lines);
    }

    /**
     * Parametry dla kolekcji
     */
    private function get_collection_params() {
        return [
            'page' => [
                'description' => 'Numer strony (dla infinite scroll)',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'description' => 'Liczba wyników na stronę',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'orderby' => [
                'description' => 'Sortowanie',
                'type' => 'string',
                'default' => 'date',
                'enum' => ['date', 'title', 'meta_value_num'],
            ],
            'order' => [
                'description' => 'Kierunek sortowania',
                'type' => 'string',
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ],
            'car_brand' => [
                'description' => 'Filtr po marce (slug)',
                'type' => 'string',
            ],
            'car_model' => [
                'description' => 'Filtr po modelu',
                'type' => 'string',
            ],
            'body_type' => [
                'description' => 'Filtr po typie nadwozia (slug)',
                'type' => 'string',
            ],
            'fuel_type' => [
                'description' => 'Filtr po rodzaju paliwa (slug)',
                'type' => 'string',
            ],
            'year_from' => [
                'description' => 'Rocznik od',
                'type' => 'integer',
            ],
            'year_to' => [
                'description' => 'Rocznik do',
                'type' => 'integer',
            ],
            'max_mileage' => [
                'description' => 'Maksymalny przebieg',
                'type' => 'integer',
            ],
            'price_from' => [
                'description' => 'Cena od (najniższa)',
                'type' => 'number',
            ],
            'price_to' => [
                'description' => 'Cena do (najniższa)',
                'type' => 'number',
            ],
            'show_reserved' => [
                'description' => 'Pokaż zarezerwowane samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
            'only_reserved' => [
                'description' => 'Zwróć TYLKO zarezerwowane samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
        ];
    }
}gallery
