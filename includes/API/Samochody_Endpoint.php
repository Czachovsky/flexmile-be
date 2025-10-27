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

        // Lista TYLKO zarezerwowanych samochodów
        register_rest_route(self::NAMESPACE, '/' . self::BASE . '/zarezerwowane', [
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

        // Filtrowanie zarezerwowanych samochodów
        if (isset($params['only_reserved']) && $params['only_reserved'] === 'true') {
            // TYLKO zarezerwowane
            $args['meta_query'][] = [
                'key' => '_rezerwacja_aktywna',
                'value' => '1',
                'compare' => '=',
            ];
        } elseif (!isset($params['show_reserved']) || $params['show_reserved'] !== 'true') {
            // Ukryj zarezerwowane (domyślnie)
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

        // Przygotuj odpowiedź - dla listy używamy LIGHTWEIGHT wersji (szybsze ładowanie)
        $samochody = [];
        foreach ($query->posts as $post) {
            $samochody[] = $this->prepare_samochod_data_minimal($post);
        }

        // Przygotuj pełny response z meta danymi
        $response_data = [
            'samochody' => $samochody,
            'meta' => [
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'current_page' => intval($params['page'] ?? 1),
                'per_page' => intval($params['per_page'] ?? 10),
            ],
        ];

        $response = new \WP_REST_Response($response_data);

        // Nagłówki dla backward compatibility
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
            'post_type' => 'samochod',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_rezerwacja_aktywna',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ];

        $query = new \WP_Query($args);

        // Również lightweight dla listy zarezerwowanych
        $samochody = [];
        foreach ($query->posts as $post) {
            $samochody[] = $this->prepare_samochod_data_minimal($post);
        }

        $response_data = [
            'samochody' => $samochody,
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

        if (!$post || $post->post_type !== 'samochod') {
            return new \WP_Error('not_found', 'Samochód nie został znaleziony', ['status' => 404]);
        }

        // Pojedynczy samochód zwraca PEŁNE DANE
        return $this->prepare_samochod_data($post);
    }

    /**
     * NOWA METODA: Przygotowuje MINIMALNE dane samochodu dla listy (lightweight)
     *
     * Zawiera tylko:
     * - id, nazwa, slug
     * - grafika (thumbnail + main)
     * - podstawowe parametry (silnik, paliwo, skrzynia, KM, marka)
     * - ceny
     * - atrybuty/flagi
     * - status dostępności
     */
    private function prepare_samochod_data_minimal($post) {
        $data = [
            'id' => $post->ID,
            'nazwa' => $post->post_title,
            'slug' => $post->post_name,
        ];

        // Zdjęcia - tylko główne (nie cała galeria!)
        $data['grafika'] = [
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'medium' => get_the_post_thumbnail_url($post->ID, 'medium'),
            'large' => get_the_post_thumbnail_url($post->ID, 'large'),
        ];

        // Podstawowe parametry techniczne
        $data['silnik'] = get_post_meta($post->ID, '_silnik', true) ?: 'Brak danych';
        $data['moc'] = (int) get_post_meta($post->ID, '_moc', true);
        $data['skrzynia'] = get_post_meta($post->ID, '_skrzynia', true);
        $data['rocznik'] = (int) get_post_meta($post->ID, '_rocznik', true);
        $data['przebieg'] = (int) get_post_meta($post->ID, '_przebieg', true);

        // Marka (uproszczona)
        $marka = wp_get_post_terms($post->ID, 'marka_samochodu');
        $data['marka'] = !empty($marka) ? [
            'id' => $marka[0]->term_id,
            'nazwa' => $marka[0]->name,
            'slug' => $marka[0]->slug,
        ] : null;

        // Typ nadwozia (uproszczony)
        $typ_nadwozia = wp_get_post_terms($post->ID, 'typ_nadwozia');
        $data['typ_nadwozia'] = !empty($typ_nadwozia) ? [
            'nazwa' => $typ_nadwozia[0]->name,
            'slug' => $typ_nadwozia[0]->slug,
        ] : null;

        // Rodzaj paliwa (uproszczony)
        $paliwo = wp_get_post_terms($post->ID, 'rodzaj_paliwa');
        $data['paliwo'] = !empty($paliwo) ? [
            'nazwa' => $paliwo[0]->name,
            'slug' => $paliwo[0]->slug,
        ] : null;

        // Ceny
        $data['ceny'] = [
            'cena_bazowa' => (float) get_post_meta($post->ID, '_cena_bazowa', true),
            'cena_za_km' => (float) get_post_meta($post->ID, '_cena_za_km', true),
        ];

        // ATRYBUTY/FLAGI - To jest to, czego chciałeś!
        $data['atrybuty'] = [
            'nowy' => get_post_meta($post->ID, '_nowy_samochod', true) === '1',
            'od_reki' => get_post_meta($post->ID, '_dostepny_od_reki', true) === '1',
            'wkrotce' => get_post_meta($post->ID, '_dostepny_wkrotce', true) === '1',
            'popularne' => get_post_meta($post->ID, '_najczesciej_wybierany', true) === '1',
            'wyrozniany' => get_post_meta($post->ID, '_wyrozniany', true) === '1',
        ];

        // Status dostępności
        $data['dostepny'] = get_post_meta($post->ID, '_rezerwacja_aktywna', true) !== '1';

        return $data;
    }

    /**
     * Przygotowuje PEŁNE dane samochodu (dla pojedynczego widoku)
     */
    private function prepare_samochod_data($post) {
        // Podstawowe dane
        $data = [
            'id' => $post->ID,
            'nazwa' => $post->post_title,
            'opis' => $post->post_content,
            'slug' => $post->post_name,
        ];

        // Zdjęcia
        $data['obrazek_glowny'] = get_the_post_thumbnail_url($post->ID, 'large');
        $data['miniaturka'] = get_the_post_thumbnail_url($post->ID, 'thumbnail');

        // Galeria (PEŁNA)
        $gallery_ids = get_post_meta($post->ID, '_galeria', true);
        $data['galeria'] = [];
        if ($gallery_ids) {
            foreach (explode(',', $gallery_ids) as $img_id) {
                if ($img_id) {
                    $data['galeria'][] = [
                        'id' => (int) $img_id,
                        'url' => wp_get_attachment_url($img_id),
                        'thumbnail' => wp_get_attachment_image_url($img_id, 'thumbnail'),
                        'medium' => wp_get_attachment_image_url($img_id, 'medium'),
                        'large' => wp_get_attachment_image_url($img_id, 'large'),
                    ];
                }
            }
        }

        // Parametry techniczne (WSZYSTKIE)
        $data['parametry'] = [
            'rocznik' => (int) get_post_meta($post->ID, '_rocznik', true),
            'przebieg' => (int) get_post_meta($post->ID, '_przebieg', true),
            'silnik' => get_post_meta($post->ID, '_silnik', true),
            'moc' => (int) get_post_meta($post->ID, '_moc', true),
            'pojemnosc' => (int) get_post_meta($post->ID, '_pojemnosc', true),
            'skrzynia' => get_post_meta($post->ID, '_skrzynia', true),
            'naped' => get_post_meta($post->ID, '_naped', true),
            'kolor' => get_post_meta($post->ID, '_kolor', true),
            'liczba_miejsc' => (int) get_post_meta($post->ID, '_liczba_miejsc', true),
            'liczba_drzwi' => (int) get_post_meta($post->ID, '_liczba_drzwi', true),
            'numer_vin' => get_post_meta($post->ID, '_numer_vin', true),
        ];

        // Taksonomie (PEŁNE)
        $marka = wp_get_post_terms($post->ID, 'marka_samochodu');
        $data['marka'] = !empty($marka) ? [
            'id' => $marka[0]->term_id,
            'nazwa' => $marka[0]->name,
            'slug' => $marka[0]->slug,
        ] : null;

        $typ_nadwozia = wp_get_post_terms($post->ID, 'typ_nadwozia');
        $data['typ_nadwozia'] = !empty($typ_nadwozia) ? [
            'id' => $typ_nadwozia[0]->term_id,
            'nazwa' => $typ_nadwozia[0]->name,
            'slug' => $typ_nadwozia[0]->slug,
        ] : null;

        $paliwo = wp_get_post_terms($post->ID, 'rodzaj_paliwa');
        $data['paliwo'] = !empty($paliwo) ? [
            'id' => $paliwo[0]->term_id,
            'nazwa' => $paliwo[0]->name,
            'slug' => $paliwo[0]->slug,
        ] : null;

        // Ceny
        $data['ceny'] = [
            'cena_bazowa' => (float) get_post_meta($post->ID, '_cena_bazowa', true),
            'cena_za_km' => (float) get_post_meta($post->ID, '_cena_za_km', true),
        ];

        // Wyposażenie standardowe (PEŁNE)
        $wyposazenie_std_raw = get_post_meta($post->ID, '_wyposazenie_standardowe', true);
        $data['wyposazenie_standardowe'] = $this->parse_textarea_to_array($wyposazenie_std_raw);

        // Wyposażenie dodatkowe (PEŁNE)
        $wyposazenie_dod_raw = get_post_meta($post->ID, '_wyposazenie_dodatkowe', true);
        $data['wyposazenie_dodatkowe'] = $this->parse_textarea_to_array($wyposazenie_dod_raw);

        // ATRYBUTY/FLAGI
        $data['atrybuty'] = [
            'nowy' => get_post_meta($post->ID, '_nowy_samochod', true) === '1',
            'od_reki' => get_post_meta($post->ID, '_dostepny_od_reki', true) === '1',
            'wkrotce' => get_post_meta($post->ID, '_dostepny_wkrotce', true) === '1',
            'popularne' => get_post_meta($post->ID, '_najczesciej_wybierany', true) === '1',
            'wyrozniany' => get_post_meta($post->ID, '_wyrozniany', true) === '1',
        ];

        // Status rezerwacji
        $data['dostepny'] = get_post_meta($post->ID, '_rezerwacja_aktywna', true) !== '1';

        return $data;
    }

    /**
     * Parsuje textarea na tablicę (każda nowa linia to element)
     */
    private function parse_textarea_to_array($text) {
        if (empty($text)) {
            return [];
        }

        // Rozdziel po nowych liniach
        $lines = preg_split('/\r\n|\r|\n/', $text);

        // Trim każdej linii i usuń puste
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function($line) {
            return !empty($line);
        });

        // Zwróć jako indeksowaną tablicę
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
            'only_reserved' => [
                'description' => 'Zwróć TYLKO zarezerwowane samochody',
                'type' => 'string',
                'enum' => ['true', 'false'],
                'default' => 'false',
            ],
        ];
    }
}
