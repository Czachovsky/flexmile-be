<?php
namespace FlexMile\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import przykładowych danych
 * UPDATED: body_type i fuel_type są teraz zapisywane jako META POLA
 */
class Sample_Data_Importer {

    public function __construct() {
        add_action('admin_post_flexmile_import_sample_data', [$this, 'import_sample_data']);
    }

    /**
     * Ładuje konfigurację z pliku JSON
     */
    private function load_config() {
        $config_file = FLEXMILE_PLUGIN_DIR . 'config.json';

        if (!file_exists($config_file)) {
            return null;
        }

        $json = file_get_contents($config_file);
        $config = json_decode($json, true);

        return $config;
    }

    /**
     * Importuje przykładowe dane
     * UPDATED: Nie importujemy już taksonomii body_type i fuel_type
     */
    public function import_sample_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        check_admin_referer('flexmile_import_sample_data', 'flexmile_nonce');

        $results = [
            'offers' => 0,
        ];

        // Import przykładowych samochodów
        $results['offers'] = $this->import_offers();

        // Przekieruj z komunikatem
        $message = sprintf(
            'Imported: %d sample offers',
            $results['offers']
        );

        wp_redirect(add_query_arg([
            'page' => 'flexmile',
            'import' => 'success',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Importuje przykładowe samochody
     * UPDATED: body_type i fuel_type jako meta pola
     */
    private function import_offers() {
        $offers = [
            [
                'title' => 'BMW X5 3.0d xDrive',
                'description' => 'Luksusowy SUV premium z napędem na cztery koła. Doskonały stan techniczny, bogate wyposażenie. Idealny do długich tras i rodzinnych wyjazdów. Samochód serwisowany w ASO, kompletna historia przeglądów.',
                'brand_slug' => 'bmw',
                'model' => 'X5',
                'body_type' => 'SUV',
                'fuel_type' => 'Diesel',
                'year' => 2022,
                'mileage' => 45000,
                'horsepower' => 286,
                'engine_capacity' => 2993,
                'transmission' => 'automatic',
                'color' => 'Czarny metalik',
                'seats' => 5,
                'vin' => 'WBAKR810501A23456',
                'lowest_price' => 2800.00,
                'pricing_config' => [
                    'rental_periods' => [12, 24, 36, 48],
                    'mileage_limits' => [10000, 15000, 20000],
                    'prices' => [
                        '12_10000' => 2800.00,
                        '12_15000' => 2900.00,
                        '12_20000' => 3000.00,
                        '24_10000' => 2600.00,
                        '24_15000' => 2700.00,
                        '24_20000' => 2800.00,
                        '36_10000' => 2400.00,
                        '36_15000' => 2500.00,
                        '36_20000' => 2600.00,
                        '48_10000' => 2200.00,
                        '48_15000' => 2300.00,
                        '48_20000' => 2400.00,
                    ]
                ],
                'attributes' => [
                    'new' => true,
                    'available_immediately' => true,
                    'popular' => true,
                    'featured' => true,
                ],
            ],
            [
                'title' => 'Toyota Corolla 1.8 Hybrid',
                'description' => 'Ekonomiczny sedan hybrydowy o niskim spalaniu (4.5l/100km). Idealne auto do miasta i trasy. Bezawaryjny napęd hybrydowy, cicha kabina, niskie koszty eksploatacji. Jeden właściciel, ASO.',
                'brand_slug' => 'toyota',
                'model' => 'Corolla',
                'body_type' => 'Sedan',
                'fuel_type' => 'Hybrid',
                'year' => 2023,
                'mileage' => 28000,
                'horsepower' => 122,
                'engine_capacity' => 1798,
                'transmission' => 'automatic',
                'color' => 'Srebrny',
                'seats' => 5,
                'vin' => 'NMTBB6EE3NR023789',
                'lowest_price' => 1900.00,
                'pricing_config' => [
                    'rental_periods' => [12, 24, 36, 48],
                    'mileage_limits' => [10000, 15000, 20000],
                    'prices' => [
                        '12_10000' => 1900.00,
                        '12_15000' => 2000.00,
                        '12_20000' => 2100.00,
                        '24_10000' => 1800.00,
                        '24_15000' => 1900.00,
                        '24_20000' => 2000.00,
                        '36_10000' => 1700.00,
                        '36_15000' => 1800.00,
                        '36_20000' => 1900.00,
                        '48_10000' => 1600.00,
                        '48_15000' => 1700.00,
                        '48_20000' => 1800.00,
                    ]
                ],
                'attributes' => [
                    'new' => true,
                    'available_immediately' => true,
                    'popular' => false,
                    'featured' => false,
                ],
            ],
            [
                'title' => 'Volkswagen Golf 1.5 TSI',
                'description' => 'Klasyczny hatchback z segmentu C. Sprawdzony silnik benzynowy 1.5 TSI o mocy 150 KM. Oszczędny, dynamiczny i praktyczny. Wyposażenie: klimatyzacja, nawigacja, czujniki parkowania, kamera cofania.',
                'brand_slug' => 'volkswagen',
                'model' => 'Golf',
                'body_type' => 'Hatchback',
                'fuel_type' => 'Petrol',
                'year' => 2021,
                'mileage' => 62000,
                'horsepower' => 150,
                'engine_capacity' => 1498,
                'transmission' => 'manual',
                'color' => 'Biały',
                'seats' => 5,
                'vin' => 'WVWZZZ1KZMW012345',
                'lowest_price' => 1600.00,
                'pricing_config' => [
                    'rental_periods' => [12, 24, 36, 48],
                    'mileage_limits' => [10000, 15000, 20000],
                    'prices' => [
                        '12_10000' => 1600.00,
                        '12_15000' => 1700.00,
                        '12_20000' => 1800.00,
                        '24_10000' => 1500.00,
                        '24_15000' => 1600.00,
                        '24_20000' => 1700.00,
                        '36_10000' => 1400.00,
                        '36_15000' => 1500.00,
                        '36_20000' => 1600.00,
                        '48_10000' => 1300.00,
                        '48_15000' => 1400.00,
                        '48_20000' => 1500.00,
                    ]
                ],
                'attributes' => [
                    'new' => false,
                    'available_immediately' => false,
                    'popular' => true,
                    'featured' => false,
                ],
            ],
        ];

        $count = 0;
        foreach ($offers as $offer) {
            // Sprawdź czy nie istnieje
            $existing = get_posts([
                'post_type' => 'offer',
                'title' => $offer['title'],
                'posts_per_page' => 1,
            ]);

            if (empty($existing)) {
                // Utwórz post
                $post_id = wp_insert_post([
                    'post_type' => 'offer',
                    'post_title' => $offer['title'],
                    'post_content' => $offer['description'],
                    'post_status' => 'publish',
                ]);

                if ($post_id && !is_wp_error($post_id)) {
                    // Zapisz markę i model jako meta pola
                    update_post_meta($post_id, '_car_brand_slug', $offer['brand_slug']);
                    update_post_meta($post_id, '_car_model', $offer['model']);

                    // NOWOŚĆ: body_type i fuel_type jako META POLA (nie taksonomie)
                    update_post_meta($post_id, '_body_type', $offer['body_type']);
                    update_post_meta($post_id, '_fuel_type', $offer['fuel_type']);

                    // Dodaj meta pola
                    update_post_meta($post_id, '_year', $offer['year']);
                    update_post_meta($post_id, '_mileage', $offer['mileage']);
                    update_post_meta($post_id, '_horsepower', $offer['horsepower']);
                    update_post_meta($post_id, '_engine_capacity', $offer['engine_capacity']);
                    update_post_meta($post_id, '_transmission', $offer['transmission']);
                    update_post_meta($post_id, '_color', $offer['color']);
                    update_post_meta($post_id, '_seats', $offer['seats']);
                    update_post_meta($post_id, '_vin_number', $offer['vin']);
                    update_post_meta($post_id, '_lowest_price', $offer['lowest_price']);
                    update_post_meta($post_id, '_pricing_config', $offer['pricing_config']);
                    update_post_meta($post_id, '_reservation_active', '0');

                    // Dodaj flagi
                    if (isset($offer['attributes'])) {
                        update_post_meta($post_id, '_new_car', $offer['attributes']['new'] ? '1' : '0');
                        update_post_meta($post_id, '_available_immediately', $offer['attributes']['available_immediately'] ? '1' : '0');
                        update_post_meta($post_id, '_most_popular', $offer['attributes']['popular'] ? '1' : '0');
                        update_post_meta($post_id, '_featured', $offer['attributes']['featured'] ? '1' : '0');
                        update_post_meta($post_id, '_coming_soon', '0');
                    }

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Sprawdza czy przykładowe dane już istnieją
     */
    public static function has_sample_data() {
        $offers = wp_count_posts('offer');
        return ($offers && $offers->publish > 0);
    }
}
