<?php
namespace FlexMile\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import przykładowych danych do systemu
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
     */
    public function import_sample_data() {
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        // Sprawdź nonce
        check_admin_referer('flexmile_import_sample_data', 'flexmile_nonce');

        $results = [
            'marki' => 0,
            'typy_nadwozia' => 0,
            'paliwa' => 0,
            'samochody' => 0,
        ];

        // Import marek
        $results['marki'] = $this->import_marki();

        // Import typów nadwozia
        $results['typy_nadwozia'] = $this->import_typy_nadwozia();

        // Import rodzajów paliwa
        $results['paliwa'] = $this->import_paliwa();

        // Import przykładowych samochodów
        $results['samochody'] = $this->import_samochody();

        // Przekieruj z komunikatem
        $message = sprintf(
            'Zaimportowano: %d marek, %d typów nadwozia, %d rodzajów paliwa, %d samochodów',
            $results['marki'],
            $results['typy_nadwozia'],
            $results['paliwa'],
            $results['samochody']
        );

        wp_redirect(add_query_arg([
            'page' => 'flexmile',
            'import' => 'success',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Importuje marki samochodów
     */
    private function import_marki() {
        $config = $this->load_config();

        // Użyj marek z config.json jeśli dostępne
        if ($config && isset($config['marki'])) {
            $marki = $config['marki'];
        } else {
            // Fallback - hardkodowane marki
            $marki = [
                'Audi', 'BMW', 'Mercedes-Benz', 'Volkswagen', 'Toyota',
                'Honda', 'Ford', 'Opel', 'Peugeot', 'Renault',
                'Skoda', 'Seat', 'Fiat', 'Alfa Romeo', 'Volvo',
                'Mazda', 'Nissan', 'Hyundai', 'Kia', 'Lexus',
                'Porsche', 'Tesla', 'Land Rover', 'Jaguar', 'Mini',
                'Chevrolet', 'Subaru', 'Mitsubishi', 'Suzuki', 'Dacia'
            ];
        }

        $count = 0;
        foreach ($marki as $marka) {
            $existing = term_exists($marka, 'marka_samochodu');
            if (!$existing) {
                $result = wp_insert_term($marka, 'marka_samochodu');
                if (!is_wp_error($result)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Importuje typy nadwozia
     */
    private function import_typy_nadwozia() {
        $config = $this->load_config();

        // Użyj typów z config.json jeśli dostępne
        if ($config && isset($config['typy_nadwozia'])) {
            $typy = $config['typy_nadwozia'];
        } else {
            // Fallback - hardkodowane typy
            $typy = [
                'Sedan',
                'Kombi',
                'SUV',
                'Hatchback',
                'Coupe',
                'Cabrio',
                'Minivan',
                'Pickup',
                'Kompakt',
                'Sportowy'
            ];
        }

        $count = 0;
        foreach ($typy as $typ) {
            $existing = term_exists($typ, 'typ_nadwozia');
            if (!$existing) {
                $result = wp_insert_term($typ, 'typ_nadwozia');
                if (!is_wp_error($result)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Importuje rodzaje paliwa
     */
    private function import_paliwa() {
        $config = $this->load_config();

        // Użyj paliw z config.json jeśli dostępne
        if ($config && isset($config['rodzaje_paliwa'])) {
            $paliwa = $config['rodzaje_paliwa'];
        } else {
            // Fallback - hardkodowane paliwa
            $paliwa = [
                'Benzyna',
                'Diesel',
                'Hybryda',
                'Elektryczny',
                'Benzyna + LPG',
                'Benzyna + CNG',
                'Plug-in Hybrid'
            ];
        }

        $count = 0;
        foreach ($paliwa as $paliwo) {
            $existing = term_exists($paliwo, 'rodzaj_paliwa');
            if (!$existing) {
                $result = wp_insert_term($paliwo, 'rodzaj_paliwa');
                if (!is_wp_error($result)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Importuje przykładowe samochody
     */
    private function import_samochody() {
        $samochody = [
            [
                'nazwa' => 'BMW X5 3.0d xDrive',
                'opis' => 'Luksusowy SUV premium z napędem na cztery koła. Doskonały stan techniczny, bogate wyposażenie. Idealny do długich tras i rodzinnych wyjazdów. Samochód serwisowany w ASO, kompletna historia przeglądów.',
                'marka' => 'BMW',
                'typ' => 'SUV',
                'paliwo' => 'Diesel',
                'rocznik' => 2022,
                'przebieg' => 45000,
                'moc' => 286,
                'pojemnosc' => 2993,
                'skrzynia' => 'automatic',
                'kolor' => 'Czarny metalik',
                'miejsca' => 5,
                'vin' => 'WBAKR810501A23456',
                'cena_bazowa' => 2800.00,
                'cena_za_km' => 0.60,
                'flagi' => [
                    'nowy' => true,
                    'od_reki' => true,
                    'popularne' => true,
                    'wyrozniany' => true,
                ],
            ],
            [
                'nazwa' => 'Toyota Corolla 1.8 Hybrid',
                'opis' => 'Ekonomiczny sedan hybrydowy o niskim spalaniu (4.5l/100km). Idealne auto do miasta i trasy. Bezawaryjny napęd hybrydowy, cicha kabina, niskie koszty eksploatacji. Jeden właściciel, ASO.',
                'marka' => 'Toyota',
                'typ' => 'Sedan',
                'paliwo' => 'Hybryda',
                'rocznik' => 2023,
                'przebieg' => 28000,
                'moc' => 122,
                'pojemnosc' => 1798,
                'skrzynia' => 'automatic',
                'kolor' => 'Srebrny',
                'miejsca' => 5,
                'vin' => 'NMTBB6EE3NR023789',
                'cena_bazowa' => 1900.00,
                'cena_za_km' => 0.40,
                'flagi' => [
                    'nowy' => true,
                    'od_reki' => true,
                    'popularne' => false,
                    'wyrozniany' => false,
                ],
            ],
            [
                'nazwa' => 'Volkswagen Golf 1.5 TSI',
                'opis' => 'Klasyczny hatchback z segmentu C. Sprawdzony silnik benzynowy 1.5 TSI o mocy 150 KM. Oszczędny, dynamiczny i praktyczny. Wyposażenie: klimatyzacja, nawigacja, czujniki parkowania, kamera cofania.',
                'marka' => 'Volkswagen',
                'typ' => 'Hatchback',
                'paliwo' => 'Benzyna',
                'rocznik' => 2021,
                'przebieg' => 62000,
                'moc' => 150,
                'pojemnosc' => 1498,
                'skrzynia' => 'manual',
                'kolor' => 'Biały',
                'miejsca' => 5,
                'vin' => 'WVWZZZ1KZMW012345',
                'cena_bazowa' => 1600.00,
                'cena_za_km' => 0.35,
                'flagi' => [
                    'nowy' => false,
                    'od_reki' => false,
                    'popularne' => true,
                    'wyrozniany' => false,
                ],
            ],
        ];

        $count = 0;
        foreach ($samochody as $auto) {
            // Sprawdź czy nie istnieje
            $existing = get_posts([
                'post_type' => 'samochod',
                'title' => $auto['nazwa'],
                'posts_per_page' => 1,
            ]);

            if (empty($existing)) {
                // Utwórz post
                $post_id = wp_insert_post([
                    'post_type' => 'samochod',
                    'post_title' => $auto['nazwa'],
                    'post_content' => $auto['opis'],
                    'post_status' => 'publish',
                ]);

                if ($post_id && !is_wp_error($post_id)) {
                    // Dodaj taksonomie
                    $marka_term = term_exists($auto['marka'], 'marka_samochodu');
                    if ($marka_term) {
                        wp_set_object_terms($post_id, (int)$marka_term['term_id'], 'marka_samochodu');
                    }

                    $typ_term = term_exists($auto['typ'], 'typ_nadwozia');
                    if ($typ_term) {
                        wp_set_object_terms($post_id, (int)$typ_term['term_id'], 'typ_nadwozia');
                    }

                    $paliwo_term = term_exists($auto['paliwo'], 'rodzaj_paliwa');
                    if ($paliwo_term) {
                        wp_set_object_terms($post_id, (int)$paliwo_term['term_id'], 'rodzaj_paliwa');
                    }

                    // Dodaj meta pola
                    update_post_meta($post_id, '_rocznik', $auto['rocznik']);
                    update_post_meta($post_id, '_przebieg', $auto['przebieg']);
                    update_post_meta($post_id, '_moc', $auto['moc']);
                    update_post_meta($post_id, '_pojemnosc', $auto['pojemnosc']);
                    update_post_meta($post_id, '_skrzynia', $auto['skrzynia']);
                    update_post_meta($post_id, '_kolor', $auto['kolor']);
                    update_post_meta($post_id, '_liczba_miejsc', $auto['miejsca']);
                    update_post_meta($post_id, '_numer_vin', $auto['vin']);
                    update_post_meta($post_id, '_cena_bazowa', $auto['cena_bazowa']);
                    update_post_meta($post_id, '_cena_za_km', $auto['cena_za_km']);
                    update_post_meta($post_id, '_rezerwacja_aktywna', '0');

                    // Dodaj flagi jeśli są
                    if (isset($auto['flagi'])) {
                        update_post_meta($post_id, '_nowy_samochod', $auto['flagi']['nowy'] ? '1' : '0');
                        update_post_meta($post_id, '_dostepny_od_reki', $auto['flagi']['od_reki'] ? '1' : '0');
                        update_post_meta($post_id, '_najczesciej_wybierany', $auto['flagi']['popularne'] ? '1' : '0');
                        update_post_meta($post_id, '_wyrozniany', $auto['flagi']['wyrozniany'] ? '1' : '0');
                        update_post_meta($post_id, '_dostepny_wkrotce', '0');
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
        $marki = get_terms([
            'taxonomy' => 'marka_samochodu',
            'hide_empty' => false,
            'count' => true,
        ]);

        $samochody = wp_count_posts('samochod');

        return (!empty($marki) && count($marki) > 5) || ($samochody && $samochody->publish > 0);
    }
}
