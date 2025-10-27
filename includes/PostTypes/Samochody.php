<?php
namespace FlexMile\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Type dla Samochodów
 */
class Samochody {

    const POST_TYPE = 'samochod';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
    }

    /**
     * Rejestracja CPT Samochód
     */
    public function register_post_type() {
        $labels = [
            'name' => 'Samochody',
            'singular_name' => 'Samochód',
            'menu_name' => 'Samochody',
            'add_new' => 'Dodaj nowy',
            'add_new_item' => 'Dodaj nowy samochód',
            'edit_item' => 'Edytuj samochód',
            'new_item' => 'Nowy samochód',
            'view_item' => 'Zobacz samochód',
            'search_items' => 'Szukaj samochodów',
            'not_found' => 'Nie znaleziono samochodów',
            'all_items' => 'Wszystkie samochody',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => true, // Dla REST API
            'rest_base' => 'samochody',
            'menu_icon' => 'dashicons-car',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'capability_type' => 'post',
            'rewrite' => false, // Wyłączone bo headless
            'show_ui' => true,
            'show_in_menu' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Rejestracja taksonomii
     */
    public function register_taxonomies() {
        // Marka
        register_taxonomy('marka_samochodu', self::POST_TYPE, [
            'labels' => [
                'name' => 'Marki',
                'singular_name' => 'Marka',
                'add_new_item' => 'Dodaj markę',
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base' => 'marki',
            'rewrite' => false,
        ]);

        // Typ nadwozia
        register_taxonomy('typ_nadwozia', self::POST_TYPE, [
            'labels' => [
                'name' => 'Typy nadwozia',
                'singular_name' => 'Typ nadwozia',
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base' => 'typy-nadwozia',
            'rewrite' => false,
        ]);

        // Rodzaj paliwa
        register_taxonomy('rodzaj_paliwa', self::POST_TYPE, [
            'labels' => [
                'name' => 'Rodzaje paliwa',
                'singular_name' => 'Rodzaj paliwa',
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base' => 'paliwa',
            'rewrite' => false,
        ]);
    }

    /**
     * Dodaje meta boxy
     */
    public function add_meta_boxes() {
        add_meta_box(
            'flexmile_samochod_details',
            'Szczegóły samochodu',
            [$this, 'render_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'flexmile_samochod_pricing',
            'Kalkulator ceny wynajmu',
            [$this, 'render_pricing_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Renderuje meta box ze szczegółami
     */
    public function render_details_meta_box($post) {
        wp_nonce_field('flexmile_samochod_meta', 'flexmile_samochod_nonce');

        $rocznik = get_post_meta($post->ID, '_rocznik', true);
        $przebieg = get_post_meta($post->ID, '_przebieg', true);
        $moc = get_post_meta($post->ID, '_moc', true);
        $pojemnosc = get_post_meta($post->ID, '_pojemnosc', true);
        $skrzynia = get_post_meta($post->ID, '_skrzynia', true);
        $kolor = get_post_meta($post->ID, '_kolor', true);
        $liczba_miejsc = get_post_meta($post->ID, '_liczba_miejsc', true);
        $numer_vin = get_post_meta($post->ID, '_numer_vin', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="rocznik">Rocznik</label></th>
                <td><input type="number" id="rocznik" name="rocznik" value="<?php echo esc_attr($rocznik); ?>" class="regular-text" min="1900" max="<?php echo date('Y') + 1; ?>"></td>
            </tr>
            <tr>
                <th><label for="przebieg">Przebieg (km)</label></th>
                <td><input type="number" id="przebieg" name="przebieg" value="<?php echo esc_attr($przebieg); ?>" class="regular-text" min="0" step="1000"></td>
            </tr>
            <tr>
                <th><label for="moc">Moc (KM)</label></th>
                <td><input type="number" id="moc" name="moc" value="<?php echo esc_attr($moc); ?>" class="regular-text" min="0"></td>
            </tr>
            <tr>
                <th><label for="pojemnosc">Pojemność (cm³)</label></th>
                <td><input type="number" id="pojemnosc" name="pojemnosc" value="<?php echo esc_attr($pojemnosc); ?>" class="regular-text" min="0"></td>
            </tr>
            <tr>
                <th><label for="skrzynia">Skrzynia biegów</label></th>
                <td>
                    <select id="skrzynia" name="skrzynia">
                        <option value="">-- Wybierz --</option>
                        <option value="manualna" <?php selected($skrzynia, 'manualna'); ?>>Manualna</option>
                        <option value="automatyczna" <?php selected($skrzynia, 'automatyczna'); ?>>Automatyczna</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="kolor">Kolor</label></th>
                <td><input type="text" id="kolor" name="kolor" value="<?php echo esc_attr($kolor); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="liczba_miejsc">Liczba miejsc</label></th>
                <td><input type="number" id="liczba_miejsc" name="liczba_miejsc" value="<?php echo esc_attr($liczba_miejsc); ?>" class="regular-text" min="1" max="9"></td>
            </tr>
            <tr>
                <th><label for="numer_vin">Numer VIN</label></th>
                <td><input type="text" id="numer_vin" name="numer_vin" value="<?php echo esc_attr($numer_vin); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderuje meta box z kalkulatorem cen
     */
    public function render_pricing_meta_box($post) {
        $cena_bazowa = get_post_meta($post->ID, '_cena_bazowa', true);
        $cena_za_km = get_post_meta($post->ID, '_cena_za_km', true);
        $rezerwacja_aktywna = get_post_meta($post->ID, '_rezerwacja_aktywna', true);
        ?>
        <p>
            <label for="cena_bazowa"><strong>Cena bazowa / miesiąc</strong></label><br>
            <input type="number" id="cena_bazowa" name="cena_bazowa" value="<?php echo esc_attr($cena_bazowa); ?>" class="widefat" step="0.01" min="0"> zł
        </p>
        <p>
            <label for="cena_za_km"><strong>Dopłata za km (powyżej limitu)</strong></label><br>
            <input type="number" id="cena_za_km" name="cena_za_km" value="<?php echo esc_attr($cena_za_km); ?>" class="widefat" step="0.01" min="0"> zł
        </p>
        <hr>
        <p>
            <label>
                <input type="checkbox" name="rezerwacja_aktywna" value="1" <?php checked($rezerwacja_aktywna, '1'); ?>>
                <strong>Samochód zarezerwowany</strong>
            </label>
        </p>
        <p class="description">Zaznacz, jeśli samochód jest aktualnie zarezerwowany i nie powinien być wyświetlany w ofercie.</p>
        <?php
    }

    /**
     * Zapisuje meta dane
     */
    public function save_meta($post_id, $post) {
        // Sprawdzenie nonce
        if (!isset($_POST['flexmile_samochod_nonce']) || 
            !wp_verify_nonce($_POST['flexmile_samochod_nonce'], 'flexmile_samochod_meta')) {
            return;
        }

        // Autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Uprawnienia
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Zapisz wszystkie pola
        $fields = [
            '_rocznik' => 'intval',
            '_przebieg' => 'intval',
            '_moc' => 'intval',
            '_pojemnosc' => 'intval',
            '_skrzynia' => 'sanitize_text_field',
            '_kolor' => 'sanitize_text_field',
            '_liczba_miejsc' => 'intval',
            '_numer_vin' => 'sanitize_text_field',
            '_cena_bazowa' => 'floatval',
            '_cena_za_km' => 'floatval',
        ];

        foreach ($fields as $field => $sanitize) {
            $key = ltrim($field, '_');
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $field, $sanitize($_POST[$key]));
            }
        }

        // Checkbox rezerwacji
        $rezerwacja = isset($_POST['rezerwacja_aktywna']) ? '1' : '0';
        update_post_meta($post_id, '_rezerwacja_aktywna', $rezerwacja);
    }
}
