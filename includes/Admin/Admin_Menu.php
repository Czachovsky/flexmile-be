<?php
namespace FlexMile\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zarządzanie menu administratora
 */
class Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        // Sprzątanie domyślnego menu WordPress po dodaniu własnego
        add_action('admin_menu', [$this, 'cleanup_admin_menu'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Obsługa zapisu ustawień domeny frontendowej
        add_action('admin_post_flexmile_save_frontend_domain', [$this, 'save_frontend_domain']);
        
        // Obsługa zapisu ustawień SMTP
        add_action('admin_post_flexmile_save_smtp_settings', [$this, 'save_smtp_settings']);
    }

    /**
     * Dodaje menu wtyczki
     */
    public function add_admin_menu() {
        add_menu_page(
            'FlexMile',
            'FlexMile',
            'manage_options',
            'flexmile',
            [$this, 'render_dashboard'],
            'dashicons-car',
            30
        );

        add_submenu_page(
            'flexmile',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'flexmile',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'flexmile',
            'API Settings',
            'API Settings',
            'manage_options',
            'flexmile-api',
            [$this, 'render_api_settings']
        );

        add_submenu_page(
            'flexmile',
            'Bannery',
            'Bannery',
            'manage_options',
            'flexmile-banners',
            [$this, 'render_banners']
        );

        add_submenu_page(
            'flexmile',
            'Ustawienia Email',
            'Ustawienia Email',
            'manage_options',
            'flexmile-email-settings',
            [$this, 'render_email_settings']
        );
    }

    /**
     * Usuwa wybrane domyślne pozycje z menu WP
     */
    public function cleanup_admin_menu() {
        // Posty (Posts)
        remove_menu_page('edit.php');
        // Strony (Pages)
        remove_menu_page('edit.php?post_type=page');
        // Komentarze (Comments)
        remove_menu_page('edit-comments.php');
        // Wygląd (Appearance)
        remove_menu_page('themes.php');
    }

    /**
     * Renderuje dashboard
     */
    public function render_dashboard() {
        // Komunikat sukcesu po imporcie
        if (isset($_GET['import']) && $_GET['import'] === 'success') {
            $notice_message = isset($_GET['message'])
                ? sanitize_text_field(wp_unslash($_GET['message']))
                : '';
            $notice_message = $notice_message !== ''
                ? $notice_message
                : __('Operacja zakończyła się powodzeniem.', 'flexmile');
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Success!</strong> <?php echo esc_html($notice_message); ?></p>
            </div>
            <?php
        }

        // Komunikat błędu po imporcie
        if (isset($_GET['import']) && $_GET['import'] === 'error') {
            $notice_message = isset($_GET['message'])
                ? sanitize_text_field(wp_unslash($_GET['message']))
                : '';
            $notice_message = $notice_message !== ''
                ? $notice_message
                : __('Wystąpił błąd podczas importu.', 'flexmile');
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>❌ Błąd!</strong> <?php echo esc_html($notice_message); ?></p>
            </div>
            <?php
        }

        // Statystyki
        $total_offers = wp_count_posts('offer');
        $total_reservations = wp_count_posts('reservation');

        $approved_count = 0;
        $rejected_count = 0;

        $reservations = get_posts([
            'post_type' => 'reservation',
            'posts_per_page' => -1,
        ]);

        foreach ($reservations as $res) {
            $status = get_post_meta($res->ID, '_status', true);
            if (empty($status)) {
                $status = 'approved';
            }
            if ($status === 'approved') $approved_count++;
            if ($status === 'rejected') $rejected_count++;
        }

        // Sprawdź czy są już przykładowe dane
        $has_sample_data = \FlexMile\Admin\Sample_Data_Importer::has_sample_data();

        // Policz marki z config.json
        $config_file = FLEXMILE_PLUGIN_DIR . 'config.json';
        $brands_count = 0;
        if (file_exists($config_file)) {
            $config = json_decode(file_get_contents($config_file), true);
            $brands_count = isset($config['brands']) ? count($config['brands']) : 0;
        }

        ?>
        <div class="wrap">
            <h1>FlexMile Dashboard</h1>

            <div class="flexmile-dashboard">
                <div class="flexmile-info">
                    <?php if (!$has_sample_data): ?>
                    <div class="flexmile-import-box">
                        <h2>🎯 Rozpocznij szybko!</h2>
                        <p>Nie masz jeszcze żadnych danych? Zaimportuj przykładowe dane aby przetestować system:</p>
                        <ul style="margin: 15px 0;">
                            <li>✅ <strong><?php echo $brands_count; ?> marek</strong> z modelami (z pliku config.json)</li>
                            <li>✅ <strong>10 typów nadwozia</strong> (SUV, Sedan, Kombi...)</li>
                            <li>✅ <strong>7 rodzajów paliwa</strong> (Benzyna, Diesel, Hybryda...)</li>
                            <li>✅ <strong>3 przykładowe samochody</strong> z pełnymi danymi</li>
                        </ul>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
                            <input type="hidden" name="action" value="flexmile_import_sample_data">
                            <?php wp_nonce_field('flexmile_import_sample_data', 'flexmile_nonce'); ?>
                            <button type="submit" class="button button-primary button-hero" style="background: #00a32a; border-color: #00a32a;">
                                Importuj przykładowe dane
                            </button>
                        </form>
                        <p style="color: rgba(255,255,255,0.8); font-size: 12px; margin-top: 10px;">
                            Import nie nadpisze istniejących danych. Możesz go uruchomić bezpiecznie.
                        </p>
                    </div>
                    <hr style="margin: 30px 0;">
                    <?php endif; ?>

                    <h2>Szybki start</h2>
                    <ul>
                        <li><a href="<?php echo admin_url('post-new.php?post_type=offer'); ?>">Dodaj nową ofertę</a></li>
                        <li><a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>">Zarządzaj rezerwacjami</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=flexmile-api'); ?>">Ustawienia API</a></li>
                    </ul>

                    <?php if (defined('FLEXMILE_CSV_IMPORT_ENABLED') && FLEXMILE_CSV_IMPORT_ENABLED === true): ?>
                    <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1; margin: 20px 0;">
                        <h3 style="margin-top: 0;">Import ofert z pliku CSV</h3>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="flexmile_import_csv">
                            <?php wp_nonce_field('flexmile_import_csv', 'flexmile_csv_nonce'); ?>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="file" name="csv_file" accept=".csv" required style="flex: 1; padding: 8px;">
                                <button type="submit" class="button button-primary">
                                    Importuj CSV
                                </button>
                            </div>
                            <p class="description" style="margin-top: 10px; font-size: 12px; color: #666;">
                                Format CSV: pierwsza linia to nagłówki kolumn. Wymagane kolumny: <code>title</code>, <code>car_brand_slug</code>, <code>car_model</code>.<br>
                                <a href="#" onclick="event.preventDefault(); document.getElementById('csv-help').style.display = document.getElementById('csv-help').style.display === 'none' ? 'block' : 'none'; return false;">Zobacz pełną listę dostępnych kolumn</a>
                            </p>
                            <div id="csv-help" style="display: none; margin-top: 15px; padding: 15px; background: white; border-radius: 5px; font-size: 12px;">
                                <strong>Dostępne kolumny w CSV:</strong>
                                <ul style="margin: 10px 0; padding-left: 20px;">
                                    <li><strong>Wymagane:</strong> title, car_brand_slug, car_model</li>
                                    <li><strong>Opcjonalne:</strong> body_type, fuel_type, year, horsepower, engine_capacity, engine, transmission, drivetrain, color, seats, doors</li>
                                    <li><strong>Ceny (3 sposoby):</strong>
                                        <ul style="margin-top: 5px;">
                                            <li><strong>Zalecane:</strong> Indywidualne kolumny <code>price_PERIOD_LIMIT</code> (np. price_12_10000, price_24_15000)</li>
                                            <li>Macierz cen: <code>price_matrix</code> (JSON)</li>
                                            <li>Auto-generowanie: <code>lowest_price</code> + <code>rental_periods</code> + <code>mileage_limits</code></li>
                                        </ul>
                                    </li>
                                    <li><strong>Flagi:</strong> new_car (1/0), available_immediately (1/0), most_popular (1/0), coming_soon (1/0), coming_soon_date (YYYY-MM-DD)</li>
                                    <li><strong>Inne:</strong> description, standard_equipment, additional_equipment</li>
                                </ul>
                                <p style="margin-top: 10px;"><strong>Przykład z indywidualnymi cenami (ZALECANE):</strong></p>
                                <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px;">title,car_brand_slug,car_model,body_type,fuel_type,year,horsepower,transmission,price_12_10000,price_12_15000,price_24_10000,price_24_15000
BMW X5 3.0d,bmw,X5,SUV,diesel,2022,286,automatic,2200,2250,2100,2150</pre>
                                <p style="margin-top: 10px;"><strong>Przykład z auto-generowaniem cen:</strong></p>
                                <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px;">title,car_brand_slug,car_model,body_type,fuel_type,year,horsepower,transmission,lowest_price,rental_periods,mileage_limits
BMW X5 3.0d,bmw,X5,SUV,diesel,2022,286,automatic,2200,"12,24,36,48","10000,15000,20000"</pre>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;">
                        <h3 style="margin-top: 0;">Zarządzanie markami i modelami</h3>
                        <p>Marki i modele są teraz przechowywane w pliku <code>config.json</code> w katalogu wtyczki.</p>
                        <p>Aby dodać/edytować marki lub modele, edytuj plik:</p>
                        <code><?php echo FLEXMILE_PLUGIN_DIR; ?>config.json</code>
                        <p style="margin-top: 10px; font-size: 13px; color: #856404;">
                            Po edycji pliku, zmiany będą natychmiast widoczne w panelu admina i w API.
                        </p>
                    </div>

                    <h3>REST API Endpoints</h3>
                    <p>Twoja aplikacja Angular powinna używać następujących endpointów:</p>
                    <ul class="api-endpoints">
                        <li>
                            <code>GET <?php echo rest_url('flexmile/v1/offers'); ?></code>
                            <span>Lista ofert z filtrowaniem</span>
                        </li>
                        <li>
                            <code>GET <?php echo rest_url('flexmile/v1/offers/brands'); ?></code>
                            <span>Lista dostępnych marek</span>
                        </li>
                        <li>
                            <code>GET <?php echo rest_url('flexmile/v1/offers/brands/{slug}/models'); ?></code>
                            <span>Modele dla wybranej marki</span>
                        </li>
                        <li>
                            <code>GET <?php echo rest_url('flexmile/v1/offers/{id}'); ?></code>
                            <span>Pojedyncza oferta</span>
                        </li>
                        <li>
                            <code>POST <?php echo rest_url('flexmile/v1/reservations'); ?></code>
                            <span>Nowa rezerwacja</span>
                        </li>
                        <li>
                            <code>GET <?php echo rest_url('flexmile/v1/banners'); ?></code>
                            <span>Lista bannerów (max 3)</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
            .flexmile-dashboard {
                display: grid;
                gap: 20px;
                margin-top: 20px;
            }
            .flexmile-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            .stat-box {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                text-align: center;
            }
            .stat-box h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
            }
            .stat-number {
                font-size: 36px;
                font-weight: bold;
                margin: 10px 0;
                color: #2271b1;
            }
            .flexmile-info {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .flexmile-info h2 {
                margin-top: 0;
            }
            .flexmile-info ul {
                list-style: none;
                padding: 0;
            }
            .flexmile-info ul li {
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .flexmile-import-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 25px;
                border-radius: 12px;
                color: white;
                margin-bottom: 20px;
            }
            .flexmile-import-box h2 {
                color: white;
                margin-top: 0;
            }
            .flexmile-import-box p {
                color: rgba(255,255,255,0.95);
            }
            .flexmile-import-box ul {
                list-style: none;
                padding: 0;
            }
            .flexmile-import-box ul li {
                padding: 8px 0;
                border-bottom: 1px solid rgba(255,255,255,0.2);
                color: white;
            }
            .flexmile-import-box ul li:last-child {
                border-bottom: none;
            }
            .api-endpoints code {
                background: #f5f5f5;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 13px;
                color: #d63638;
            }
            .api-endpoints span {
                color: #666;
                font-size: 13px;
                margin-left: 10px;
            }
        </style>
        <?php
    }

    /**
     * Renderuje ustawienia API
     */
    public function render_api_settings() {
        // Obsługa zapisu formularza domeny frontendowej
        if (isset($_POST['flexmile_save_frontend_domain']) && check_admin_referer('flexmile_frontend_domain_nonce')) {
            $domain = isset($_POST['frontend_domain']) ? esc_url_raw(trim($_POST['frontend_domain'])) : '';
            // Usuń końcowy slash jeśli istnieje
            $domain = rtrim($domain, '/');
            update_option('flexmile_frontend_domain', $domain);
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Sukces!</strong> Domena frontendowa została zapisana.</p>
            </div>
            <?php
        }

        $frontend_domain = get_option('flexmile_frontend_domain', '');
        ?>
        <div class="wrap">
            <h1>Ustawienia FlexMile API</h1>

            <div class="flexmile-api-info" style="margin-bottom: 20px;">
                <h2>Domena Frontendowa</h2>
                <p>Ustaw domenę frontendową (np. https://flexmile.mr-creations.pl), aby móc korzystać z przycisku "Zobacz ofertę" w edycji oferty.</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="flexmile_save_frontend_domain">
                    <?php wp_nonce_field('flexmile_frontend_domain_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="frontend_domain">Domena frontendowa</label>
                            </th>
                            <td>
                                <input 
                                    type="url" 
                                    id="frontend_domain" 
                                    name="frontend_domain" 
                                    value="<?php echo esc_attr($frontend_domain); ?>" 
                                    class="regular-text"
                                    placeholder="https://flexmile.mr-creations.pl"
                                />
                                <p class="description">Pełny URL do aplikacji frontendowej (bez końcowego slasha).</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="flexmile_save_frontend_domain" class="button button-primary" value="Zapisz domenę" />
                    </p>
                </form>
            </div>

            <div class="flexmile-api-info">
                <h2>Konfiguracja CORS</h2>
                <h3>Dodaj do pliku <code>wp-config.php</code> lub <code>functions.php</code>:</h3>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>// Enable CORS for headless WordPress
add_action('init', function() {
    header('Access-Control-Allow-Origin: *'); // Change * to Angular domain in production
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
});</code></pre>

<h3>Wszystkie dostępne filtry:</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Parametr</th>
                    <th>Typ</th>
                    <th>Opis</th>
                </tr>
            </thead>
            <tbody>
                <!-- MARKA I MODEL -->
                <tr style="background: #f0f9ff;">
                    <td colspan="3"><strong>Marka i model</strong></td>
                </tr>
                <tr>
                    <td><code>car_brand</code></td>
                    <td>string</td>
                    <td>Marka (slug, np. "bmw", "toyota")</td>
                </tr>
                <tr>
                    <td><code>car_model</code></td>
                    <td>string</td>
                    <td>Model (np. "X5", "Corolla")</td>
                </tr>

                <!-- PARAMETRY TECHNICZNE -->
                <tr style="background: #f0f9ff;">
                    <td colspan="3"><strong>Parametry techniczne</strong></td>
                </tr>
                <tr>
                    <td><code>body_type</code></td>
                    <td>string</td>
                    <td>Typ nadwozia (np. "SUV", "Sedan", "Hatchback")</td>
                </tr>
                <tr>
                    <td><code>fuel_type</code></td>
                    <td>string</td>
                    <td>Rodzaj paliwa (np. "Diesel", "Petrol", "Hybrid", "Electric")</td>
                </tr>
                <tr>
                    <td><code>transmission</code></td>
                    <td>enum</td>
                    <td> Typ skrzyni: "manual" lub "automatic"</td>
                </tr>

                <!-- ZAKRES WARTOŚCI -->
                <tr style="background: #f0f9ff;">
                    <td colspan="3"><strong>Zakresy wartości</strong></td>
                </tr>
                <tr>
                    <td><code>year_from</code></td>
                    <td>integer</td>
                    <td>Rocznik od (np. 2020)</td>
                </tr>
                <tr>
                    <td><code>year_to</code></td>
                    <td>integer</td>
                    <td>Rocznik do (np. 2023)</td>
                </tr>
                <tr>
                    <td><code>price_from</code></td>
                    <td>float</td>
                    <td>Cena minimalna w zł/mies. (np. 1500)</td>
                </tr>
                <tr>
                    <td><code>price_to</code></td>
                    <td>float</td>
                    <td>Cena maksymalna w zł/mies. (np. 3000)</td>
                </tr>

                <!-- DOSTĘPNOŚĆ -->
                <tr style="background: #f0f9ff;">
                    <td colspan="3"><strong>Dostępność</strong></td>
                </tr>
                <tr>
                    <td><code>available_only</code></td>
                    <td>boolean</td>
                    <td>Tylko dostępne (nie zarezerwowane): "true" lub "false"</td>
                </tr>
                <tr>
                    <td><code>show_reserved</code></td>
                    <td>boolean</td>
                    <td>Pokaż także zarezerwowane: "true" lub "false"</td>
                </tr>
                <tr>
                    <td><code>only_reserved</code></td>
                    <td>boolean</td>
                    <td>Tylko zarezerwowane: "true" lub "false"</td>
                </tr>

                <!-- PAGINACJA -->
                <tr style="background: #f0f9ff;">
                    <td colspan="3"><strong>Paginacja i sortowanie</strong></td>
                </tr>
                <tr>
                    <td><code>page</code></td>
                    <td>integer</td>
                    <td>Numer strony (infinite scroll, domyślnie: 1)</td>
                </tr>
                <tr>
                    <td><code>per_page</code></td>
                    <td>integer</td>
                    <td>Liczba wyników na stronę (max 100, domyślnie: 10)</td>
                </tr>
                <tr>
                    <td><code>orderby</code></td>
                    <td>enum</td>
                    <td>Sortowanie: "date" lub "price" (domyślnie: "date")</td>
                </tr>
                <tr>
                    <td><code>order</code></td>
                    <td>enum</td>
                    <td>Kierunek: "ASC" lub "DESC" (domyślnie: "DESC")</td>
                </tr>
            </tbody>
        </table>

        <h3>Przykładowe zapytania:</h3>
        <ul class="api-examples">
            <li>
                <strong>Wszystkie dostępne oferty:</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?available_only=true'); ?></code>
            </li>
            <li>
                <strong>Lista wszystkich marek:</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers/brands'); ?></code>
            </li>
            <li>
                <strong>Modele BMW:</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers/brands/bmw/models'); ?></code>
            </li>
            <li>
                <strong>BMW X5 automatyczne z lat 2020-2023:</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?car_brand=bmw&car_model=X5&transmission=automatic&year_from=2020&year_to=2023&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Tanie SUV-y (do 2000 zł):</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?body_type=SUV&price_to=2000&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Diesle z manualną skrzynią:</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?fuel_type=Diesel&transmission=manual&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Nowe hybrydy (2022+):</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?fuel_type=Hybrid&year_from=2022&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Wszystkie Toyoty (dostępne):</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?car_brand=toyota&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Zaawansowane filtrowanie (SUV, Diesel, auto, 2020+, max 50k, 2000-3000 zł):</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?body_type=SUV&fuel_type=Diesel&transmission=automatic&year_from=2020&price_from=2000&price_to=3000&available_only=true'); ?></code>
            </li>
            <li>
                <strong>Infinite scroll (strona 2, 20 wyników):</strong><br>
                <code><?php echo rest_url('flexmile/v1/offers?page=2&per_page=20&available_only=true'); ?></code>
            </li>
        </ul>

        <div style="background: #d1fae5; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; margin: 20px 0;">
            <ul style="margin-bottom: 0;">
                <li><strong>Case-sensitivity:</strong>
                    <ul>
                        <li><code>car_brand</code>: małe litery (bmw, toyota)</li>
                        <li><code>car_model</code>: dokładnie jak w config.json (X5, Corolla)</li>
                        <li><code>fuel_type</code>: pierwsza wielka (Diesel, Hybrid)</li>
                        <li><code>body_type</code>: pierwsza wielka (SUV, Sedan)</li>
                        <li><code>transmission</code>: małe litery (manual, automatic)</li>
                    </ul>
                </li>
            </ul>
        </div>

        <style>
            .flexmile-api-info {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                max-width: 900px;
            }
            .flexmile-api-info h2 {
                margin-top: 0;
            }
            .flexmile-api-info pre {
                max-width: 100%;
            }
            .api-examples {
                list-style: none;
                padding: 0;
            }
            .api-examples li {
                margin-bottom: 15px;
                padding: 15px;
                background: #f9f9f9;
                border-left: 3px solid #2271b1;
            }
            .api-examples code {
                display: block;
                margin-top: 5px;
                word-break: break-all;
            }
        </style>
        <?php
    }

    /**
     * Renderuje stronę zarządzania bannerami
     */
    public function render_banners() {
        // Obsługa zapisu formularza
        if (isset($_POST['flexmile_save_banners']) && check_admin_referer('flexmile_banners_nonce')) {
            $saved = 0;
            for ($i = 1; $i <= 3; $i++) {
                $label = isset($_POST["banner_{$i}_label"]) ? sanitize_text_field($_POST["banner_{$i}_label"]) : '';
                $description = isset($_POST["banner_{$i}_description"]) ? sanitize_textarea_field($_POST["banner_{$i}_description"]) : '';
                
                update_option("flexmile_banner_{$i}_label", $label);
                update_option("flexmile_banner_{$i}_description", $description);
                
                if (!empty($label)) {
                    $saved++;
                }
            }
            
            $message = $saved > 0 
                ? sprintf('Zapisano %d %s.', $saved, $saved === 1 ? 'banner' : 'bannerów')
                : 'Wszystkie bannery zostały usunięte.';
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Sukces!</strong> <?php echo esc_html($message); ?></p>
            </div>
            <?php
        }

        // Pobierz istniejące bannery
        $banners = [];
        for ($i = 1; $i <= 3; $i++) {
            $banners[$i] = [
                'label' => get_option("flexmile_banner_{$i}_label", ''),
                'description' => get_option("flexmile_banner_{$i}_description", ''),
            ];
        }
        ?>
        <div class="wrap">
            <h1>Zarządzanie Bannerami</h1>
            <p>Możesz dodać maksymalnie 3 bannery. Każdy banner składa się z nagłówka (label) i opisu (description).</p>

            <form method="post" action="">
                <?php wp_nonce_field('flexmile_banners_nonce'); ?>
                
                <div class="flexmile-banners-container">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="banner-box">
                            <h2>Banner <?php echo $i; ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="banner_<?php echo $i; ?>_label">Nagłówek (Label) *</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="text" 
                                            id="banner_<?php echo $i; ?>_label" 
                                            name="banner_<?php echo $i; ?>_label" 
                                            value="<?php echo esc_attr($banners[$i]['label']); ?>" 
                                            class="regular-text"
                                            placeholder="np. Nowa oferta specjalna"
                                        />
                                        <p class="description">Nagłówek banneru wyświetlany jako główny tekst</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="banner_<?php echo $i; ?>_description">Opis (Description)</label>
                                    </th>
                                    <td>
                                        <textarea 
                                            id="banner_<?php echo $i; ?>_description" 
                                            name="banner_<?php echo $i; ?>_description" 
                                            rows="4" 
                                            class="large-text"
                                            placeholder="np. Sprawdź nasze najnowsze promocje na wybrane modele samochodów"
                                        ><?php echo esc_textarea($banners[$i]['description']); ?></textarea>
                                        <p class="description">Szczegółowy opis banneru</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endfor; ?>
                </div>

                <p class="submit">
                    <input type="submit" name="flexmile_save_banners" class="button button-primary" value="Zapisz bannery" />
                </p>
            </form>

            <div class="flexmile-banners-info">
                <h2>Endpoint API</h2>
                <p>Bannery są dostępne przez endpoint:</p>
                <code><?php echo rest_url('flexmile/v1/banners'); ?></code>
                <p class="description">Endpoint zwraca tablicę maksymalnie 3 bannerów w formacie:</p>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>[
  {
    "label": "Nagłówek 1",
    "description": "Opis 1"
  },
  {
    "label": "Nagłówek 2",
    "description": "Opis 2"
  },
  {
    "label": "Nagłówek 3",
    "description": "Opis 3"
  }
]</code></pre>
            </div>
        </div>

        <style>
            .flexmile-banners-container {
                display: grid;
                gap: 20px;
                margin: 20px 0;
            }
            .banner-box {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-left: 4px solid #2271b1;
            }
            .banner-box h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }
            .flexmile-banners-info {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin-top: 30px;
            }
            .flexmile-banners-info code {
                background: #f5f5f5;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 14px;
                color: #d63638;
                display: inline-block;
                margin: 10px 0;
            }
            .flexmile-banners-info pre {
                max-width: 100%;
                overflow-x: auto;
            }
        </style>
        <?php
    }

    /**
     * Zapisuje domenę frontendową
     */
    public function save_frontend_domain() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        check_admin_referer('flexmile_frontend_domain_nonce');

        $domain = isset($_POST['frontend_domain']) ? esc_url_raw(trim($_POST['frontend_domain'])) : '';
        $domain = rtrim($domain, '/');
        
        update_option('flexmile_frontend_domain', $domain);

        wp_redirect(add_query_arg([
            'page' => 'flexmile-api',
            'settings-updated' => 'true'
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Renderuje stronę ustawień email/SMTP
     */
    public function render_email_settings() {
        // Komunikat sukcesu
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Sukces!</strong> Ustawienia SMTP zostały zapisane.</p>
            </div>
            <?php
        }

        // Pobierz aktualne ustawienia
        $smtp_enabled = get_option('flexmile_smtp_enabled', false);
        $smtp_host = get_option('flexmile_smtp_host', '');
        $smtp_port = get_option('flexmile_smtp_port', 587);
        $smtp_encryption = get_option('flexmile_smtp_encryption', 'tls');
        $smtp_username = get_option('flexmile_smtp_username', '');
        $smtp_password = get_option('flexmile_smtp_password', '');
        $smtp_from_email = get_option('flexmile_smtp_from_email', '');
        $smtp_from_name = get_option('flexmile_smtp_from_name', get_option('blogname', 'FlexMile'));
        $smtp_debug = get_option('flexmile_smtp_debug', false);
        
        // Dla bezpieczeństwa, nie pokazuj pełnego hasła
        $smtp_password_display = !empty($smtp_password) ? '••••••••' : '';
        ?>
        <div class="wrap">
            <h1>Ustawienia Email / SMTP</h1>
            
            <div class="flexmile-api-info" style="max-width: 800px;">
                <h2>Konfiguracja SMTP</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="flexmile_save_smtp_settings">
                    <?php wp_nonce_field('flexmile_smtp_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="smtp_enabled">Włącz SMTP</label>
                            </th>
                            <td>
                                <label>
                                    <input 
                                        type="checkbox" 
                                        id="smtp_enabled" 
                                        name="smtp_enabled" 
                                        value="1" 
                                        <?php checked($smtp_enabled, true); ?>
                                    />
                                    Użyj SMTP zamiast standardowej funkcji mail()
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_host">Serwer SMTP</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="smtp_host" 
                                    name="smtp_host" 
                                    value="<?php echo esc_attr($smtp_host); ?>" 
                                    class="regular-text"
                                    placeholder="smtp.example.com"
                                />
                                <p class="description">Adres serwera SMTP (np. smtp.gmail.com, smtp.example.com)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_port">Port SMTP</label>
                            </th>
                            <td>
                                <input 
                                    type="number" 
                                    id="smtp_port" 
                                    name="smtp_port" 
                                    value="<?php echo esc_attr($smtp_port); ?>" 
                                    class="small-text"
                                    min="1"
                                    max="65535"
                                />
                                <p class="description">Port SMTP (587 dla TLS, 465 dla SSL, 25 dla bez szyfrowania)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_encryption">Szyfrowanie</label>
                            </th>
                            <td>
                                <select id="smtp_encryption" name="smtp_encryption" class="regular-text">
                                    <option value="" <?php selected($smtp_encryption, ''); ?>>Brak</option>
                                    <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>>TLS (zalecane)</option>
                                    <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>>SSL</option>
                                </select>
                                <p class="description">Typ szyfrowania połączenia SMTP</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_username">Nazwa użytkownika</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="smtp_username" 
                                    name="smtp_username" 
                                    value="<?php echo esc_attr($smtp_username); ?>" 
                                    class="regular-text"
                                    placeholder="twoj@email.com"
                                />
                                <p class="description">Pełny adres email używany do autentykacji SMTP</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_password">Hasło</label>
                            </th>
                            <td>
                                <input 
                                    type="password" 
                                    id="smtp_password" 
                                    name="smtp_password" 
                                    value="" 
                                    class="regular-text"
                                    placeholder="<?php echo esc_attr($smtp_password_display); ?>"
                                />
                                <p class="description">
                                    Hasło do konta email lub hasło aplikacji (dla Gmail użyj hasła aplikacji)
                                    <?php if (!empty($smtp_password)): ?>
                                        <br><strong>Uwaga:</strong> Hasło jest już zapisane. Wpisz nowe tylko jeśli chcesz je zmienić.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_from_email">Adres nadawcy (From)</label>
                            </th>
                            <td>
                                <input 
                                    type="email" 
                                    id="smtp_from_email" 
                                    name="smtp_from_email" 
                                    value="<?php echo esc_attr($smtp_from_email); ?>" 
                                    class="regular-text"
                                    placeholder="noreply@example.com"
                                />
                                <p class="description">Adres email, który będzie widoczny jako nadawca (opcjonalne, domyślnie używa domeny strony)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_from_name">Nazwa nadawcy</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="smtp_from_name" 
                                    name="smtp_from_name" 
                                    value="<?php echo esc_attr($smtp_from_name); ?>" 
                                    class="regular-text"
                                    placeholder="FlexMile"
                                />
                                <p class="description">Nazwa wyświetlana jako nadawca (opcjonalne)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="smtp_debug">Debugowanie SMTP</label>
                            </th>
                            <td>
                                <label>
                                    <input 
                                        type="checkbox" 
                                        id="smtp_debug" 
                                        name="smtp_debug" 
                                        value="1" 
                                        <?php checked($smtp_debug, true); ?>
                                    />
                                    Włącz szczegółowe logowanie SMTP (tylko do diagnostyki)
                                </label>
                                <p class="description">
                                    Po włączeniu, szczegóły połączenia SMTP będą widoczne na stronie <strong>FlexMile → Test Emaili</strong> 
                                    w sekcji "Logi debugowania SMTP". <strong>Wyłącz po zdiagnozowaniu problemu!</strong>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="flexmile_save_smtp_settings" class="button button-primary" value="Zapisz ustawienia SMTP" />
                    </p>
                </form>

            </div>
        </div>
        <?php
    }

    /**
     * Zapisuje ustawienia SMTP
     */
    public function save_smtp_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        check_admin_referer('flexmile_smtp_settings_nonce');

        // Zapisz ustawienia
        $smtp_enabled = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1';
        update_option('flexmile_smtp_enabled', $smtp_enabled);

        if (isset($_POST['smtp_host'])) {
            update_option('flexmile_smtp_host', sanitize_text_field($_POST['smtp_host']));
        }

        if (isset($_POST['smtp_port'])) {
            $port = intval($_POST['smtp_port']);
            if ($port > 0 && $port <= 65535) {
                update_option('flexmile_smtp_port', $port);
            }
        }

        if (isset($_POST['smtp_encryption'])) {
            $encryption = sanitize_text_field($_POST['smtp_encryption']);
            if (in_array($encryption, ['', 'tls', 'ssl'], true)) {
                update_option('flexmile_smtp_encryption', $encryption);
            }
        }

        if (isset($_POST['smtp_username'])) {
            update_option('flexmile_smtp_username', sanitize_text_field($_POST['smtp_username']));
        }

        // Hasło - zapisz tylko jeśli zostało podane (nie nadpisuj jeśli puste)
        if (isset($_POST['smtp_password']) && !empty($_POST['smtp_password'])) {
            // Szyfruj hasło przed zapisaniem (opcjonalne, ale bezpieczniejsze)
            update_option('flexmile_smtp_password', sanitize_text_field($_POST['smtp_password']));
        }

        if (isset($_POST['smtp_from_email'])) {
            $email = sanitize_email($_POST['smtp_from_email']);
            if (is_email($email)) {
                update_option('flexmile_smtp_from_email', $email);
            } else {
                update_option('flexmile_smtp_from_email', '');
            }
        }

        if (isset($_POST['smtp_from_name'])) {
            update_option('flexmile_smtp_from_name', sanitize_text_field($_POST['smtp_from_name']));
        }

        $smtp_debug = isset($_POST['smtp_debug']) && $_POST['smtp_debug'] === '1';
        update_option('flexmile_smtp_debug', $smtp_debug);

        wp_redirect(add_query_arg([
            'page' => 'flexmile-email-settings',
            'settings-updated' => 'true'
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Ładuje assety dla admina
     */
    public function enqueue_admin_assets($hook) {
        // Możesz tutaj dodać własne CSS/JS dla admina jeśli potrzeba
    }
}
