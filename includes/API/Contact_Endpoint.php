<?php
namespace FlexMile\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint dla Formularza Kontaktowego
 * Wysyła maile bez zapisywania danych w bazie
 */
class Contact_Endpoint {

    const NAMESPACE = 'flexmile/v1';
    const BASE = 'contact';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Rejestruje endpoint API
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/' . self::BASE, [
            'methods' => 'POST',
            'callback' => [$this, 'send_contact_form'],
            'permission_callback' => '__return_true',
            'args' => $this->get_form_params(),
        ]);
    }

    /**
     * Obsługuje wysłanie formularza kontaktowego
     */
    public function send_contact_form($request) {
        $params = $request->get_params();

        // Walidacja wymaganych pól
        $required_fields = ['first_name', 'last_name', 'email', 'phone', 'consent_email'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf('Pole "%s" jest wymagane', $this->get_field_label($field)),
                    ['status' => 400]
                );
            }
        }

        // Walidacja emaila
        if (!is_email($params['email'])) {
            return new \WP_Error('invalid_email', 'Nieprawidłowy adres email', ['status' => 400]);
        }

        // Walidacja zgody email (musi być true/1)
        if ($params['consent_email'] !== true && $params['consent_email'] !== '1' && $params['consent_email'] !== 1) {
            return new \WP_Error(
                'consent_required',
                'Wymagana zgoda na kontakt mailowy',
                ['status' => 400]
            );
        }

        // Walidacja budżetu (jeśli podane)
        if (!empty($params['monthly_budget_from']) && !empty($params['monthly_budget_to'])) {
            $budget_from = floatval($params['monthly_budget_from']);
            $budget_to = floatval($params['monthly_budget_to']);

            if ($budget_from > $budget_to) {
                return new \WP_Error(
                    'invalid_budget',
                    'Budżet "od" nie może być większy niż budżet "do"',
                    ['status' => 400]
                );
            }
        }

        // Wysyłka maili
        $admin_sent = $this->send_admin_email($params);

        if (!$admin_sent) {
            return new \WP_Error(
                'email_failed',
                'Wystąpił problem z wysyłką wiadomości',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Wysłano.',
        ], 200);
    }

    /**
     * Wysyła email do administratora
     */
    private function send_admin_email($params) {
        $admin_email = get_option('admin_email');
        $site_name = get_option('blogname');

        $subject = sprintf('[%s] Nowe zapytanie kontaktowe', $site_name);

        $message = $this->load_email_template('admin-contact', [
            'params' => $params,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail($admin_email, $subject, $message, $headers);
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
     * Zwraca nazwę pola po polsku (dla komunikatów błędów)
     */
    private function get_field_label($field) {
        $labels = [
            'first_name' => 'Imię',
            'last_name' => 'Nazwisko',
            'email' => 'Email',
            'phone' => 'Telefon',
            'consent_email' => 'Zgoda na kontakt email',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Parametry dla formularza kontaktowego
     */
    private function get_form_params() {
        return [
            'first_name' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Imię klienta',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Nazwisko klienta',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'description' => 'Adres email klienta',
                'sanitize_callback' => 'sanitize_email',
            ],
            'phone' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Numer telefonu',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'monthly_budget_from' => [
                'required' => false,
                'type' => 'number',
                'description' => 'Budżet miesięczny od (opcjonalnie)',
                'minimum' => 0,
            ],
            'monthly_budget_to' => [
                'required' => false,
                'type' => 'number',
                'description' => 'Budżet miesięczny do (opcjonalnie)',
                'minimum' => 0,
            ],
            'message' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Wiadomość od klienta (opcjonalnie)',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'consent_email' => [
                'required' => true,
                'type' => 'boolean',
                'description' => 'Zgoda na kontakt email (wymagana)',
            ],
            'consent_phone' => [
                'required' => false,
                'type' => 'boolean',
                'description' => 'Zgoda na kontakt telefoniczny (opcjonalna)',
            ],
        ];
    }
}