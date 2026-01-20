<?php
namespace FlexMile\Admin;

use FlexMile\Core\Email_Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Narzędzie developerskie do testowania szablonów emailowych
 */
class Email_Tester {

    private $mock_car_id = null;

    private $templates = [
        'admin-contact' => [
            'name' => 'Admin - Formularz kontaktowy',
            'description' => 'Email wysyłany do administratora po wypełnieniu formularza kontaktowego',
        ],
        'admin-order' => [
            'name' => 'Admin - Nowe zamówienie',
            'description' => 'Email wysyłany do administratora po złożeniu zamówienia',
        ],
        'admin-reservation' => [
            'name' => 'Admin - Nowa rezerwacja',
            'description' => 'Email wysyłany do administratora po złożeniu rezerwacji',
        ],
        'customer-order' => [
            'name' => 'Klient - Potwierdzenie zamówienia',
            'description' => 'Email potwierdzający wysyłany do klienta po złożeniu zamówienia',
        ],
        'customer-reservation' => [
            'name' => 'Klient - Potwierdzenie rezerwacji',
            'description' => 'Email potwierdzający wysyłany do klienta po złożeniu rezerwacji',
        ],
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_flexmile_test_email', [$this, 'handle_test_email']);
    }

    /**
     * Dodaje menu w panelu admina
     */
    public function add_admin_menu() {
        add_submenu_page(
            'flexmile',
            'Test Emaili',
            'Test Emaili',
            'manage_options',
            'flexmile-test-emails',
            [$this, 'render_page']
        );
    }

    /**
     * Renderuje stronę testowania emaili
     */
    public function render_page() {
        // Obsługa wysyłki testowego emaila
        if (isset($_GET['sent']) && $_GET['sent'] === '1') {
            $template = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : '';
            $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Sukces!</strong> Testowy email został wysłany do <strong><?php echo esc_html($email); ?></strong> (szablon: <strong><?php echo esc_html($template); ?></strong>)</p>
            </div>
            <?php
        }

        if (isset($_GET['error'])) {
            $error = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : 'Wystąpił błąd podczas wysyłki emaila';
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>Błąd!</strong> <?php echo esc_html($error); ?></p>
            </div>
            <?php
        }

        ?>
        <div class="wrap">
            <h1>Testowanie Szablonów Emailowych</h1>
            <p class="description">Narzędzie developerskie do testowania wszystkich dostępnych szablonów emailowych z przykładowymi danymi.</p>

            <div class="flexmile-email-tester" style="margin-top: 20px;">
                <?php foreach ($this->templates as $template_key => $template_info): ?>
                    <div class="flexmile-test-template-box" style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #2271b1;">
                        <h2 style="margin-top: 0;"><?php echo esc_html($template_info['name']); ?></h2>
                        <p style="color: #666; margin-bottom: 15px;"><?php echo esc_html($template_info['description']); ?></p>
                        
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="action" value="flexmile_test_email">
                            <input type="hidden" name="template" value="<?php echo esc_attr($template_key); ?>">
                            <?php wp_nonce_field('flexmile_test_email_' . $template_key, 'flexmile_test_email_nonce'); ?>
                            
                            <div style="flex: 1;">
                                <label for="email_<?php echo esc_attr($template_key); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Adres email:
                                </label>
                                <input 
                                    type="email" 
                                    id="email_<?php echo esc_attr($template_key); ?>" 
                                    name="email" 
                                    value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                    required 
                                    class="regular-text"
                                    placeholder="twoj@email.pl"
                                >
                                <p class="description" style="margin-top: 5px;">
                                    Email zostanie wysłany na ten adres z przykładowymi danymi
                                </p>
                            </div>
                            
                            <div>
                                <button type="submit" class="button button-primary button-large">
                                    Wyślij testowy email
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 30px;">
                <h3 style="margin-top: 0;">Uwaga</h3>
                <p style="margin-bottom: 0;">
                    To narzędzie używa rzeczywistej funkcji <code>wp_mail()</code> WordPressa. 
                    Upewnij się, że konfiguracja SMTP jest prawidłowa, lub użyj wtyczki do konfiguracji wysyłki emaili (np. WP Mail SMTP).
                </p>
            </div>

            <?php
            // Sekcja logów emaili
            $logs = Email_Config::get_logs(50);
            $smtp_debug_enabled = get_option('flexmile_smtp_debug', false);
            $smtp_debug_logs = $smtp_debug_enabled ? Email_Config::get_smtp_debug_logs(100) : [];
            ?>

            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; border-left: 4px solid #6b7280; margin-top: 30px;">
                <h3 style="margin-top: 0;">Logi wysyłki emaili (ostatnie 50)</h3>
                <p style="margin-bottom: 10px; color: #4b5563;">
                    Tutaj zobaczysz próby wysyłki emaili z wtyczki (rezerwacje, zamówienia, formularz kontaktowy, testy) oraz ewentualne błędy SMTP.
                </p>

                <?php if (empty($logs)): ?>
                    <p style="margin: 0; color: #6b7280;">Brak zapisanych logów wysyłki emaili.</p>
                <?php else: ?>
                    <div style="max-height: 400px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 4px; background: #fff;">
                        <table class="widefat striped" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 150px;">Czas</th>
                                    <th style="width: 90px;">Status</th>
                                    <th>Do</th>
                                    <th>Temat</th>
                                    <th style="width: 140px;">Kontekst</th>
                                    <th style="width: 220px;">Szczegóły</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $status = isset($log['status']) ? $log['status'] : '';
                                    $status_label = strtoupper($status);
                                    $status_color = '#6b7280';

                                    if ($status === 'success') {
                                        $status_color = '#059669';
                                    } elseif ($status === 'failed') {
                                        $status_color = '#dc2626';
                                    }

                                    $time = isset($log['time']) ? $log['time'] : '';
                                    $to = isset($log['to']) ? $log['to'] : '';
                                    $subject = isset($log['subject']) ? $log['subject'] : '';
                                    $context = isset($log['context']) ? $log['context'] : '';
                                    $error = isset($log['error']) ? $log['error'] : '';
                                    $error_details = isset($log['error_details']) ? $log['error_details'] : '';
                                    $from = isset($log['from']) ? $log['from'] : '';
                                    $smtp = isset($log['smtp']) && is_array($log['smtp']) ? $log['smtp'] : null;
                                    ?>
                                    <tr>
                                        <td><code><?php echo esc_html($time); ?></code></td>
                                        <td>
                                            <span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr($status_color); ?>">
                                                <?php echo esc_html($status_label); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($to); ?></td>
                                        <td><?php echo esc_html($subject); ?></td>
                                        <td><code><?php echo esc_html($context); ?></code></td>
                                        <td>
                                            <?php if (!empty($from)): ?>
                                                <div style="color:#059669;font-size:11px;margin-bottom:4px;">
                                                    <strong>From:</strong> <?php echo esc_html($from); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($error)): ?>
                                                <div style="color:#dc2626;font-size:12px;margin-bottom:4px;">
                                                    <strong>Błąd:</strong> <?php echo esc_html($error); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($error_details)): ?>
                                                <div style="color:#991b1b;font-size:11px;margin-bottom:4px;font-style:italic;">
                                                    <?php echo esc_html($error_details); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($smtp): ?>
                                                <div style="color:#4b5563;font-size:11px;">
                                                    <strong>SMTP:</strong>
                                                    host=<?php echo esc_html($smtp['host'] ?? ''); ?>,
                                                    port=<?php echo isset($smtp['port']) ? intval($smtp['port']) : ''; ?>,
                                                    enc=<?php echo esc_html($smtp['encryption'] ?? ''); ?>,
                                                    user=<?php echo esc_html($smtp['username'] ?? ''); ?>,
                                                    enabled=<?php echo !empty($smtp['enabled']) ? 'yes' : 'no'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if ($smtp_debug_enabled && !empty($smtp_debug_logs)): ?>
                    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h3 style="margin: 0;">🔍 Logi debugowania SMTP (ostatnie 100)</h3>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                <input type="hidden" name="action" value="flexmile_clear_smtp_debug_logs">
                                <?php wp_nonce_field('flexmile_clear_smtp_debug_logs'); ?>
                                <button type="submit" class="button button-secondary" style="font-size: 12px; padding: 4px 8px;">
                                    Wyczyść logi
                                </button>
                            </form>
                        </div>
                        <p style="margin-bottom: 10px; color: #92400e; font-size: 13px;">
                            Szczegółowe komunikaty z serwera SMTP podczas ostatniej próby wysyłki. To pomaga zdiagnozować problemy z autoryzacją.
                        </p>
                        <div style="max-height: 300px; overflow: auto; border: 1px solid #fbbf24; border-radius: 4px; background: #fff; padding: 10px; font-family: monospace; font-size: 11px;">
                            <?php foreach ($smtp_debug_logs as $debug_log): ?>
                                <div style="margin-bottom: 4px; padding: 2px 0; border-bottom: 1px solid #fef3c7;">
                                    <span style="color: #92400e;">[<?php echo esc_html($debug_log['time'] ?? ''); ?>]</span>
                                    <span style="color: <?php echo isset($debug_log['level']) && $debug_log['level'] >= 2 ? '#dc2626' : '#059669'; ?>;">
                                        <?php echo esc_html($debug_log['message'] ?? ''); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($smtp_debug_enabled && empty($smtp_debug_logs)): ?>
                    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-top: 20px;">
                        <h3 style="margin: 0 0 10px 0;">🔍 Debugowanie SMTP włączone</h3>
                        <p style="margin: 0; color: #92400e;">
                            Wyślij testowy email, aby zobaczyć szczegółowe logi połączenia SMTP tutaj.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Obsługuje wysyłkę testowego emaila
     */
    public function handle_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($template) || !isset($this->templates[$template])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'flexmile-test-emails',
                'error' => '1',
                'message' => urlencode('Nieprawidłowy szablon')
            ], admin_url('admin.php')));
            exit;
        }

        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'flexmile-test-emails',
                'error' => '1',
                'message' => urlencode('Nieprawidłowy adres email')
            ], admin_url('admin.php')));
            exit;
        }

        check_admin_referer('flexmile_test_email_' . $template, 'flexmile_test_email_nonce');

        // Przygotuj dane testowe i wyślij email
        $sent = $this->send_test_email($template, $email);

        if ($sent) {
            wp_safe_redirect(add_query_arg([
                'page' => 'flexmile-test-emails',
                'sent' => '1',
                'template' => urlencode($template),
                'email' => urlencode($email)
            ], admin_url('admin.php')));
            exit;
        } else {
            wp_safe_redirect(add_query_arg([
                'page' => 'flexmile-test-emails',
                'error' => '1',
                'message' => urlencode('Nie udało się wysłać emaila. Sprawdź konfigurację SMTP.')
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Wysyła testowy email z przykładowymi danymi
     */
    private function send_test_email($template_name, $to_email) {
        // Resetuj mock_car_id przed każdym testem
        $this->mock_car_id = null;
        
        $test_data = $this->get_test_data($template_name);
        
        if ($test_data === false) {
            return false;
        }

        $message = $this->load_email_template($template_name, $test_data);
        
        // Resetuj po użyciu
        $this->mock_car_id = null;
        
        if (empty($message)) {
            return false;
        }

        $subject = sprintf('[TEST] FlexMile - %s', $this->templates[$template_name]['name']);
        
        $context = 'email_tester:' . $template_name;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'X-FlexMile-Context: ' . $context,
        ];

        $sent = wp_mail($to_email, $subject, $message, $headers);

        // Pobierz informacje o adresie From (z ustawień SMTP lub domyślnego)
        $smtp_enabled = get_option('flexmile_smtp_enabled', false);
        $smtp_from_email = get_option('flexmile_smtp_from_email', '');
        $smtp_from_name = get_option('flexmile_smtp_from_name', '');
        $smtp_username = get_option('flexmile_smtp_username', '');
        
        $from_email = '';
        if ($smtp_enabled) {
            if (!empty($smtp_from_email) && is_email($smtp_from_email)) {
                $from_email = $smtp_from_email;
            } elseif (!empty($smtp_username) && is_email($smtp_username)) {
                $from_email = $smtp_username;
            }
        }
        
        // Zapisz log testowego maila
        Email_Config::add_log_entry([
            'type'    => 'info',
            'status'  => $sent ? 'success' : 'failed',
            'to'      => $to_email,
            'subject' => $subject,
            'context' => $context,
            'from'    => $from_email, // Dodaj informację o adresie From
        ]);

        return $sent;
    }

    /**
     * Ładuje szablon e-maila z pliku (kopiowane z API)
     */
    private function load_email_template($template_name, $vars = []) {
        $template_path = FLEXMILE_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return false;
        }

        // Dodaj filtr dla mock samochodu jeśli potrzebny
        if (isset($this->mock_car_id) && $this->mock_car_id) {
            add_filter('get_post_metadata', [$this, 'mock_car_meta_data'], 10, 4);
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        include $template_path;
        $output = ob_get_clean();

        // Usuń filtr po renderowaniu
        if (isset($this->mock_car_id) && $this->mock_car_id) {
            remove_filter('get_post_metadata', [$this, 'mock_car_meta_data'], 10);
        }

        return $output;
    }

    /**
     * Generuje przykładowe dane testowe dla danego szablonu
     */
    private function get_test_data($template_name) {
        switch ($template_name) {
            case 'admin-contact':
                return [
                    'params' => [
                        'first_name' => 'Jan',
                        'last_name' => 'Kowalski',
                        'email' => 'jan.kowalski@example.com',
                        'phone' => '+48 123 456 789',
                        'monthly_budget_from' => 2000.00,
                        'monthly_budget_to' => 3500.00,
                        'message' => 'To jest przykładowa wiadomość testowa wysłana z narzędzia developerskiego do testowania szablonów emailowych. Można tutaj umieścić dowolną treść zapytania kontaktowego.',
                        'consent_email' => true,
                        'consent_phone' => true,
                    ],
                ];

            case 'admin-order':
            case 'customer-order':
                // Potrzebujemy przykładowego samochodu
                $test_car = $this->get_test_car();
                if (!$test_car) {
                    return false;
                }

                return [
                    'rezerwacja_id' => 9999,
                    'samochod' => $test_car,
                    'params' => [
                        'company_name' => 'Testowa Spółka z o.o.',
                        'tax_id' => '1234567890',
                        'first_name' => 'Jan',
                        'last_name' => 'Kowalski',
                        'email' => 'jan.kowalski@example.com',
                        'phone' => '+48 123 456 789',
                        'rental_months' => 24,
                        'annual_mileage_limit' => 15000,
                        'consent_email' => true,
                        'consent_phone' => true,
                        'pickup_location' => 'salon',
                        'message' => 'To jest przykładowe zamówienie testowe.',
                    ],
                    'cena_miesieczna' => 2499.99,
                    'cena_calkowita' => 59999.76,
                    'entry_type' => 'order',
                ];

            case 'admin-reservation':
            case 'customer-reservation':
                // Potrzebujemy przykładowego samochodu
                $test_car = $this->get_test_car();
                if (!$test_car) {
                    return false;
                }

                return [
                    'rezerwacja_id' => 8888,
                    'samochod' => $test_car,
                    'params' => [
                        'company_name' => 'Testowa Spółka z o.o.',
                        'tax_id' => '1234567890',
                        'first_name' => 'Anna',
                        'last_name' => 'Nowak',
                        'email' => 'anna.nowak@example.com',
                        'phone' => '+48 987 654 321',
                        'rental_months' => 36,
                        'annual_mileage_limit' => 20000,
                        'consent_email' => true,
                        'consent_phone' => true,
                        'pickup_location' => 'home_delivery',
                        'message' => 'To jest przykładowa rezerwacja testowa.',
                    ],
                    'cena_miesieczna' => 2199.99,
                    'cena_calkowita' => 79199.64,
                    'entry_type' => 'reservation',
                ];

            default:
                return [];
        }
    }

    /**
     * Pobiera przykładowy samochód z bazy (lub tworzy obiekt mock jeśli brak)
     */
    private function get_test_car() {
        // Spróbuj pobrać pierwszy dostępny samochód
        $cars = get_posts([
            'post_type' => 'offer',
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (!empty($cars)) {
            return $cars[0];
        }

        // Jeśli brak samochodów, stwórz obiekt mock
        $mock_car = new \stdClass();
        $mock_car->ID = 999999; // Używamy wysokiego ID, aby nie kolidować z prawdziwymi postami
        
        // Ustawiamy meta dane dla mock samochodu poprzez filtr
        add_filter('get_post_metadata', [$this, 'mock_car_meta_data'], 10, 4);
        
        // Tymczasowo zapisz ID mock samochodu, aby filtr wiedział kiedy go użyć
        $this->mock_car_id = $mock_car->ID;
        
        $mock_car->post_title = 'BMW X5 3.0d xDrive (TEST)';
        $mock_car->post_type = 'offer';
        $mock_car->post_status = 'publish';
        
        return $mock_car;
    }

    /**
     * Filtr do mockowania meta danych dla testowego samochodu
     */
    public function mock_car_meta_data($value, $object_id, $meta_key, $single) {
        if (!isset($this->mock_car_id) || $object_id !== $this->mock_car_id) {
            return $value;
        }

        $mock_meta = [
            '_car_reference_id' => 'FLX-LA-2025-999',
            '_car_brand_slug' => 'bmw',
            '_car_model' => 'X5',
            '_body_type' => 'SUV',
            '_fuel_type' => 'Diesel',
            '_year' => 2023,
            '_horsepower' => 286,
            '_engine_capacity' => 2998,
            '_transmission' => 'automatic',
            '_drivetrain' => 'AWD',
            '_engine' => '3.0d xDrive',
            '_color' => 'Czarny metalik',
            '_seats' => 5,
            '_doors' => '5',
        ];

        if (isset($mock_meta[$meta_key])) {
            return $single ? $mock_meta[$meta_key] : [$mock_meta[$meta_key]];
        }

        return $value;
    }
    
    /**
     * Obsługuje czyszczenie logów debugowania SMTP
     */
    public function handle_clear_smtp_debug_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        check_admin_referer('flexmile_clear_smtp_debug_logs');

        Email_Config::clear_smtp_debug_logs();

        wp_safe_redirect(add_query_arg([
            'page' => 'flexmile-test-emails',
            'smtp_logs_cleared' => '1'
        ], admin_url('admin.php')));
        exit;
    }
}

