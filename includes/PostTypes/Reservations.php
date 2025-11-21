<?php
namespace FlexMile\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Type dla Rezerwacji
 * UPDATED: używa meta pól zamiast taksonomii
 */
class Reservations {

    const POST_TYPE = 'reservation';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
    }

    /**
     * Rejestracja CPT Rezerwacja
     */
    public function register_post_type() {
        $labels = [
            'name' => 'Rezerwacje',
            'singular_name' => 'Rezerwacja',
            'menu_name' => 'Rezerwacje',
            'add_new' => 'Dodaj rezerwację',
            'add_new_item' => 'Dodaj nową rezerwację',
            'edit_item' => 'Edytuj rezerwację',
            'new_item' => 'Nowa rezerwacja',
            'view_item' => 'Zobacz rezerwację',
            'search_items' => 'Szukaj rezerwacji',
            'not_found' => 'Nie znaleziono rezerwacji',
            'all_items' => 'Wszystkie rezerwacje',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => 'reservations',
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title', 'custom-fields'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => false,
            ],
            'map_meta_cap' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Dodaje meta boxy
     */
    public function add_meta_boxes() {
        add_meta_box(
            'flexmile_rezerwacja_details',
            'Szczegóły rezerwacji',
            [$this, 'render_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'flexmile_rezerwacja_status',
            'Status rezerwacji',
            [$this, 'render_status_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'flexmile_rezerwacja_car',
            'Zarezerwowany samochód',
            [$this, 'render_car_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Renderuje meta box ze szczegółami klienta
     */
    public function render_details_meta_box($post) {
        $nazwa_firmy = get_post_meta($post->ID, '_company_name', true);
        $nip = get_post_meta($post->ID, '_tax_id', true);
        $imie = get_post_meta($post->ID, '_first_name', true);
        $nazwisko = get_post_meta($post->ID, '_last_name', true);
        $email = get_post_meta($post->ID, '_email', true);
        $telefon = get_post_meta($post->ID, '_phone', true);
        $ilosc_miesiecy = get_post_meta($post->ID, '_rental_months', true);
        $limit_km_rocznie = get_post_meta($post->ID, '_annual_mileage_limit', true);
        $cena_miesieczna = get_post_meta($post->ID, '_monthly_price', true);
        $cena_calkowita = get_post_meta($post->ID, '_total_price', true);
        $wiadomosc = get_post_meta($post->ID, '_message', true);
        ?>
        <table class="form-table">
            <tr>
                <th><strong>Nazwa firmy:</strong></th>
                <td>
                    <?php
                    if (!empty($nazwa_firmy)) {
                        echo esc_html($nazwa_firmy);
                    } else {
                        echo esc_html(trim($imie . ' ' . $nazwisko));
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><strong>NIP:</strong></th>
                <td><?php echo esc_html($nip); ?></td>
            </tr>
            <tr>
                <th><strong>Email:</strong></th>
                <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
            </tr>
            <tr>
                <th><strong>Telefon:</strong></th>
                <td><a href="tel:<?php echo esc_attr($telefon); ?>"><?php echo esc_html($telefon); ?></a></td>
            </tr>
            <tr style="background: #f0f9ff; border-top: 2px solid #0369a1; border-bottom: 2px solid #0369a1;">
                <th colspan="2" style="padding: 12px; text-align: center; font-size: 16px; color: #0369a1;">
                    💰 WYBRANA KONFIGURACJA
                </th>
            </tr>
            <tr>
                <th><strong>Okres wynajmu:</strong></th>
                <td><?php echo esc_html($ilosc_miesiecy); ?> miesięcy</td>
            </tr>
            <tr>
                <th><strong>Roczny limit km:</strong></th>
                <td><?php echo number_format($limit_km_rocznie, 0, ',', ' '); ?> km</td>
            </tr>
            <tr>
                <th><strong>Cena miesięczna:</strong></th>
                <td><strong style="color: #10b981; font-size: 15px;"><?php echo number_format($cena_miesieczna, 2, ',', ' '); ?> zł/mies.</strong></td>
            </tr>
            <tr style="background: #d1fae5;">
                <th><strong>Cena całkowita:</strong></th>
                <td><strong style="color: #059669; font-size: 18px;"><?php echo number_format($cena_calkowita, 2, ',', ' '); ?> zł</strong></td>
            </tr>
            <?php if ($wiadomosc): ?>
            <tr>
                <th><strong>Wiadomość:</strong></th>
                <td><?php echo nl2br(esc_html($wiadomosc)); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Renderuje meta box ze statusem
     */
    public function render_status_meta_box($post) {
        wp_nonce_field('flexmile_rezerwacja_status', 'flexmile_rezerwacja_status_nonce');

        $status = get_post_meta($post->ID, '_status', true);
        if (empty($status)) {
            $status = 'pending';
        }
        ?>
        <p>
            <label for="status"><strong>Status:</strong></label><br>
            <select id="status" name="status" class="widefat">
                <option value="pending" <?php selected($status, 'pending'); ?>>⏳ Oczekująca</option>
                <option value="approved" <?php selected($status, 'approved'); ?>>✅ Zatwierdzona</option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>>❌ Odrzucona</option>
                <option value="completed" <?php selected($status, 'completed'); ?>>🎉 Zrealizowana</option>
            </select>
        </p>
        <p class="description">Po zatwierdzeniu rezerwacji, samochód zostanie automatycznie oznaczony jako zarezerwowany.</p>
        <?php
    }

    /**
     * Renderuje meta box z informacją o samochodzie
     * UPDATED: używa meta pól zamiast taksonomii
     */
    public function render_car_meta_box($post) {
        $samochod_id = get_post_meta($post->ID, '_offer_id', true);

        if ($samochod_id) {
            $samochod = get_post($samochod_id);
            if ($samochod) {
                $thumbnail = get_the_post_thumbnail($samochod_id, 'thumbnail');
                echo '<p><a href="' . get_edit_post_link($samochod_id) . '">';
                if ($thumbnail) {
                    echo $thumbnail . '<br>';
                }
                echo '<strong>' . esc_html($samochod->post_title) . '</strong></a></p>';

                // Pobierz markę z meta pola
                $brand_slug = get_post_meta($samochod_id, '_car_brand_slug', true);
                if ($brand_slug) {
                    $config = $this->load_config();
                    if ($config && isset($config['brands'][$brand_slug])) {
                        echo '<p>Marka: ' . esc_html($config['brands'][$brand_slug]['name']) . '</p>';
                    }
                }

                // Pobierz model
                $model = get_post_meta($samochod_id, '_car_model', true);
                if ($model) {
                    echo '<p>Model: ' . esc_html($model) . '</p>';
                }

                // NOWOŚĆ: body_type i fuel_type z meta pól
                $body_type = get_post_meta($samochod_id, '_body_type', true);
                if ($body_type) {
                    echo '<p>Typ nadwozia: ' . esc_html($body_type) . '</p>';
                }

                $fuel_type = get_post_meta($samochod_id, '_fuel_type', true);
                if ($fuel_type) {
                    echo '<p>Paliwo: ' . esc_html($fuel_type) . '</p>';
                }
            }
        } else {
            echo '<p>Brak przypisanego samochodu</p>';
        }
    }

    /**
     * Ładuje config z JSON
     */
    private function load_config() {
        $config_file = FLEXMILE_PLUGIN_DIR . 'config.json';

        if (!file_exists($config_file)) {
            return null;
        }

        $json = file_get_contents($config_file);
        return json_decode($json, true);
    }

    /**
     * Zapisuje meta dane
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['flexmile_rezerwacja_status_nonce']) ||
            !wp_verify_nonce($_POST['flexmile_rezerwacja_status_nonce'], 'flexmile_rezerwacja_status')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['status'])) {
            $old_status = get_post_meta($post_id, '_status', true);
            $new_status = sanitize_text_field($_POST['status']);

            update_post_meta($post_id, '_status', $new_status);

            if ($new_status === 'approved' && $old_status !== 'approved') {
                $samochod_id = get_post_meta($post_id, '_offer_id', true);
                if ($samochod_id) {
                    update_post_meta($samochod_id, '_reservation_active', '1');
                }
            }

            if ($old_status === 'approved' && $new_status !== 'approved') {
                $samochod_id = get_post_meta($post_id, '_offer_id', true);
                if ($samochod_id) {
                    update_post_meta($samochod_id, '_reservation_active', '0');
                }
            }
        }
    }

    /**
     * Dodaje własne kolumny w liście rezerwacji
     */
    public function custom_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Klient';
        $new_columns['samochod'] = 'Samochód';
        $new_columns['status'] = 'Status';
        $new_columns['konfiguracja'] = 'Konfiguracja';
        $new_columns['cena'] = 'Cena';
        $new_columns['date'] = 'Data';

        return $new_columns;
    }

    /**
     * Wypełnia zawartość własnych kolumn
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'samochod':
                $samochod_id = get_post_meta($post_id, '_offer_id', true);
                if ($samochod_id) {
                    $samochod = get_post($samochod_id);
                    if ($samochod) {
                        echo '<a href="' . get_edit_post_link($samochod_id) . '">' . esc_html($samochod->post_title) . '</a>';
                    }
                }
                break;

            case 'status':
                $status = get_post_meta($post_id, '_status', true);
                $labels = [
                    'pending' => '<span style="color: orange;">⏳ Oczekująca</span>',
                    'approved' => '<span style="color: green;">✅ Zatwierdzona</span>',
                    'rejected' => '<span style="color: red;">❌ Odrzucona</span>',
                    'completed' => '<span style="color: blue;">🎉 Zrealizowana</span>',
                ];
                echo $labels[$status] ?? $labels['pending'];
                break;

            case 'konfiguracja':
                $ilosc_miesiecy = get_post_meta($post_id, '_rental_months', true);
                $limit_km = get_post_meta($post_id, '_annual_mileage_limit', true);
                echo '<strong>' . esc_html($ilosc_miesiecy) . ' mies.</strong> / ' . number_format($limit_km, 0, '', ' ') . ' km/rok';
                break;

            case 'cena':
                $cena = get_post_meta($post_id, '_total_price', true);
                echo '<strong>' . number_format($cena, 2, ',', ' ') . ' zł</strong>';
                break;
        }
    }
}
