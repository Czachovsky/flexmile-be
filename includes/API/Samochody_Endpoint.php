<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Samochodów
 */
class Samochody_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'samochody';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpointy API
     */
    public function register_routes() {
        // Lista samochodów z filtrowaniem
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_samochody'],
            'permission_callback' => '__return_true',
            'args' => $this->get_collection_params(),
        ]);

        // Pojedynczy samochód
        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_samochod'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Pobiera listę samochodów z filtrowaniem
     */
    public function get_samochody($request) {
        $params = $request->get_params();

        // Parametry query
        $args = [
            'post_type' => 'samochod',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1,
            'orderby' => isset($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date',
            'order' => isset($params['order']) ? sanitize_text_field($params['order']) : 'DESC',
            'meta_query' => [],
            'tax_query' => [],
        ];

        // Ukryj zarezerwowane samochody (opcjonalnie)
        if (!isset($params['show_reserved']) || $params['show_reserved'] !== 'true') {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_rezerwacja_aktywna',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_rezerwacja_aktywna',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }

        // Sortowanie - wyróżnione na górze
        if (isset($params['sort_featured']) && $params['sort_featured'] === 'true') {
            $args['meta_key'] = '_wyrozniany';
            $args['orderby'] = ['meta_value' => 'DESC', 'date' => 'DESC'];
        }

        // Filtr po fladze "wyróżniony"
        if (isset($params['wyrozniany']) && $params['wyrozniany'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_wyrozniany',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Filtr po fladze "nowy samochód"
        if (isset($params['nowy']) && $params['nowy'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_nowy_samochod',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Filtr po fladze "dostępny od ręki"
        if (isset($params['od_reki']) && $params['od_reki'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_dostepny_od_reki',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Filtr po fladze "najczęściej wybierany"
        if (isset($params['popularne']) && $params['popularne'] === 'true') {
            $args['meta_query'][] = [
                'key' => '_najczesciej_wybierany',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Filtr po marce
        if (!empty($params['marka'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'marka_samochodu',
                'field' => 'slug',
                'terms' => sanitize_text_field($params['marka']),
            ];
        }

        // Filtr po typie nadwozia
        if (!empty($params['typ_nadwozia'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'typ_nadwozia',
                'field' => 'slug',
                'terms' => sanitize_text_field($params['typ_nadwozia']),
            ];
        }

        // Filtr po rodzaju paliwa
        if (!empty($params['paliwo'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'rodzaj_paliwa',
                'field' => 'slug',
                'terms' => sanitize_text_field($params['paliwo']),
            ];
        }

        // Filtr po rocznikach (od-do)
        if (!empty($params['rocznik_od']) || !empty($params['rocznik_do'])) {
            $rocznik_query = ['key' => '_rocznik', 'type' => 'NUMERIC'];

            if (!empty($params['rocznik_od'])) {
                $rocznik_query['value'] = intval($params['rocznik_od']);
                $rocznik_query['compare'] = '>=';
            }

            if (!empty($params['rocznik_do'])) {
                if (!empty($params['rocznik_od'])) {
                    $rocznik_query['value'] = [intval($params['rocznik_od']), intval($params['rocznik_do'])];
                    $rocznik_query['compare'] = 'BETWEEN';
                } else {
                    $rocznik_query['value'] = intval($params['rocznik_do']);
                    $rocznik_query['compare'] = '<=';
                }
            }

            $args['meta_query'][] = $rocznik_query;
        }

        // Filtr po przebiegu (maksymalny)
        if (!empty($params['przebieg_max'])) {
            $args['meta_query'][] = [
                'key' => '_przebieg',
                'value' => intval($params['przebieg_max']),
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        // Filtr po cenie (od-do)
        if (!empty($params['cena_od']) || !empty($params['cena_do'])) {
            $cena_query = ['key' => '_cena_bazowa', 'type' => 'NUMERIC'];

            if (!empty($params['cena_od']) && !empty($params['cena_do'])) {
                $cena_query['value'] = [floatval($params['cena_od']), floatval($params['cena_do'])];
                $cena_query['compare'] = 'BETWEEN';
            } elseif (!empty($params['cena_od'])) {
                $cena_query['value'] = floatval($params['cena_od']);
                $cena_query['compare'] = '>=';
            } else {
                $cena_query['value'] = floatval($params['cena_do']);
                $cena_query['compare'] = '<=';
            }

            $args['meta_query'][] = $cena_query;
        }

        // Wykonaj query
        $query = new \WP_Query($args);

        // Przygotuj odpowiedź
        $samochody = [];
        foreach ($query->posts as $post) {
            $samochody[] = $this->prepare_samochod_data($post);
        }

        // Nagłówki dla infinite scroll
        $response = new \WP_REST_Response($samochody);
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

        if (!$post || $post->post_type !== 'samochod') {
            return new \WP_Error('not_found', 'Samochód nie został znaleziony', ['status' => 404]);
        }

        return $this->prepare_samochod_data($post);
    }

    /**
     * Przygotowuje dane samochodu do API
     */
    private function prepare_samochod_data($post) {
        // Podstawowe dane
        $data = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
        ];

        // Zdjęcie
        $data['photo'] = get_the_post_thumbnail_url($post->ID, 'large') ?: null;

        // Typ paliwa
        $paliwo = wp_get_post_terms($post->ID, 'rodzaj_paliwa');
        $data['fuel'] = !empty($paliwo) ? $paliwo[0]->name : '';

        // Skrzynia biegów
        $data['transmission'] = get_post_meta($post->ID, '_skrzynia', true) ?: '';

        // Konie mechaniczne
        $data['power'] = (int) get_post_meta($post->ID, '_moc', true);

        // Atrybuty (flagi statusu)
        $data['attributes'] = [
            'new' => get_post_meta($post->ID, '_nowy_samochod', true) === '1',
            'available' => get_post_meta($post->ID, '_dostepny_od_reki', true) === '1',
            'soon' => get_post_meta($post->ID, '_dostepny_wkrotce', true) === '1',
            'hot' => get_post_meta($post->ID, '_najczesciej_wybierany', true) === '1',
        ];

        // Wyróżnienie
        $data['featured'] = get_post_meta($post->ID, '_wyrozniany', true) === '1';

        return $data;
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
            'marka' => [
                'description' => 'Filtr po marce (slug)',
                'type' => 'string',
            ],
            'typ_nadwozia' => [
                'description' => 'Filtr po typie nadwozia (slug)',
                'type' => 'string',
            ],
            'paliwo' => [
                'description' => 'Filtr po rodzaju paliwa (slug)',
                'type' => 'string',
            ],
            'rocznik_od' => [
                'description' => 'Rocznik od',
                'type' => 'integer',
            ],
            'rocznik_do' => [
                'description' => 'Rocznik do',
                'type' => 'integer',
            ],
            'przebieg_max' => [
                'description' => 'Maksymalny przebieg',
                'type' => 'integer',
            ],
            'cena_od' => [
                'description' => 'Cena od',
                'type' => 'number',
            ],
            'cena_do' => [
                'description' => 'Cena do',
                'type' => 'number',
            ],
            'show_reserved' => [
                'description' => 'Pokaż zarezerwowane samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
            'sort_featured' => [
                'description' => 'Sortuj wyróżnione na górze',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
            'wyrozniany' => [
                'description' => 'Tylko wyróżnione samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
            ],
            'nowy' => [
                'description' => 'Tylko nowe samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
            ],
            'od_reki' => [
                'description' => 'Tylko dostępne od ręki',
                'type' => 'string',
                'enum' => ['true', 'false'],
            ],
            'popularne' => [
                'description' => 'Tylko najczęściej wybierane',
                'type' => 'string',
                'enum' => ['true', 'false'],
            ],
        ];
    }
}
