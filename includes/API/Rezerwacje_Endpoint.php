<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Rezerwacji
 */
class Rezerwacje_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'rezerwacje';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpointy API
     */
    public function register_routes() {
        // Tworzenie rezerwacji
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'POST',
            'callback' => [$this, 'create_rezerwacja'],
            'permission_callback' => '__return_true',
            'args' => $this->get_create_params(),
        ]);

        // Lista rezerwacji (tylko dla zalogowanych adminów)
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'GET',
            'callback' => [$this, 'get_rezerwacje'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Tworzy nową rezerwację
     */
    public function create_rezerwacja($request) {
        $params = $request->get_params();

        // Walidacja samochodu
        $samochod_id = intval($params['samochod_id']);
        $samochod = get_post($samochod_id);

        if (!$samochod || $samochod->post_type !== 'samochod') {
            return new \WP_Error('invalid_car', 'Nieprawidłowy ID samochodu', ['status' => 400]);
        }

        // Sprawdź czy samochód nie jest zarezerwowany
        $rezerwacja_aktywna = get_post_meta($samochod_id, '_rezerwacja_aktywna', true);
        if ($rezerwacja_aktywna === '1') {
            return new \WP_Error('car_reserved', 'Ten samochód jest już zarezerwowany', ['status' => 400]);
        }

        // Pobierz ceny samochodu
        $cena_bazowa = (float) get_post_meta($samochod_id, '_cena_bazowa', true);
        $cena_za_km = (float) get_post_meta($samochod_id, '_cena_za_km', true);

        // Oblicz cenę całkowitą
        $ilosc_miesiecy = intval($params['ilosc_miesiecy']);
        $ilosc_km = intval($params['ilosc_km']);
        
        $cena_calkowita = $this->calculate_price($cena_bazowa, $cena_za_km, $ilosc_miesiecy, $ilosc_km);

        // Przygotuj tytuł rezerwacji
        $tytul = sprintf(
            'Rezerwacja: %s - %s %s',
            sanitize_text_field($params['imie']) . ' ' . sanitize_text_field($params['nazwisko']),
            $samochod->post_title,
            date('Y-m-d H:i')
        );

        // Utwórz post rezerwacji
        $post_data = [
            'post_type' => 'rezerwacja',
            'post_title' => $tytul,
            'post_status' => 'publish',
            'post_author' => 1, // Admin
        ];

        $rezerwacja_id = wp_insert_post($post_data);

        if (is_wp_error($rezerwacja_id)) {
            return new \WP_Error('create_failed', 'Nie udało się utworzyć rezerwacji', ['status' => 500]);
        }

        // Zapisz meta dane
        update_post_meta($rezerwacja_id, '_samochod_id', $samochod_id);
        update_post_meta($rezerwacja_id, '_imie', sanitize_text_field($params['imie']));
        update_post_meta($rezerwacja_id, '_nazwisko', sanitize_text_field($params['nazwisko']));
        update_post_meta($rezerwacja_id, '_email', sanitize_email($params['email']));
        update_post_meta($rezerwacja_id, '_telefon', sanitize_text_field($params['telefon']));
        update_post_meta($rezerwacja_id, '_ilosc_miesiecy', $ilosc_miesiecy);
        update_post_meta($rezerwacja_id, '_ilosc_km', $ilosc_km);
        update_post_meta($rezerwacja_id, '_cena_calkowita', $cena_calkowita);
        update_post_meta($rezerwacja_id, '_status_rezerwacji', 'pending');

        if (!empty($params['wiadomosc'])) {
            update_post_meta($rezerwacja_id, '_wiadomosc', sanitize_textarea_field($params['wiadomosc']));
        }

        // Wyślij maile
        $this->send_admin_email($rezerwacja_id, $samochod, $params, $cena_calkowita);
        $this->send_customer_email($rezerwacja_id, $samochod, $params, $cena_calkowita);

        // Zwróć odpowiedź
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Rezerwacja została złożona pomyślnie',
            'rezerwacja_id' => $rezerwacja_id,
            'cena_calkowita' => $cena_calkowita,
        ], 201);
    }

    /**
     * Pobiera listę rezerwacji (tylko dla adminów)
     */
    public function get_rezerwacje($request) {
        $args = [
            'post_type' => 'rezerwacja',
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
     * Przygotowuje dane rezerwacji
     */
    private function prepare_rezerwacja_data($post) {
        $samochod_id = get_post_meta($post->ID, '_samochod_id', true);
        $samochod = get_post($samochod_id);

        return [
            'id' => $post->ID,
            'data_utworzenia' => $post->post_date,
            'status' => get_post_meta($post->ID, '_status_rezerwacji', true),
            'klient' => [
                'imie' => get_post_meta($post->ID, '_imie', true),
                'nazwisko' => get_post_meta($post->ID, '_nazwisko', true),
                'email' => get_post_meta($post->ID, '_email', true),
                'telefon' => get_post_meta($post->ID, '_telefon', true),
            ],
            'samochod' => [
                'id' => $samochod_id,
                'nazwa' => $samochod ? $samochod->post_title : '',
            ],
            'szczegoly' => [
                'ilosc_miesiecy' => (int) get_post_meta($post->ID, '_ilosc_miesiecy', true),
                'ilosc_km' => (int) get_post_meta($post->ID, '_ilosc_km', true),
                'cena_calkowita' => (float) get_post_meta($post->ID, '_cena_calkowita', true),
            ],
            'wiadomosc' => get_post_meta($post->ID, '_wiadomosc', true),
        ];
    }

    /**
     * Oblicza cenę wynajmu
     */
    private function calculate_price($cena_bazowa, $cena_za_km, $ilosc_miesiecy, $ilosc_km) {
        $cena_miesieczna = $cena_bazowa * $ilosc_miesiecy;
        
        // Przykładowy limit km: 1000 km/miesiąc
        $limit_km = 1000 * $ilosc_miesiecy;
        $nadwyzka_km = max(0, $ilosc_km - $limit_km);
        $cena_za_nadwyzke = $nadwyzka_km * $cena_za_km;
        
        return $cena_miesieczna + $cena_za_nadwyzke;
    }

    /**
     * Wysyła email do administratora
     */
    private function send_admin_email($rezerwacja_id, $samochod, $params, $cena_calkowita) {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[FlexMile] Nowa rezerwacja #%d - %s', $rezerwacja_id, $samochod->post_title);
        
        $message = "Nowa rezerwacja w systemie FlexMile!\n\n";
        $message .= "=== SZCZEGÓŁY REZERWACJI ===\n";
        $message .= sprintf("Numer rezerwacji: #%d\n", $rezerwacja_id);
        $message .= sprintf("Data: %s\n\n", date('Y-m-d H:i:s'));
        
        $message .= "=== SAMOCHÓD ===\n";
        $message .= sprintf("Nazwa: %s\n", $samochod->post_title);
        $message .= sprintf("Link: %s\n\n", admin_url('post.php?post=' . $samochod->ID . '&action=edit'));
        
        $message .= "=== KLIENT ===\n";
        $message .= sprintf("Imię i nazwisko: %s %s\n", $params['imie'], $params['nazwisko']);
        $message .= sprintf("Email: %s\n", $params['email']);
        $message .= sprintf("Telefon: %s\n\n", $params['telefon']);
        
        $message .= "=== PARAMETRY WYNAJMU ===\n";
        $message .= sprintf("Okres: %d miesięcy\n", $params['ilosc_miesiecy']);
        $message .= sprintf("Planowany przebieg: %d km\n", $params['ilosc_km']);
        $message .= sprintf("Cena całkowita: %.2f zł\n\n", $cena_calkowita);
        
        if (!empty($params['wiadomosc'])) {
            $message .= "=== WIADOMOŚĆ OD KLIENTA ===\n";
            $message .= $params['wiadomosc'] . "\n\n";
        }
        
        $message .= "Aby zarządzać tą rezerwacją, przejdź do:\n";
        $message .= admin_url('post.php?post=' . $rezerwacja_id . '&action=edit');

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Wysyła email potwierdzający do klienta
     */
    private function send_customer_email($rezerwacja_id, $samochod, $params, $cena_calkowita) {
        $to = $params['email'];
        $subject = 'Potwierdzenie rezerwacji - FlexMile';
        
        $message = sprintf("Witaj %s!\n\n", $params['imie']);
        $message .= "Dziękujemy za złożenie rezerwacji w FlexMile.\n\n";
        
        $message .= "=== SZCZEGÓŁY TWOJEJ REZERWACJI ===\n";
        $message .= sprintf("Numer rezerwacji: #%d\n", $rezerwacja_id);
        $message .= sprintf("Samochód: %s\n", $samochod->post_title);
        $message .= sprintf("Okres wynajmu: %d miesięcy\n", $params['ilosc_miesiecy']);
        $message .= sprintf("Planowany przebieg: %d km\n", $params['ilosc_km']);
        $message .= sprintf("Szacowana cena: %.2f zł\n\n", $cena_calkowita);
        
        $message .= "Twoja rezerwacja oczekuje na zatwierdzenie.\n";
        $message .= "Skontaktujemy się z Tobą wkrótce!\n\n";
        
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół FlexMile\n";
        $message .= get_option('blogname');

        wp_mail($to, $subject, $message);
    }

    /**
     * Parametry dla tworzenia rezerwacji
     */
    private function get_create_params() {
        return [
            'samochod_id' => [
                'required' => true,
                'type' => 'integer',
                'description' => 'ID samochodu',
            ],
            'imie' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Imię klienta',
            ],
            'nazwisko' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Nazwisko klienta',
            ],
            'email' => [
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'description' => 'Email klienta',
            ],
            'telefon' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Numer telefonu',
            ],
            'ilosc_miesiecy' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Liczba miesięcy wynajmu',
            ],
            'ilosc_km' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Planowana liczba kilometrów',
            ],
            'wiadomosc' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Dodatkowa wiadomość od klienta',
            ],
        ];
    }
}
