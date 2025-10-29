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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
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
    }

    /**
     * Renderuje dashboard
     */
    public function render_dashboard() {
        // Komunikat sukcesu po imporcie
        if (isset($_GET['import']) && $_GET['import'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Success!</strong> <?php echo esc_html(urldecode($_GET['message'])); ?></p>
            </div>
            <?php
        }

        // Statystyki
        $total_offers = wp_count_posts('offer');
        $total_reservations = wp_count_posts('reservation');

        $pending_count = 0;
        $approved_count = 0;
        $rejected_count = 0;

        $reservations = get_posts([
            'post_type' => 'reservation',
            'posts_per_page' => -1,
        ]);

        foreach ($reservations as $res) {
            $status = get_post_meta($res->ID, '_status', true);
            if ($status === 'pending') $pending_count++;
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
                <div class="flexmile-stats">
                    <div class="stat-box">
                        <h3>🚗 Oferty</h3>
                        <p class="stat-number"><?php echo $total_offers->publish; ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=offer'); ?>">Zobacz wszystkie</a>
                    </div>

                    <div class="stat-box">
                        <h3>⏳ Oczekujące rezerwacje</h3>
                        <p class="stat-number"><?php echo $pending_count; ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>">Zarządzaj</a>
                    </div>

                    <div class="stat-box">
                        <h3>✅ Zatwierdzone</h3>
                        <p class="stat-number"><?php echo $approved_count; ?></p>
                    </div>

                    <div class="stat-box">
                        <h3>📋 Wszystkie rezerwacje</h3>
                        <p class="stat-number"><?php echo $total_reservations->publish; ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>">Zobacz wszystkie</a>
                    </div>
                </div>

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
                                📦 Importuj przykładowe dane
                            </button>
                        </form>
                        <p style="color: rgba(255,255,255,0.8); font-size: 12px; margin-top: 10px;">
                            ℹ️ Import nie nadpisze istniejących danych. Możesz go uruchomić bezpiecznie.
                        </p>
                    </div>
                    <hr style="margin: 30px 0;">
                    <?php endif; ?>

                    <h2>🎯 Szybki start</h2>
                    <ul>
                        <li><a href="<?php echo admin_url('post-new.php?post_type=offer'); ?>">➕ Dodaj nową ofertę</a></li>
                        <li><a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>">📋 Zarządzaj rezerwacjami</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=flexmile-api'); ?>">⚙️ Ustawienia API</a></li>
                    </ul>

                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;">
                        <h3 style="margin-top: 0;">📝 Zarządzanie markami i modelami</h3>
                        <p>Marki i modele są teraz przechowywane w pliku <code>config.json</code> w katalogu wtyczki.</p>
                        <p>Aby dodać/edytować marki lub modele, edytuj plik:</p>
                        <code><?php echo FLEXMILE_PLUGIN_DIR; ?>config.json</code>
                        <p style="margin-top: 10px; font-size: 13px; color: #856404;">
                            💡 Po edycji pliku, zmiany będą natychmiast widoczne w panelu admina i w API.
                        </p>
                    </div>

                    <h3>📡 REST API Endpoints</h3>
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
        ?>
        <div class="wrap">
            <h1>Ustawienia FlexMile API</h1>

            <div class="flexmile-api-info">
                <h2>🔌 Konfiguracja CORS</h2>
                <p>Aby Twoja aplikacja Angular mogła komunikować się z WordPress API, musisz skonfigurować CORS.</p>

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

                <h2>🚗 Nowy system marek i modeli</h2>
                <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2271b1; border-radius: 4px; margin: 20px 0;">
                    <p><strong>Marki i modele są teraz w pliku JSON!</strong></p>
                    <p>Zamiast używać taksonomii WordPress, marki i modele są przechowywane w pliku <code>config.json</code>. To oznacza:</p>
                    <ul style="margin-left: 20px;">
                        <li>✅ Brak śmieci w bazie danych</li>
                        <li>✅ Łatwiejsza aktualizacja (wystarczy edytować jeden plik)</li>
                        <li>✅ Szybsze zapytania do API</li>
                        <li>✅ Automatyczne dependency dropdown (najpierw marka, potem modele)</li>
                    </ul>
                </div>

                <h3>📋 Nowe endpointy API:</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Opis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>GET /wp-json/flexmile/v1/offers/brands</code></td>
                            <td>Zwraca listę wszystkich dostępnych marek z liczbą modeli</td>
                        </tr>
                        <tr>
                            <td><code>GET /wp-json/flexmile/v1/offers/brands/{slug}/models</code></td>
                            <td>Zwraca modele dla wybranej marki (np. <code>/offers/brands/bmw/models</code>)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>📋 Zaktualizowane filtry:</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Parametr</th>
                            <th>Typ</th>
                            <th>Opis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>car_brand</code></td>
                            <td>string</td>
                            <td>Filtrowanie po marce (slug, np. "bmw", "toyota")</td>
                        </tr>
                        <tr>
                            <td><code>car_model</code></td>
                            <td>string</td>
                            <td><strong>NOWOŚĆ!</strong> Filtrowanie po modelu (np. "X5", "Corolla")</td>
                        </tr>
                        <tr>
                            <td><code>year_from</code></td>
                            <td>integer</td>
                            <td>Rocznik od</td>
                        </tr>
                        <tr>
                            <td><code>year_to</code></td>
                            <td>integer</td>
                            <td>Rocznik do</td>
                        </tr>
                        <tr>
                            <td><code>max_mileage</code></td>
                            <td>integer</td>
                            <td>Maksymalny przebieg</td>
                        </tr>
                        <tr>
                            <td><code>price_from</code></td>
                            <td>float</td>
                            <td>Cena minimalna</td>
                        </tr>
                        <tr>
                            <td><code>price_to</code></td>
                            <td>float</td>
                            <td>Cena maksymalna</td>
                        </tr>
                        <tr>
                            <td><code>page</code></td>
                            <td>integer</td>
                            <td>Numer strony (infinite scroll)</td>
                        </tr>
                        <tr>
                            <td><code>per_page</code></td>
                            <td>integer</td>
                            <td>Liczba wyników na stronę (max 100)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>💡 Przykładowe zapytania:</h3>
                <ul class="api-examples">
                    <li>
                        <strong>Wszystkie oferty:</strong><br>
                        <code><?php echo rest_url('flexmile/v1/offers'); ?></code>
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
                        <strong>BMW X5 z lat 2020-2023:</strong><br>
                        <code><?php echo rest_url('flexmile/v1/offers?car_brand=bmw&car_model=X5&year_from=2020&year_to=2023'); ?></code>
                    </li>
                    <li>
                        <strong>Wszystkie Toyoty:</strong><br>
                        <code><?php echo rest_url('flexmile/v1/offers?car_brand=toyota'); ?></code>
                    </li>
                    <li>
                        <strong>Infinite scroll (strona 2):</strong><br>
                        <code><?php echo rest_url('flexmile/v1/offers?page=2&per_page=10'); ?></code>
                    </li>
                </ul>
            </div>
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
     * Ładuje assety dla admina
     */
    public function enqueue_admin_assets($hook) {
        // Możesz tutaj dodać własne CSS/JS dla admina jeśli potrzeba
    }
}
