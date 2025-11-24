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
    private const ENTRY_CONFIG = [
        'reservation' => [
            'post_type' => 'reservation',
            'title_prefix' => 'Rezerwacja',
            'success_message' => 'Rezerwacja została złożona pomyślnie',
            'admin_email_template' => 'admin-reservation',
            'customer_email_template' => 'customer-reservation',
            'admin_email_subject' => '[FlexMile] Nowa rezerwacja #%d - %s',
            'customer_email_subject' => 'Potwierdzenie rezerwacji - FlexMile',
            'should_lock_vehicle' => true,
        ],
        'order' => [
            'post_type' => 'order',
            'title_prefix' => 'Zamówienie',
            'success_message' => 'Zamówienie zostało złożone pomyślnie',
            'admin_email_template' => 'admin-order',
            'customer_email_template' => 'customer-order',
            'admin_email_subject' => '[FlexMile] Nowe zamówienie #%d - %s',
            'customer_email_subject' => 'Potwierdzenie zamówienia - FlexMile',
            'should_lock_vehicle' => false,
        ],
    ];

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
            'args' => $this->get_list_params(),
        ]);
    }

    /**
     * Tworzy nową rezerwację (ZAKTUALIZOWANE)
     */
    public function create_rezerwacja($request) {
        $params = $request->get_params();

        $entry_type = $this->resolve_entry_type($params['type'] ?? null);
        $entry_config = $this->get_entry_config($entry_type);

        $samochod_id = intval($params['offer_id']);
        $samochod = get_post($samochod_id);

        if (!$samochod || $samochod->post_type !== 'offer') {
            return new \WP_Error('invalid_car', 'Nieprawidłowy ID samochodu', ['status' => 400]);
        }

        $rezerwacja_aktywna = get_post_meta($samochod_id, '_reservation_active', true);
        if ($entry_config['should_lock_vehicle'] && $rezerwacja_aktywna === '1') {
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
            '%s: %s - %s %s',
            $entry_config['title_prefix'],
            $company_name,
            $samochod->post_title,
            date('Y-m-d H:i')
        );

        $post_data = [
            'post_type' => $entry_config['post_type'],
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
        update_post_meta($rezerwacja_id, '_entry_type', $entry_type);

        if (!empty($params['first_name'])) {
            update_post_meta($rezerwacja_id, '_first_name', sanitize_text_field($params['first_name']));
        }

        if (!empty($params['last_name'])) {
            update_post_meta($rezerwacja_id, '_last_name', sanitize_text_field($params['last_name']));
        }

        if (!empty($params['message'])) {
            update_post_meta($rezerwacja_id, '_message', sanitize_textarea_field($params['message']));
        }

        $consent_email = $this->sanitize_bool($params['consent_email'] ?? false);
        $consent_phone = $this->sanitize_bool($params['consent_phone'] ?? false);
        $pickup_location = $this->sanitize_pickup_location($params['pickup_location'] ?? null);

        update_post_meta($rezerwacja_id, '_consent_email', $consent_email ? '1' : '0');
        update_post_meta($rezerwacja_id, '_consent_phone', $consent_phone ? '1' : '0');
        update_post_meta($rezerwacja_id, '_pickup_location', $pickup_location);

        $params['company_name'] = $company_name;
        $params['tax_id'] = $tax_id;
        $params['consent_email'] = $consent_email;
        $params['consent_phone'] = $consent_phone;
        $params['pickup_location'] = $pickup_location;

        $this->send_admin_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita, $entry_config);
        $this->send_customer_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita, $entry_config);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $entry_config['success_message'],
            'reservation_id' => $rezerwacja_id,
            'type' => $entry_type,
        ], 201);
    }

    /**
     * Pobiera listę rezerwacji (tylko dla adminów)
     */
    public function get_rezerwacje($request) {
        $entry_type = $this->resolve_entry_type($request->get_param('type'));
        $entry_config = $this->get_entry_config($entry_type);

        $args = [
            'post_type' => $entry_config['post_type'],
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
            'type' => $this->detect_entry_type($post),
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
            'consents' => [
                'email' => $this->get_bool_meta($post->ID, '_consent_email'),
                'phone' => $this->get_bool_meta($post->ID, '_consent_phone'),
            ],
            'pickup_location' => get_post_meta($post->ID, '_pickup_location', true) ?: null,
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
    private function send_admin_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita, $entry_config) {
        $admin_email = get_option('admin_email');
        $subject = sprintf($entry_config['admin_email_subject'], $rezerwacja_id, $samochod->post_title);

        $message = $this->load_email_template($entry_config['admin_email_template'], [
            'rezerwacja_id' => $rezerwacja_id,
            'samochod' => $samochod,
            'params' => $params,
            'cena_miesieczna' => $cena_miesieczna,
            'cena_calkowita' => $cena_calkowita,
            'entry_type' => $entry_config['post_type'],
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Wysyła email potwierdzający do klienta (ZAKTUALIZOWANE)
     */
    private function send_customer_email($rezerwacja_id, $samochod, $params, $cena_miesieczna, $cena_calkowita, $entry_config) {
        $to = $params['email'];
        $subject = $entry_config['customer_email_subject'];

        $message = $this->load_email_template($entry_config['customer_email_template'], [
            'rezerwacja_id' => $rezerwacja_id,
            'samochod' => $samochod,
            'params' => $params,
            'cena_miesieczna' => $cena_miesieczna,
            'cena_calkowita' => $cena_calkowita,
            'entry_type' => $entry_config['post_type'],
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
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['reservation', 'order'],
                'description' => 'Typ zgłoszenia: rezerwacja (domyślnie) lub zamówienie',
            ],
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
            'consent_email' => [
                'required' => false,
                'type' => 'boolean',
                'default' => false,
                'description' => 'Zgoda na kontakt e-mail',
            ],
            'consent_phone' => [
                'required' => false,
                'type' => 'boolean',
                'default' => false,
                'description' => 'Zgoda na kontakt telefoniczny',
            ],
            'pickup_location' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['salon', 'home_delivery'],
                'description' => 'Preferowane miejsce wydania (salon/home_delivery)',
            ],
        ];
    }

    /**
     * Parametry zapytań GET
     */
    private function get_list_params() {
        return [
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['reservation', 'order'],
                'description' => 'Filtruj po typie zgłoszenia',
            ],
        ];
    }

    /**
     * Rozpoznaje typ zgłoszenia
     */
    private function resolve_entry_type($type) {
        if (is_string($type) && strtolower($type) === 'order') {
            return 'order';
        }

        return 'reservation';
    }

    /**
     * Zwraca konfigurację dla typu zgłoszenia
     */
    private function get_entry_config($entry_type) {
        return self::ENTRY_CONFIG[$entry_type] ?? self::ENTRY_CONFIG['reservation'];
    }

    /**
     * Wykrywa typ wpisu na podstawie meta danych
     */
    private function detect_entry_type($post) {
        $meta_type = get_post_meta($post->ID, '_entry_type', true);

        if (!empty($meta_type)) {
            return $meta_type;
        }

        return $post->post_type === 'order' ? 'order' : 'reservation';
    }

    /**
     * Sanityzacja wartości bool
     */
    private function sanitize_bool($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return false;
    }

    /**
     * Sanityzacja miejsca odbioru
     */
    private function sanitize_pickup_location($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = strtolower(trim($value));

        if (in_array($value, ['salon', 'home_delivery'], true)) {
            return $value;
        }

        return '';
    }

    /**
     * Pobiera meta bool jako bool
     */
    private function get_bool_meta($post_id, $meta_key) {
        $value = get_post_meta($post_id, $meta_key, true);
        return $value === '1';
    }
}



