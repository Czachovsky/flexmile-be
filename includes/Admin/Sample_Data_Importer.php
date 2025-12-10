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
        
        // CSV Import - tylko jeśli włączony
        if (defined('FLEXMILE_CSV_IMPORT_ENABLED') && FLEXMILE_CSV_IMPORT_ENABLED === true) {
            add_action('admin_post_flexmile_import_csv', [$this, 'import_csv']);
        }
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
     * Generuje 100 ofert do testów
     */
    private function import_offers() {
        // Dane do generowania różnorodnych ofert
        $brands = [
            ['slug' => 'bmw', 'name' => 'BMW', 'models' => ['X5', 'X3', '3 Series', '5 Series', 'X1']],
            ['slug' => 'toyota', 'name' => 'Toyota', 'models' => ['Corolla', 'Camry', 'RAV4', 'Prius', 'Yaris']],
            ['slug' => 'volkswagen', 'name' => 'Volkswagen', 'models' => ['Golf', 'Passat', 'Tiguan', 'Polo', 'Touareg']],
            ['slug' => 'audi', 'name' => 'Audi', 'models' => ['A4', 'A6', 'Q5', 'Q7', 'A3']],
            ['slug' => 'mercedes', 'name' => 'Mercedes-Benz', 'models' => ['C-Class', 'E-Class', 'GLC', 'GLE', 'A-Class']],
            ['slug' => 'ford', 'name' => 'Ford', 'models' => ['Focus', 'Mondeo', 'Kuga', 'Fiesta', 'Edge']],
            ['slug' => 'skoda', 'name' => 'Škoda', 'models' => ['Octavia', 'Superb', 'Kodiaq', 'Kamiq', 'Fabia']],
            ['slug' => 'hyundai', 'name' => 'Hyundai', 'models' => ['i30', 'Tucson', 'Kona', 'Elantra', 'Santa Fe']],
            ['slug' => 'peugeot', 'name' => 'Peugeot', 'models' => ['308', '3008', '208', '508', '5008']],
            ['slug' => 'opel', 'name' => 'Opel', 'models' => ['Astra', 'Insignia', 'Crossland', 'Grandland', 'Corsa']],
        ];
        
        $body_types = ['SUV', 'Sedan', 'Hatchback', 'Kombi', 'Coupe'];
        $fuel_types = ['diesel', 'petrol', 'hybrid', 'electric'];
        $transmissions = ['automatic', 'manual'];
        $colors = ['Czarny metalik', 'Biały', 'Srebrny', 'Szary', 'Niebieski', 'Czerwony', 'Zielony'];
        
        $offers = [];
        
        // Generuj 100 ofert
        for ($i = 1; $i <= 100; $i++) {
            $brand = $brands[($i - 1) % count($brands)];
            $model = $brand['models'][($i - 1) % count($brand['models'])];
            $body_type = $body_types[($i - 1) % count($body_types)];
            $fuel_type = $fuel_types[($i - 1) % count($fuel_types)];
            $transmission = $transmissions[($i - 1) % count($transmissions)];
            $color = $colors[($i - 1) % count($colors)];
            
            // Losowe wartości
            $year = rand(2019, 2024);
            $horsepower = rand(100, 400);
            $engine_capacity = rand(1200, 3500);
            $seats = rand(4, 7);
            
            // Generuj oznaczenie silnika
            $engine = $this->generate_engine_designation($brand['slug'], $fuel_type, $engine_capacity, $horsepower);
            
            // Cena bazowa zależna od marki i roku
            $base_price = rand(1200, 3500);
            $lowest_price = $base_price;
            
            // Generuj pricing_config
            $pricing_config = [
                'rental_periods' => [12, 24, 36, 48],
                'mileage_limits' => [10000, 15000, 20000],
                'prices' => []
            ];
            
            foreach ([12, 24, 36, 48] as $period) {
                foreach ([10000, 15000, 20000] as $mileage_limit) {
                    $price_key = $period . '_' . $mileage_limit;
                    // Cena maleje z okresem i rośnie z limitem przebiegu
                    $price = $base_price - ($period / 12 * 200) + ($mileage_limit / 10000 * 100);
                    $pricing_config['prices'][$price_key] = round($price, 2);
                    if ($price < $lowest_price) {
                        $lowest_price = round($price, 2);
                    }
                }
            }
            
            // Atrybuty (losowe)
            $is_new = ($i % 3 == 0);
            $is_available = ($i % 4 != 0);
            $is_popular = ($i % 5 == 0);
            
            $offers[] = [
                'title' => $brand['name'] . ' ' . $model . ' ' . ($engine_capacity / 1000) . ($fuel_type === 'Diesel' ? 'd' : ($fuel_type === 'Electric' ? 'e' : 'i')),
                'brand_slug' => $brand['slug'],
                'model' => $model,
                'body_type' => $body_type,
                'fuel_type' => $fuel_type,
                'year' => $year,
                'horsepower' => $horsepower,
                'engine_capacity' => $engine_capacity,
                'engine' => $engine,
                'transmission' => $transmission,
                'color' => $color,
                'seats' => $seats,
                'lowest_price' => $lowest_price,
                'pricing_config' => $pricing_config,
                'attributes' => [
                    'new' => $is_new,
                    'available_immediately' => $is_available,
                    'popular' => $is_popular,
                ],
            ];
        }

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
                    'post_content' => '',
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
                    update_post_meta($post_id, '_horsepower', $offer['horsepower']);
                    update_post_meta($post_id, '_engine_capacity', $offer['engine_capacity']);
                    update_post_meta($post_id, '_engine', $offer['engine']);
                    update_post_meta($post_id, '_transmission', $offer['transmission']);
                    update_post_meta($post_id, '_color', $offer['color']);
                    update_post_meta($post_id, '_seats', $offer['seats']);
                    update_post_meta($post_id, '_lowest_price', $offer['lowest_price']);
                    update_post_meta($post_id, '_pricing_config', $offer['pricing_config']);
                    update_post_meta($post_id, '_reservation_active', '0');

                    // Dodaj flagi
                    if (isset($offer['attributes'])) {
                        update_post_meta($post_id, '_new_car', $offer['attributes']['new'] ? '1' : '0');
                        update_post_meta($post_id, '_available_immediately', $offer['attributes']['available_immediately'] ? '1' : '0');
                        update_post_meta($post_id, '_most_popular', $offer['attributes']['popular'] ? '1' : '0');
                        update_post_meta($post_id, '_coming_soon', '0');
                        delete_post_meta($post_id, '_coming_soon_date');
                    }

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Generuje oznaczenie silnika na podstawie marki, paliwa i parametrów
     */
    private function generate_engine_designation($brand_slug, $fuel_type, $engine_capacity, $horsepower) {
        $capacity_liters = round($engine_capacity / 1000, 1);
        
        // Mapowanie oznaczeń silników dla różnych marek
        $engine_patterns = [
            'bmw' => [
                'diesel' => ['{capacity}d', '{capacity}d xDrive', '{capacity}d sDrive'],
                'petrol' => ['{capacity}i', '{capacity}i xDrive', '{capacity}i sDrive'],
                'hybrid' => ['{capacity}i eDrive', '{capacity}i xDrive eDrive'],
                'electric' => ['eDrive', 'xDrive eDrive'],
            ],
            'toyota' => [
                'diesel' => ['{capacity} D-4D', '{capacity} D-CAT'],
                'petrol' => ['{capacity} VVT-i', '{capacity} Dual VVT-i'],
                'hybrid' => ['{capacity} Hybrid', '{capacity} Hybrid AWD'],
                'electric' => ['e-TNGA', 'Electric'],
            ],
            'volkswagen' => [
                'diesel' => ['{capacity} TDI', '{capacity} TDI 4MOTION'],
                'petrol' => ['{capacity} TSI', '{capacity} TSI 4MOTION'],
                'hybrid' => ['{capacity} eTSI', '{capacity} eTSI 4MOTION'],
                'electric' => ['e-Golf', 'ID.'],
            ],
            'audi' => [
                'diesel' => ['{capacity} TDI', '{capacity} TDI quattro'],
                'petrol' => ['{capacity} TFSI', '{capacity} TFSI quattro'],
                'hybrid' => ['{capacity} e-tron', '{capacity} e-tron quattro'],
                'electric' => ['e-tron', 'e-tron quattro'],
            ],
            'mercedes' => [
                'diesel' => ['{capacity} d', '{capacity} d 4MATIC'],
                'petrol' => ['{capacity}', '{capacity} 4MATIC'],
                'hybrid' => ['{capacity} EQ Power', '{capacity} EQ Power 4MATIC'],
                'electric' => ['EQ', 'EQ 4MATIC'],
            ],
            'ford' => [
                'diesel' => ['{capacity} TDCi', '{capacity} TDCi AWD'],
                'petrol' => ['{capacity} EcoBoost', '{capacity} EcoBoost AWD'],
                'hybrid' => ['{capacity} Hybrid', '{capacity} Hybrid AWD'],
                'electric' => ['Electric', 'Electric AWD'],
            ],
            'skoda' => [
                'diesel' => ['{capacity} TDI', '{capacity} TDI 4x4'],
                'petrol' => ['{capacity} TSI', '{capacity} TSI 4x4'],
                'hybrid' => ['{capacity} eTSI', '{capacity} eTSI 4x4'],
                'electric' => ['e-', 'Electric'],
            ],
            'hyundai' => [
                'diesel' => ['{capacity} CRDi', '{capacity} CRDi AWD'],
                'petrol' => ['{capacity} GDI', '{capacity} GDI AWD'],
                'hybrid' => ['{capacity} Hybrid', '{capacity} Hybrid AWD'],
                'electric' => ['Electric', 'Electric AWD'],
            ],
            'peugeot' => [
                'diesel' => ['{capacity} HDi', '{capacity} BlueHDi'],
                'petrol' => ['{capacity} PureTech', '{capacity} PureTech EAT8'],
                'hybrid' => ['{capacity} Hybrid', '{capacity} Hybrid EAT8'],
                'electric' => ['e-', 'Electric'],
            ],
            'opel' => [
                'diesel' => ['{capacity} CDTI', '{capacity} CDTI 4x4'],
                'petrol' => ['{capacity} Turbo', '{capacity} Turbo 4x4'],
                'hybrid' => ['{capacity} Hybrid', '{capacity} Hybrid 4x4'],
                'electric' => ['Electric', 'Electric 4x4'],
            ],
        ];
        
        // Domyślne wzorce jeśli marka nie jest w mapowaniu
        $default_patterns = [
            'diesel' => ['{capacity} TDI', '{capacity} D'],
            'petrol' => ['{capacity} TSI', '{capacity}'],
            'hybrid' => ['{capacity} Hybrid', '{capacity} HEV'],
            'electric' => ['Electric', 'e-'],
        ];
        
        // Wybierz wzorce dla marki lub użyj domyślnych
        $patterns = isset($engine_patterns[$brand_slug][$fuel_type]) 
            ? $engine_patterns[$brand_slug][$fuel_type]
            : ($default_patterns[$fuel_type] ?? ['{capacity}']);
        
        // Wybierz losowy wzorzec
        $pattern = $patterns[array_rand($patterns)];
        
        // Zastąp placeholder pojemnością
        $engine = str_replace('{capacity}', $capacity_liters, $pattern);
        
        return $engine;
    }

    /**
     * Sprawdza czy przykładowe dane już istnieją
     */
    public static function has_sample_data() {
        $offers = wp_count_posts('offer');
        return ($offers && $offers->publish > 0);
    }

    /**
     * Importuje oferty z pliku CSV
     */
    public function import_csv() {
        // Sprawdź czy funkcjonalność jest włączona
        if (!defined('FLEXMILE_CSV_IMPORT_ENABLED') || FLEXMILE_CSV_IMPORT_ENABLED !== true) {
            wp_die('Import CSV jest wyłączony. Skontaktuj się z administratorem.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        check_admin_referer('flexmile_import_csv', 'flexmile_csv_nonce');

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg([
                'page' => 'flexmile',
                'import' => 'error',
                'message' => urlencode('Błąd podczas przesyłania pliku. Upewnij się, że wybrałeś plik CSV.')
            ], admin_url('admin.php')));
            exit;
        }

        $file = $_FILES['csv_file'];
        
        // Sprawdź typ pliku
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            wp_redirect(add_query_arg([
                'page' => 'flexmile',
                'import' => 'error',
                'message' => urlencode('Plik musi mieć rozszerzenie .csv')
            ], admin_url('admin.php')));
            exit;
        }

        // Otwórz plik
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            wp_redirect(add_query_arg([
                'page' => 'flexmile',
                'import' => 'error',
                'message' => urlencode('Nie można otworzyć pliku CSV')
            ], admin_url('admin.php')));
            exit;
        }

        // Wczytaj nagłówki
        $headers = fgetcsv($handle, 0, ',');
        if ($headers === false) {
            fclose($handle);
            wp_redirect(add_query_arg([
                'page' => 'flexmile',
                'import' => 'error',
                'message' => urlencode('Nie można odczytać nagłówków z pliku CSV')
            ], admin_url('admin.php')));
            exit;
        }

        // Normalizuj nagłówki (usuń BOM, spacje, małe litery)
        $headers = array_map(function($header) {
            $header = trim($header);
            // Usuń BOM jeśli istnieje
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            return strtolower($header);
        }, $headers);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Wczytaj dane
        $line_number = 1;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $line_number++;
            
            if (count($row) !== count($headers)) {
                $errors[] = "Linia {$line_number}: Nieprawidłowa liczba kolumn";
                $skipped++;
                continue;
            }

            // Stwórz tablicę asocjacyjną
            $data = array_combine($headers, $row);
            
            // Waliduj wymagane pola
            if (empty($data['title']) || empty($data['car_brand_slug']) || empty($data['car_model'])) {
                $errors[] = "Linia {$line_number}: Brakuje wymaganych pól (title, car_brand_slug, car_model)";
                $skipped++;
                continue;
            }

            // Sprawdź czy oferta już istnieje
            $existing = get_posts([
                'post_type' => 'offer',
                'title' => sanitize_text_field($data['title']),
                'posts_per_page' => 1,
            ]);

            if (!empty($existing)) {
                $skipped++;
                continue;
            }

            // Utwórz post
            $post_id = wp_insert_post([
                'post_type' => 'offer',
                'post_title' => sanitize_text_field($data['title']),
                'post_content' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
                'post_status' => 'publish',
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                // Zapisz podstawowe pola
                update_post_meta($post_id, '_car_brand_slug', sanitize_text_field($data['car_brand_slug']));
                update_post_meta($post_id, '_car_model', sanitize_text_field($data['car_model']));
                
                if (isset($data['body_type'])) {
                    update_post_meta($post_id, '_body_type', sanitize_text_field($data['body_type']));
                }
                
                if (isset($data['fuel_type'])) {
                    update_post_meta($post_id, '_fuel_type', sanitize_text_field($data['fuel_type']));
                }

                // Zapisz pola numeryczne
                if (isset($data['year'])) {
                    update_post_meta($post_id, '_year', intval($data['year']));
                }
                
                if (isset($data['horsepower'])) {
                    update_post_meta($post_id, '_horsepower', intval($data['horsepower']));
                }
                
                if (isset($data['engine_capacity'])) {
                    update_post_meta($post_id, '_engine_capacity', intval($data['engine_capacity']));
                }
                
                if (isset($data['seats'])) {
                    update_post_meta($post_id, '_seats', intval($data['seats']));
                }

                // Zapisz pola tekstowe
                if (isset($data['engine'])) {
                    update_post_meta($post_id, '_engine', sanitize_text_field($data['engine']));
                }
                
                if (isset($data['transmission'])) {
                    update_post_meta($post_id, '_transmission', sanitize_text_field($data['transmission']));
                }
                
                if (isset($data['drivetrain'])) {
                    update_post_meta($post_id, '_drivetrain', sanitize_text_field($data['drivetrain']));
                }
                
                if (isset($data['color'])) {
                    update_post_meta($post_id, '_color', sanitize_text_field($data['color']));
                }
                
                if (isset($data['doors'])) {
                    update_post_meta($post_id, '_doors', sanitize_text_field($data['doors']));
                }

                // Konfiguracja cen
                $pricing_config = [
                    'rental_periods' => [12, 24, 36, 48],
                    'mileage_limits' => [10000, 15000, 20000],
                    'prices' => []
                ];

                // Jeśli podano okresy i limity
                if (isset($data['rental_periods'])) {
                    $periods = array_map('intval', array_filter(explode(',', $data['rental_periods'])));
                    if (!empty($periods)) {
                        $pricing_config['rental_periods'] = $periods;
                    }
                }

                if (isset($data['mileage_limits'])) {
                    $limits = array_map('intval', array_filter(explode(',', $data['mileage_limits'])));
                    if (!empty($limits)) {
                        $pricing_config['mileage_limits'] = $limits;
                    }
                }

                // Sprawdź czy są podane poszczególne ceny w kolumnach (format: price_PERIOD_LIMIT)
                $has_individual_prices = false;
                foreach ($data as $key => $value) {
                    if (preg_match('/^price_(\d+)_(\d+)$/i', $key, $matches)) {
                        $period = intval($matches[1]);
                        $limit = intval($matches[2]);
                        $price_key = $period . '_' . $limit;
                        $price_value = floatval($value);
                        
                        if ($price_value > 0) {
                            $pricing_config['prices'][$price_key] = $price_value;
                            $has_individual_prices = true;
                            
                            // Dodaj okres i limit do listy jeśli jeszcze ich nie ma
                            if (!in_array($period, $pricing_config['rental_periods'])) {
                                $pricing_config['rental_periods'][] = $period;
                            }
                            if (!in_array($limit, $pricing_config['mileage_limits'])) {
                                $pricing_config['mileage_limits'][] = $limit;
                            }
                        }
                    }
                }
                
                // Sortuj okresy i limity
                sort($pricing_config['rental_periods']);
                sort($pricing_config['mileage_limits']);

                // Jeśli podano macierz cen (JSON) i nie ma indywidualnych cen
                if (!$has_individual_prices && isset($data['price_matrix']) && !empty($data['price_matrix'])) {
                    $price_matrix = json_decode($data['price_matrix'], true);
                    if (is_array($price_matrix)) {
                        $pricing_config['prices'] = $price_matrix;
                    }
                } elseif (!$has_individual_prices) {
                    // Generuj ceny na podstawie lowest_price jeśli podano
                    if (isset($data['lowest_price']) && !empty($data['lowest_price'])) {
                        $base_price = floatval($data['lowest_price']);
                        foreach ($pricing_config['rental_periods'] as $period) {
                            foreach ($pricing_config['mileage_limits'] as $mileage_limit) {
                                $price_key = $period . '_' . $mileage_limit;
                                if (!isset($pricing_config['prices'][$price_key])) {
                                    $price = $base_price - ($period / 12 * 200) + ($mileage_limit / 10000 * 100);
                                    $pricing_config['prices'][$price_key] = round($price, 2);
                                }
                            }
                        }
                    }
                }

                update_post_meta($post_id, '_pricing_config', $pricing_config);

                // Najniższa cena
                if (isset($data['lowest_price'])) {
                    update_post_meta($post_id, '_lowest_price', floatval($data['lowest_price']));
                } else {
                    $min_price = !empty($pricing_config['prices']) ? min($pricing_config['prices']) : 0;
                    update_post_meta($post_id, '_lowest_price', $min_price);
                }

                // Flagi
                update_post_meta($post_id, '_reservation_active', '0');
                update_post_meta($post_id, '_new_car', isset($data['new_car']) && ($data['new_car'] === '1' || strtolower($data['new_car']) === 'true') ? '1' : '0');
                update_post_meta($post_id, '_available_immediately', isset($data['available_immediately']) && ($data['available_immediately'] === '1' || strtolower($data['available_immediately']) === 'true') ? '1' : '0');
                update_post_meta($post_id, '_most_popular', isset($data['most_popular']) && ($data['most_popular'] === '1' || strtolower($data['most_popular']) === 'true') ? '1' : '0');
                
                if (isset($data['coming_soon']) && ($data['coming_soon'] === '1' || strtolower($data['coming_soon']) === 'true')) {
                    update_post_meta($post_id, '_coming_soon', '1');
                    if (isset($data['coming_soon_date'])) {
                        update_post_meta($post_id, '_coming_soon_date', sanitize_text_field($data['coming_soon_date']));
                    }
                } else {
                    update_post_meta($post_id, '_coming_soon', '0');
                    delete_post_meta($post_id, '_coming_soon_date');
                }

                // Wyposażenie
                if (isset($data['standard_equipment'])) {
                    update_post_meta($post_id, '_standard_equipment', sanitize_textarea_field($data['standard_equipment']));
                }
                
                if (isset($data['additional_equipment'])) {
                    update_post_meta($post_id, '_additional_equipment', sanitize_textarea_field($data['additional_equipment']));
                }

                $imported++;
            } else {
                $errors[] = "Linia {$line_number}: Nie można utworzyć oferty";
                $skipped++;
            }
        }

        fclose($handle);

        // Przygotuj komunikat
        $message = sprintf(
            'Zaimportowano: %d ofert. Pominięto: %d.',
            $imported,
            $skipped
        );

        if (!empty($errors) && count($errors) <= 10) {
            $message .= ' Błędy: ' . implode('; ', array_slice($errors, 0, 10));
        } elseif (!empty($errors)) {
            $message .= ' Wystąpiło ' . count($errors) . ' błędów.';
        }

        wp_redirect(add_query_arg([
            'page' => 'flexmile',
            'import' => $imported > 0 ? 'success' : 'error',
            'message' => urlencode($message)
        ], admin_url('admin.php')));
        exit;
    }
}
