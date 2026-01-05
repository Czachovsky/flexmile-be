<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Ofert
 * KOMPLETNY ZESTAW FILTRÓW v2.1
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

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/brands', [
            'methods' => 'GET',
            'callback' => [$this, 'get_brands'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/brands/(?P<brand_slug>[a-z0-9-]+)/models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_models_for_brand'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Pobiera listę samochodów z filtrowaniem
     * ZAKTUALIZOWANE: dodano wszystkie filtry
     */
    public function get_samochody($request) {
        $params = $request->get_params();

        $orderby = isset($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date';
        $order = isset($params['order']) ? sanitize_text_field($params['order']) : 'DESC';
        
        $args = [
            'post_type' => 'offer',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1,
            'orderby' => $orderby === 'price' ? 'meta_value_num' : $orderby,
            'order' => $order,
            'meta_query' => ['relation' => 'AND'],
        ];
        
        // Jeśli sortujemy po cenie, musimy ustawić meta_key
        if ($orderby === 'price') {
            $args['meta_key'] = '_lowest_price';
        }

        // ========================================
        // FILTR: Tylko dostępne (nie zarezerwowane)
        // ========================================
        if (isset($params['available_only']) && $params['available_only'] === 'true') {
            // Dostępne = brak aktywnej rezerwacji ORAZ brak zatwierdzonego zamówienia
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
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_order_approved',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_order_approved',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }
        // Alternatywnie: pokaż tylko zarezerwowane i zamówione
        elseif (isset($params['only_reserved']) && $params['only_reserved'] === 'true') {
            // Zarezerwowane LUB zamówione = aktywna rezerwacja LUB zatwierdzone zamówienie
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_reservation_active',
                    'value' => '1',
                    'compare' => '=',
                ],
                [
                    'key' => '_order_approved',
                    'value' => '1',
                    'compare' => '=',
                ],
            ];
        }
        // Domyślnie: ukryj zarezerwowane (chyba że show_reserved=true)
        elseif (!isset($params['show_reserved']) || $params['show_reserved'] !== 'true') {
            // Domyślnie pokazuj tylko dostępne:
            // brak aktywnej rezerwacji ORAZ brak zatwierdzonego zamówienia
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
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_order_approved',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_order_approved',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }

        // ========================================
        // FILTR: Marka
        // ========================================
        if (!empty($params['car_brand'])) {
            $args['meta_query'][] = [
                'key' => '_car_brand_slug',
                'value' => sanitize_text_field($params['car_brand']),
                'compare' => '=',
            ];
        }

        // ========================================
        // FILTR: Model
        // ========================================
        if (!empty($params['car_model'])) {
            $args['meta_query'][] = [
                'key' => '_car_model',
                'value' => sanitize_text_field($params['car_model']),
                'compare' => '=',
            ];
        }

        // ========================================
        // FILTR: Typ nadwozia
        // ========================================
        if (!empty($params['body_type'])) {
            $args['meta_query'][] = [
                'key' => '_body_type',
                'value' => sanitize_text_field($params['body_type']),
                'compare' => '=',
            ];
        }

        // ========================================
        // FILTR: Typ paliwa
        // ========================================
        if (!empty($params['fuel_type'])) {
            $args['meta_query'][] = [
                'key' => '_fuel_type',
                'value' => sanitize_text_field($params['fuel_type']),
                'compare' => '=',
            ];
        }

        // ========================================
        // FILTR: Typ skrzyni (NOWOŚĆ!)
        // ========================================
        if (!empty($params['transmission'])) {
            $transmission = sanitize_text_field($params['transmission']);

            // Akceptuj: manual, automatic
            if (in_array($transmission, ['manual', 'automatic'])) {
                $args['meta_query'][] = [
                    'key' => '_transmission',
                    'value' => $transmission,
                    'compare' => '=',
                ];
            }
        }

        // ========================================
        // FILTR: Rocznik od/do
        // ========================================
        if (!empty($params['year_from']) || !empty($params['year_to'])) {
            $rocznik_query = ['key' => '_year', 'type' => 'NUMERIC'];

            if (!empty($params['year_from']) && !empty($params['year_to'])) {
                $rocznik_query['value'] = [intval($params['year_from']), intval($params['year_to'])];
                $rocznik_query['compare'] = 'BETWEEN';
            } elseif (!empty($params['year_from'])) {
                $rocznik_query['value'] = intval($params['year_from']);
                $rocznik_query['compare'] = '>=';
            } else {
                $rocznik_query['value'] = intval($params['year_to']);
                $rocznik_query['compare'] = '<=';
            }

            $args['meta_query'][] = $rocznik_query;
        }


        // ========================================
        // FILTR: Cena od/do
        // ========================================
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

        // ========================================
        // FILTR: Dostępne od ręki (available_immediately)
        // ========================================
        if (isset($params['available_immediately']) && $params['available_immediately'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_available_immediately',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Wykonaj zapytanie
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
     * Pobiera TYLKO zarezerwowane samochody
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
                'relation' => 'AND',
                [
                    'key' => '_reservation_active',
                    'value' => '1',
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => '_order_approved',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => '_order_approved',
                        'value' => '1',
                        'compare' => '!=',
                    ],
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
     * Pobiera pojedynczy samochód
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
     * Zwraca listę dostępnych marek (tylko te, które mają oferty)
     * Z cache'owaniem dla lepszej wydajności
     */
    public function get_brands() {
        // Sprawdź cache (ważny przez 1 godzinę)
        $cache_key = 'flexmile_brands_list';
        $cached_brands = get_transient($cache_key);

        if ($cached_brands !== false) {
            return new \WP_REST_Response($cached_brands);
        }

        $config = $this->load_config();

        if (!$config || !isset($config['brands'])) {
            return new \WP_Error('config_error', 'Nie można załadować konfiguracji marek', ['status' => 500]);
        }

        // Pobierz wszystkie unikalne marki z opublikowanych ofert
        $args = [
            'post_type' => 'offer',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Pobierz wszystkie oferty
            'fields' => 'ids', // Tylko ID, dla wydajności
        ];

        $query = new \WP_Query($args);
        $brand_slugs_in_offers = [];

        // Zbierz wszystkie unikalne marki z ofert
        foreach ($query->posts as $post_id) {
            $brand_slug = get_post_meta($post_id, '_car_brand_slug', true);
            if (!empty($brand_slug)) {
                $brand_slugs_in_offers[$brand_slug] = true;
            }
        }

        // Zwróć tylko marki, które faktycznie występują w ofertach
        $brands = [];
        foreach ($config['brands'] as $slug => $brand) {
            if (isset($brand_slugs_in_offers[$slug])) {
                $brands[] = [
                    'slug' => $slug,
                    'name' => $brand['name']
                ];
            }
        }

        // Zapisz w cache na 1 godzinę
        set_transient($cache_key, $brands, HOUR_IN_SECONDS);

        return new \WP_REST_Response($brands);
    }

    /**
     * Czyści cache marek (wywoływane przy zmianach ofert)
     */
    public static function clear_brands_cache() {
        delete_transient('flexmile_brands_list');
    }

    /**
     * Zwraca modele dla wybranej marki (tylko te, które mają oferty)
     * Z cache'owaniem dla lepszej wydajności
     */
    public function get_models_for_brand($request) {
        $brand_slug = sanitize_text_field($request['brand_slug']);

        $config = $this->load_config();

        if (!$config || !isset($config['brands'][$brand_slug])) {
            return new \WP_Error('not_found', 'Nie znaleziono marki', ['status' => 404]);
        }

        // Sprawdź cache (ważny przez 1 godzinę)
        $cache_key = 'flexmile_models_' . $brand_slug;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return new \WP_REST_Response($cached_data);
        }

        // Pobierz wszystkie oferty dla danej marki
        $args = [
            'post_type' => 'offer',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Pobierz wszystkie oferty
            'fields' => 'ids', // Tylko ID, dla wydajności
            'meta_query' => [
                [
                    'key' => '_car_brand_slug',
                    'value' => $brand_slug,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $models_in_offers = [];

        // Zbierz wszystkie unikalne modele z ofert dla tej marki
        foreach ($query->posts as $post_id) {
            $model = get_post_meta($post_id, '_car_model', true);
            if (!empty($model)) {
                $models_in_offers[$model] = true;
            }
        }

        // Zwróć tylko modele, które faktycznie występują w ofertach
        $available_models = [];
        $all_models = $config['brands'][$brand_slug]['models'];
        
        foreach ($all_models as $model) {
            if (isset($models_in_offers[$model])) {
                $available_models[] = $model;
            }
        }

        $response_data = [
            'brand_slug' => $brand_slug,
            'brand_name' => $config['brands'][$brand_slug]['name'],
            'models' => $available_models
        ];

        // Zapisz w cache na 1 godzinę
        set_transient($cache_key, $response_data, HOUR_IN_SECONDS);

        return new \WP_REST_Response($response_data);
    }

    /**
     * Czyści cache modeli dla wszystkich marek (wywoływane przy zmianach ofert)
     */
    public static function clear_models_cache($brand_slug = null) {
        if ($brand_slug) {
            // Wyczyść cache dla konkretnej marki
            delete_transient('flexmile_models_' . $brand_slug);
        } else {
            // Wyczyść cache dla wszystkich marek - pobierz wszystkie marki z config
            $config_file = FLEXMILE_PLUGIN_DIR . 'config.json';
            if (file_exists($config_file)) {
                $json = file_get_contents($config_file);
                $config = json_decode($json, true);
                
                if ($config && isset($config['brands'])) {
                    foreach (array_keys($config['brands']) as $slug) {
                        delete_transient('flexmile_models_' . $slug);
                    }
                }
            }
        }
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
     * Przygotowuje MINIMALNE dane samochodu dla listy
     */
    private function prepare_samochod_data_minimal($post) {
        $data = [
            'id' => $post->ID,
        ];

        $gallery_ids = get_post_meta($post->ID, '_gallery', true);
        $image_url = false;
        
        if ($gallery_ids) {
            $gallery_array = explode(',', $gallery_ids);
            $first_image_id = trim($gallery_array[0]);
            if ($first_image_id) {
                $image_url = wp_get_attachment_image_url((int) $first_image_id, 'large');
            }
        }

        if (!$image_url) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
        }

        $data['image'] = $image_url ? $image_url : null;

        $data['engine'] = get_post_meta($post->ID, '_engine', true) ?: 'Brak danych';
        $data['horsepower'] = (int) get_post_meta($post->ID, '_horsepower', true);
        $data['transmission'] = get_post_meta($post->ID, '_transmission', true);
        $data['engine_capacity'] = (int) get_post_meta($post->ID, '_engine_capacity', true);

        // Marka i model
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

        // Fuel type z meta pól
        $data['fuel_type'] = get_post_meta($post->ID, '_fuel_type', true);

        // Użyj wybranej ceny wyświetlanej, jeśli istnieje, w przeciwnym razie najniższej
        $display_price = get_post_meta($post->ID, '_display_price', true);
        if (!empty($display_price) && is_numeric($display_price)) {
            $data['price_from'] = (float) $display_price;
        } else {
            $cena_najnizsza = (float) get_post_meta($post->ID, '_lowest_price', true);
            $data['price_from'] = $cena_najnizsza;
        }

        $data['attributes'] = [
            'new' => get_post_meta($post->ID, '_new_car', true) === '1',
            'available_immediately' => get_post_meta($post->ID, '_available_immediately', true) === '1',
            'coming_soon' => get_post_meta($post->ID, '_coming_soon', true) === '1',
            'popular' => get_post_meta($post->ID, '_most_popular', true) === '1',
        ];

        $coming_soon_date = get_post_meta($post->ID, '_coming_soon_date', true);
        $data['coming_soon_date'] = !empty($coming_soon_date) ? $coming_soon_date : null;

        // Dostępność = brak aktywnej rezerwacji i brak zatwierdzonego zamówienia
        $reservation_active = get_post_meta($post->ID, '_reservation_active', true) === '1';
        $order_approved = get_post_meta($post->ID, '_order_approved', true) === '1';
        $data['available'] = !$reservation_active && !$order_approved;

        // Dodatkowe usługi (tylko enabled + title)
        $additional_services_config = [
            'financing' => '_financing',
            'vehicle_service' => '_vehicle_service',
            'insurance_oc_ac_nnw' => '_insurance_oc_ac_nnw',
            'assistance_24h' => '_assistance_24h',
            'summer_winter_tires' => '_summer_winter_tires',
        ];
        
        $data['additional_services'] = [];
        foreach ($additional_services_config as $key => $meta_key) {
            $is_enabled = get_post_meta($post->ID, $meta_key, true) === '1';
            if ($is_enabled) {
                $title = get_post_meta($post->ID, $meta_key . '_title', true);
                $data['additional_services'][$key] = [
                    'enabled' => true,
                    'title' => $title ?: '',
                ];
            } else {
                $data['additional_services'][$key] = [
                    'enabled' => false,
                    'title' => '',
                ];
            }
        }

        return $data;
    }

    /**
     * Przygotowuje PEŁNE dane samochodu
     */
    private function prepare_samochod_data($post) {
        $data = [
            'id' => $post->ID,
            'car_reference_id' => get_post_meta($post->ID, '_car_reference_id', true) ?: null,
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

        $mileage = (int) get_post_meta($post->ID, '_mileage', true);
        $co2_emission = (int) get_post_meta($post->ID, '_co2_emission', true);
        
        $data['specs'] = [
            'year' => (int) get_post_meta($post->ID, '_year', true),
            'engine' => get_post_meta($post->ID, '_engine', true),
            'horsepower' => (int) get_post_meta($post->ID, '_horsepower', true),
            'engine_capacity' => (int) get_post_meta($post->ID, '_engine_capacity', true),
            'transmission' => get_post_meta($post->ID, '_transmission', true),
            'drivetrain' => get_post_meta($post->ID, '_drivetrain', true),
            'color' => get_post_meta($post->ID, '_color', true),
            'interior_color' => get_post_meta($post->ID, '_interior_color', true),
            'co2_emission' => $co2_emission > 0 ? $co2_emission : null,
            'condition' => get_post_meta($post->ID, '_car_condition', true),
            'mileage' => $mileage > 0 ? $mileage : null,
            'seats' => (int) get_post_meta($post->ID, '_seats', true),
            'doors' => (int) get_post_meta($post->ID, '_doors', true),
        ];

        // Marka i model
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

        // Body type i fuel type
        $data['body_type'] = get_post_meta($post->ID, '_body_type', true);
        $data['fuel_type'] = get_post_meta($post->ID, '_fuel_type', true);

        $config_price = get_post_meta($post->ID, '_pricing_config', true);

        if (!empty($config_price)) {
            $data['pricing'] = [
                'rental_periods' => $config_price['rental_periods'],
                'mileage_limits' => $config_price['mileage_limits'],
                'initial_payments' => isset($config_price['initial_payments']) && !empty($config_price['initial_payments']) 
                    ? $config_price['initial_payments'] 
                    : [0],
                'price_matrix' => $config_price['prices'],
                'lowest_price' => (float) get_post_meta($post->ID, '_lowest_price', true),
                'display_price' => get_post_meta($post->ID, '_display_price', true) ? (float) get_post_meta($post->ID, '_display_price', true) : null,
            ];
        } else {
            $data['pricing'] = [
                'rental_periods' => [],
                'mileage_limits' => [],
                'initial_payments' => [0],
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
        ];

        $coming_soon_date = get_post_meta($post->ID, '_coming_soon_date', true);
        $data['coming_soon_date'] = !empty($coming_soon_date) ? $coming_soon_date : null;

        // Dostępność = brak aktywnej rezerwacji i brak zatwierdzonego zamówienia
        $reservation_active = get_post_meta($post->ID, '_reservation_active', true) === '1';
        $order_approved = get_post_meta($post->ID, '_order_approved', true) === '1';
        $data['available'] = !$reservation_active && !$order_approved;

        // Dodatkowe usługi (tylko w szczegółach oferty)
        $additional_services_config = [
            'financing' => '_financing',
            'vehicle_service' => '_vehicle_service',
            'insurance_oc_ac_nnw' => '_insurance_oc_ac_nnw',
            'assistance_24h' => '_assistance_24h',
            'summer_winter_tires' => '_summer_winter_tires',
        ];
        
        $data['additional_services'] = [];
        foreach ($additional_services_config as $key => $meta_key) {
            $is_enabled = get_post_meta($post->ID, $meta_key, true) === '1';
            if ($is_enabled) {
                $title = get_post_meta($post->ID, $meta_key . '_title', true);
                $description = get_post_meta($post->ID, $meta_key . '_description', true);
                $data['additional_services'][$key] = [
                    'enabled' => true,
                    'title' => $title ?: '',
                    'description' => $description ?: '',
                ];
            } else {
                $data['additional_services'][$key] = [
                    'enabled' => false,
                    'title' => '',
                    'description' => '',
                ];
            }
        }

        // Customowe dodatkowe dane (tylko w szczegółach oferty)
        $custom_data = get_post_meta($post->ID, '_custom_additional_data', true);
        if (empty($custom_data) || !is_array($custom_data)) {
            $data['custom_additional_data'] = [];
        } else {
            // Upewnij się, że dane są w odpowiednim formacie
            $data['custom_additional_data'] = array_map(function($item) {
                return [
                    'title' => isset($item['title']) ? $item['title'] : '',
                    'description' => isset($item['description']) ? $item['description'] : '',
                ];
            }, $custom_data);
        }

        return $data;
    }

    /**
     * Parsuje textarea na tablicę
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
     * ZAKTUALIZOWANE: dodano wszystkie filtry
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
                'enum' => ['date', 'price'],
            ],
            'order' => [
                'description' => 'Kierunek sortowania',
                'type' => 'string',
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ],

            // ========================================
            // FILTRY PODSTAWOWE
            // ========================================
            'car_brand' => [
                'description' => 'Filtr po marce (slug, np. "bmw")',
                'type' => 'string',
            ],
            'car_model' => [
                'description' => 'Filtr po modelu (np. "X5")',
                'type' => 'string',
            ],
            'body_type' => [
                'description' => 'Filtr po typie nadwozia (np. "SUV")',
                'type' => 'string',
            ],
            'fuel_type' => [
                'description' => 'Filtr po rodzaju paliwa (np. "Diesel")',
                'type' => 'string',
            ],
            'transmission' => [
                'description' => 'Filtr po typie skrzyni ("manual" lub "automatic")',
                'type' => 'string',
                'enum' => ['manual', 'automatic'],
            ],

            // ========================================
            // FILTRY ZAKRESOWE
            // ========================================
            'year_from' => [
                'description' => 'Rocznik od',
                'type' => 'integer',
            ],
            'year_to' => [
                'description' => 'Rocznik do',
                'type' => 'integer',
            ],
            'price_from' => [
                'description' => 'Cena od (najniższa cena miesięczna)',
                'type' => 'number',
            ],
            'price_to' => [
                'description' => 'Cena do (najniższa cena miesięczna)',
                'type' => 'number',
            ],

            // ========================================
            // FILTRY DOSTĘPNOŚCI
            // ========================================
            'available_only' => [
                'description' => 'Tylko dostępne samochody (nie zarezerwowane)',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
            'show_reserved' => [
                'description' => 'Pokaż także zarezerwowane samochody',
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
            'available_immediately' => [
                'description' => 'Tylko samochody dostępne od ręki',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
        ];
    }
}











