<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Rezerwacji
 * Z nowym systemem wyboru konfiguracji cen
 */
class Reservations_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'reservations';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpointy API
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'POST',
            'callback' => [$this, 'create_rezerwacja'],
            'permission_callback' => '__return_true',
            'args' => $this->get_create_params(),
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_rezerwacje'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Tworzy nową rezerwację (ZAKTUALIZOWANE)
     */
    public function create_rezerwacja($request) {
        $params = $request->get_params();

        $samochod_id = intval($params['offer_id']);
        $samochod = get_post($samochod_id);

        if (!$samochod || $samochod->post_type !== 'offer') {
            return new \WP_Error('invalid_car', 'Nieprawidłowy ID samochodu', ['status' => 400]);
        }

        $rezerwacja_aktywna = get_post_meta($samochod_id, '_reservation_active', true);
        if ($rezerwacja_aktywna === '1') {
            return new \WP_Error('car_reserved', 'Ten samochód jest już zarezerwowany', ['status' => 400]);
        }

        $config = get_post_meta($samochod_id, '_pricing_config', true);

        if (empty($config) || empty($config['prices'])) {
            return new \WP_Error('no_pricing', 'Samochód nie ma skonfigurowanych cen', ['status' => 400]);
        }

        $company_name = $this->resolve_company_name($params);
        $tax_id = $this->resolve_tax_id($params);

        if (empty($company_name)) {
            return new \WP_Error('missing_company_name', 'Nazwa firmy jest wymagana', ['status' => 400]);
        }

        if (empty($tax_id)) {
            return new \WP_Error('missing_tax_id', 'NIP jest wymagany', ['status' => 400]);
        }

        $ilosc_miesiecy = intval($params['rental_months']);
        $limit_km_rocznie = intval($params['annual_mileage_limit']);

        if (!in_array($ilosc_miesiecy, $config['rental_periods'])) {
            return new \WP_Error(
                'invalid_period',
                'Wybrany okres wynajmu nie jest dostępny dla tego samochodu',
                ['status' => 400]
            );
        }

        if (!in_array($limit_km_rocznie, $config['mileage_limits'])) {
            return new \WP_Error(
                'invalid_km_limit',
                'Wybrany limit kilometrów nie jest dostępny dla tego samochodu',
                ['status' => 400]
            );
        }

        $cena_key = $ilosc_miesiecy . '_' . $limit_km_rocznie;

        if (!isset($config['prices'][$cena_key])) {
            return new \WP_Error(
                'price_not_found',
                'Nie znaleziono ceny dla wybranej konfiguracji',
                ['status' => 400]
            );
        }

        $cena_miesieczna = (float) $config['prices'][$cena_key];
        $cena_calkowita = $cena_miesieczna * $ilosc_miesiecy;

        $tytul = sprintf(
            'Rezerwacja: %s - %s %s',
            $company_name,
            $samochod->post_title,
            date('Y-m-d H:i')
        );

        $post_data = [
            'post_type' => 'reservation',
            'post_title' => $tytul,
            'post_status' => 'publish',
            'post_author' => 1,
        ];

        $rezerwacja_id = wp_insert_post($post_data);

        if (is_wp_error($rezerwacja_id)) {
            return new \WP_Error('create_failed', 'Nie udało się utworzyć rezerwacji', ['status' => 500]);
        }

        update_post_meta($rezerwacja_id, '_offer_id', $samochod_id);
        update_post_meta($rezerwacja_id, '_company_name', $company_name);
        update_post_meta($rezerwacja_id, '_tax_id', $tax_id);
        update_post_meta($rezerwacja_id, '_email', sanitize_email($params['email']));
        update_post_meta($rezerwacja_id, '_phone', sanitize_text_field($params['phone']));
        update_post_meta($rezerwacja_id, '_rental_months', $ilosc_miesiecy);
        update_post_meta($rezerwacja_id, '_annual_mileage_limit', $limit_km_rocznie);
        update_post_meta($rezerwacja_id, '_monthly_price', $cena_miesieczna);
        update_post_meta($rezerwacja_id, '_total_price', $cena_calkowita);
        update_post_meta($rezerwacja_id, '_status', 'pending');

        if (!empty($params['first_name'])) {
            update_post_meta($rezerwacja_id, '_first_name', sanitize_text_field($params['first_name']));
        }

        if (!empty($params['last_name'])) {
            update_post_meta($rezerwacja_id, '_last_name', sanitize_text_field($params['last_name']));
        }

        if (!empty($params['message'])) {
            update_post_meta($rezerwacja_id, '_message', sanitize_textarea_field($params['message']));
        }

        $params['company_name'] = $company_name;
        $params['tax_id'] = $tax_id;

        $this->send_admin_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita);
        $this->send_customer_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Rezerwacja została złożona pomyślnie',
            'reservation_id' => $rezerwacja_id,
            'pricing' => [
                'monthly_price' => $cena_miesieczna,
                'total_price' => $cena_calkowita,
                'rental_months' => $ilosc_miesiecy,
                'annual_mileage_limit' => $limit_km_rocznie,
            ],
        ], 201);
    }

    /**
     * Pobiera listę rezerwacji (tylko dla adminów)
     */
    public function get_rezerwacje($request) {
        $args = [
            'post_type' => 'reservation',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new \WP_Query($args);
        $rezerwacje = [];

        foreach ($query->posts as $post) {
            $rezerwacje[] = $this->prepare_rezerwacja_data($post);
        }

        return new \WP_REST_Response($rezerwacje);
    }

    /**
     * Przygotowuje dane rezerwacji (ZAKTUALIZOWANE)
     */
    private function prepare_rezerwacja_data($post) {
        $samochod_id = get_post_meta($post->ID, '_offer_id', true);
        $samochod = get_post($samochod_id);

        return [
            'id' => $post->ID,
            'created_at' => $post->post_date,
            'status' => get_post_meta($post->ID, '_status', true),
            'customer' => [
                'company_name' => $this->get_company_meta($post->ID),
                'tax_id' => $this->get_tax_meta($post->ID),
                'email' => get_post_meta($post->ID, '_email', true),
                'phone' => get_post_meta($post->ID, '_phone', true),
            ],
            'offer' => [
                'id' => $samochod_id,
                'title' => $samochod ? $samochod->post_title : '',
            ],
            'details' => [
                'rental_months' => (int) get_post_meta($post->ID, '_rental_months', true),
                'annual_mileage_limit' => (int) get_post_meta($post->ID, '_annual_mileage_limit', true),
                'monthly_price' => (float) get_post_meta($post->ID, '_monthly_price', true),
                'total_price' => (float) get_post_meta($post->ID, '_total_price', true),
            ],
            'message' => get_post_meta($post->ID, '_message', true),
        ];
    }

    /**
     * Zwraca nazwę firmy z uwzględnieniem wstecznej kompatybilności
     */
    private function get_company_meta($reservation_id) {
        $company = get_post_meta($reservation_id, '_company_name', true);

        if (!empty($company)) {
            return $company;
        }

        $first = get_post_meta($reservation_id, '_first_name', true);
        $last = get_post_meta($reservation_id, '_last_name', true);

        return trim($first . ' ' . $last);
    }

    /**
     * Zwraca NIP z uwzględnieniem wstecznej kompatybilności
     */
    private function get_tax_meta($reservation_id) {
        $tax_id = get_post_meta($reservation_id, '_tax_id', true);

        if (!empty($tax_id)) {
            return $tax_id;
        }

        return '';
    }

    /**
     * Wyznacza nazwę firmy na podstawie parametrów requestu
     */
    private function resolve_company_name($params) {
        if (!empty($params['company_name'])) {
            return sanitize_text_field($params['company_name']);
        }

        $first = !empty($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last = !empty($params['last_name']) ? sanitize_text_field($params['last_name']) : '';

        return trim($first . ' ' . $last);
    }

    /**
     * Wyznacza NIP na podstawie parametrów requestu
     */
    private function resolve_tax_id($params) {
        if (empty($params['tax_id'])) {
            return '';
        }

        return sanitize_text_field($params['tax_id']);
    }

    /**
     * Wysyła email do administratora (ZAKTUALIZOWANE)
     */
    private function send_admin_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita) {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[FlexMile] Nowa rezerwacja #%d - %s', $rezerwacja_id, $samochod->post_title);

        $message = $this->load_email_template('admin-reservation', [
            'rezerwacja_id' => $rezerwacja_id,
            'samochod' => $samochod,
            'params' => $params,
            'cena_miesieczna' => $cena_miesieczna,
            'cena_calkowita' => $cena_calkowita,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Wysyła email potwierdzający do klienta (ZAKTUALIZOWANE)
     */
    private function send_customer_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita) {
        $to = $params['email'];
        $subject = 'Potwierdzenie rezerwacji - FlexMile';

        $message = $this->load_email_template('customer-reservation', [
            'rezerwacja_id' => $rezerwacja_id,
            'samochod' => $samochod,
            'params' => $params,
            'cena_miesieczna' => $cena_miesieczna,
            'cena_calkowita' => $cena_calkowita,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>',
        ];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Ładuje szablon e-maila z pliku
     * 
     * @param string $template_name Nazwa szablonu (bez rozszerzenia .php)
     * @param array $vars Zmienne dostępne w szablonie
     * @return string Zawartość szablonu
     */
    private function load_email_template($template_name, $vars = []) {
        $template_path = FLEXMILE_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            // Fallback: zwróć podstawowy komunikat błędu
            return sprintf(
                'Szablon e-maila "%s" nie został znaleziony. Sprawdź plik: %s',
                $template_name,
                $template_path
            );
        }

        // Wyodrębnij zmienne do osobnych zmiennych dla łatwego użycia w szablonie
        extract($vars, EXTR_SKIP);

        // Przechwyć output szablonu
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Parametry dla tworzenia rezerwacji (ZAKTUALIZOWANE)
     */
    private function get_create_params() {
        return [
            'offer_id' => [
                'required' => true,
                'type' => 'integer',
                'description' => 'ID samochodu',
            ],
            'company_name' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Nazwa firmy klienta',
            ],
            'tax_id' => [
                'required' => true,
                'type' => 'string',
                'description' => 'NIP firmy klienta',
            ],
            'first_name' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Imię klienta (zgodność wsteczna)',
            ],
            'last_name' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Nazwisko klienta (zgodność wsteczna)',
            ],
            'email' => [
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'description' => 'Email klienta',
            ],
            'phone' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Numer telefonu',
            ],
            'rental_months' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Wybrany okres wynajmu w miesiącach (np. 12, 24, 36)',
            ],
            'annual_mileage_limit' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Wybrany roczny limit kilometrów (np. 10000, 15000)',
            ],
            'message' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Dodatkowa wiadomość od klienta',
            ],
        ];
    }
}


