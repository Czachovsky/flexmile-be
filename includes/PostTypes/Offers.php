<?php
namespace FlexMile\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Type dla Samochodów
 * body_type i fuel_type są teraz META POLAMI (tak jak transmission)
 * Z systemem reference ID: FLX-LA-YYYY-XXX
 */
class Offers {

    const POST_TYPE = 'offer';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        add_action('wp_ajax_flexmile_generate_price_matrix', [$this, 'ajax_generate_price_matrix']);
        add_action('wp_ajax_flexmile_get_models', [$this, 'ajax_get_models']);

        // Generuj reference ID przy tworzeniu nowego posta
        add_action('transition_post_status', [$this, 'generate_reference_id'], 10, 3);

        // Dodaj kolumnę z reference ID w liście postów
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_reference_id_column']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
    }

    /**
     * Generuje unikalny reference ID w formacie FLX-LA-YYYY-XXX
     */
    public function generate_reference_id($new_status, $old_status, $post) {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        $existing_ref = get_post_meta($post->ID, '_car_reference_id', true);
        if (!empty($existing_ref)) {
            return;
        }

        $reference_id = $this->get_next_reference_id();
        update_post_meta($post->ID, '_car_reference_id', $reference_id);
    }

    /**
     * Pobiera następny dostępny reference ID
     */
    private function get_next_reference_id() {
        $current_year = date('Y');
        $option_key = 'flexmile_last_car_number';

        $last_number = get_option($option_key, 100);
        $new_number = $last_number + 1;
        update_option($option_key, $new_number);

        return sprintf('FLX-LA-%s-%03d', $current_year, $new_number);
    }

    /**
     * Dodaje kolumnę Reference ID do listy postów
     */
    public function add_reference_id_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['car_reference_id'] = '🔖 ID';
                $new_columns['car_status'] = 'Status';
            }
        }
        return $new_columns;
    }

    /**
     * Wyświetla Reference ID w kolumnie
     */
    public function render_custom_columns($column, $post_id) {
        if ($column === 'car_reference_id') {
            $ref_id = get_post_meta($post_id, '_car_reference_id', true);
            if ($ref_id) {
                echo '<strong style="color: #667eea; font-family: monospace; font-size: 13px;">' . esc_html($ref_id) . '</strong>';
            } else {
                echo '<span style="color: #94a3b8;">—</span>';
            }
            return;
        }

        if ($column === 'car_status') {
            $badges = $this->build_status_badges($post_id);
            if (!empty($badges)) {
                echo implode('<br>', $badges);
            } else {
                echo '<span style="color: #94a3b8;">Brak</span>';
            }
        }
    }

    /**
     * Buduje znaczniki statusu na podstawie powiązanych rezerwacji i zamówień
     */
    private function build_status_badges($post_id) {
        $badges = [];

        $reserved_active = get_post_meta($post_id, '_reservation_active', true) === '1';
        if ($reserved_active) {
            $badges[] = '<span style="display:inline-flex;align-items:center;gap:6px;background:#fee2e2;color:#b91c1c;font-weight:600;padding:3px 10px;border-radius:999px;font-size:12px;">🔒 Zarezerwowany</span>';
        } elseif ($this->has_entry_with_status($post_id, 'reservation', ['pending'])) {
            $badges[] = '<span style="display:inline-flex;align-items:center;gap:6px;background:#fef3c7;color:#92400e;font-weight:600;padding:3px 10px;border-radius:999px;font-size:12px;">⏳ Rezerwacja oczekująca</span>';
        }

        if ($this->has_entry_with_status($post_id, 'order', ['pending', 'approved'])) {
            $badges[] = '<span style="display:inline-flex;align-items:center;gap:6px;background:#dbeafe;color:#1d4ed8;font-weight:600;padding:3px 10px;border-radius:999px;font-size:12px;">🛒 Zamówienie</span>';
        }

        return $badges;
    }

    /**
     * Sprawdza, czy istnieje wpis (rezerwacja/zamówienie) w określonym statusie dla danego samochodu
     */
    private function has_entry_with_status($offer_id, $post_type, $statuses) {
        $cache_key = $post_type . '_' . $offer_id . '_' . implode('_', $statuses);
        static $cache = [];

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $query = new \WP_Query([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_offer_id',
                    'value' => $offer_id,
                ],
                [
                    'key' => '_status',
                    'value' => $statuses,
                    'compare' => 'IN',
                ],
            ],
        ]);

        $has_entry = !empty($query->posts);
        $cache[$cache_key] = $has_entry;

        return $has_entry;
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
            'show_in_rest' => true,
            'rest_base' => 'offers',
            'menu_icon' => 'dashicons-car',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'capability_type' => 'post',
            'rewrite' => false,
            'show_ui' => true,
            'show_in_menu' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Ładuje skrypty admina
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        if (('post.php' === $hook || 'post-new.php' === $hook) && self::POST_TYPE === $post_type) {
            wp_enqueue_media();

            wp_enqueue_script(
                'flexmile-gallery',
                plugins_url('../../assets/admin-gallery.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );

            wp_enqueue_script(
                'flexmile-dropdown',
                plugins_url('../../assets/admin-dropdown.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );

            wp_localize_script('flexmile-dropdown', 'flexmileDropdown', [
                'nonce' => wp_create_nonce('flexmile_dropdown')
            ]);

            wp_enqueue_style(
                'flexmile-admin-styles',
                plugins_url('../../assets/admin-styles.css', __FILE__),
                [],
                '1.0'
            );

            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('wp-jquery-ui-dialog');

            wp_add_inline_script('jquery-ui-tabs', '
                jQuery(document).ready(function($) {
                    $(".flexmile-tabs").tabs();
                });
            ');
        }
    }

    /**
     * Dodaje meta boxy
     */
    public function add_meta_boxes() {
        add_meta_box(
            'flexmile_car_reference',
            'ID oferty',
            [$this, 'render_reference_id_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'flexmile_samochod_gallery',
            'Galeria zdjęć',
            [$this, 'render_gallery_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'flexmile_samochod_details',
            'Szczegóły samochodu',
            [$this, 'render_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'flexmile_samochod_wyposazenie',
            'Wyposażenie standardowe',
            [$this, 'render_wyposazenie_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'flexmile_samochod_wyposazenie_dodatkowe',
            'Wyposażenie dodatkowe',
            [$this, 'render_wyposazenie_dodatkowe_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'flexmile_samochod_pricing',
            '💰 Konfiguracja cen',
            [$this, 'render_pricing_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'flexmile_samochod_flags',
            'Statusy i wyróżnienie',
            [$this, 'render_flags_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Renderuje meta box z Reference ID
     */
    public function render_reference_id_meta_box($post) {
        $ref_id = get_post_meta($post->ID, '_car_reference_id', true);

        if (empty($ref_id)) {
            ?>
            <div style="padding: 15px; text-align: center; background: #f8fafc; border-radius: 8px;">
                <p style="margin: 0; color: #64748b; font-size: 13px;">
                    ID oferty zostanie wygenerowane<br>automatycznie po opublikowaniu oferty.
                </p>
            </div>
            <?php
        } else {
            ?>
            <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; text-align: center;">
                <div style="font-size: 11px; color: rgba(255,255,255,0.8); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;">
                    ID Oferty
                </div>
                <div style="font-size: 24px; font-weight: bold; color: white; font-family: monospace; letter-spacing: 2px;">
                    <?php echo esc_html($ref_id); ?>
                </div>
                <div style="margin-top: 10px; font-size: 11px; color: rgba(255,255,255,0.7);">
                    To ID jest unikalne i nie może być zmienione
                </div>
            </div>
            <?php
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
     * AJAX: Pobiera modele dla wybranej marki
     */
    public function ajax_get_models() {
        check_ajax_referer('flexmile_dropdown', 'nonce');

        $brand_slug = isset($_POST['brand_slug']) ? sanitize_text_field($_POST['brand_slug']) : '';

        if (empty($brand_slug)) {
            wp_send_json_error(['message' => 'Nie podano marki']);
        }

        $config = $this->load_config();

        if (!$config || !isset($config['brands'][$brand_slug])) {
            wp_send_json_error(['message' => 'Nie znaleziono marki']);
        }

        $models = $config['brands'][$brand_slug]['models'];

        wp_send_json_success(['models' => $models]);
    }

    /**
     * Renderuje meta box z galerią
     */
    public function render_gallery_meta_box($post) {
        wp_nonce_field('flexmile_samochod_meta', 'flexmile_samochod_nonce');

        $gallery_ids = get_post_meta($post->ID, '_gallery', true);
        $gallery_ids_array = !empty($gallery_ids) ? explode(',', $gallery_ids) : [];
        ?>
        <div class="flexmile-gallery-container">
            <div class="flexmile-gallery-images">
                <?php if (!empty($gallery_ids_array)): ?>
                    <?php foreach ($gallery_ids_array as $img_id): ?>
                        <?php if ($img_id): ?>
                            <div class="gallery-item" data-id="<?php echo esc_attr($img_id); ?>">
                                <?php echo wp_get_attachment_image($img_id, 'thumbnail'); ?>
                                <button type="button" class="remove-gallery-image">&times;</button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <input type="hidden" id="flexmile_gallery_ids" name="gallery" value="<?php echo esc_attr($gallery_ids); ?>">

            <button type="button" class="button button-primary" id="flexmile_add_gallery_images">
                📷 Dodaj zdjęcia do galerii
            </button>

            <p class="description" style="margin-top: 10px;">
                💡 Możesz dodać wiele zdjęć. Przeciągnij aby zmienić kolejność.
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var frame;

            $('#flexmile_add_gallery_images').on('click', function(e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Wybierz zdjęcia do galerii',
                    button: { text: 'Dodaj do galerii' },
                    multiple: true
                });

                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    var ids = $('#flexmile_gallery_ids').val().split(',').filter(Boolean);

                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        ids.push(attachment.id);

                        $('.flexmile-gallery-images').append(
                            '<div class="gallery-item" data-id="' + attachment.id + '">' +
                                '<img src="' + attachment.sizes.thumbnail.url + '">' +
                                '<button type="button" class="remove-gallery-image">&times;</button>' +
                            '</div>'
                        );
                    });

                    $('#flexmile_gallery_ids').val(ids.join(','));
                });

                frame.open();
            });

            $(document).on('click', '.remove-gallery-image', function() {
                var item = $(this).closest('.gallery-item');
                var id = item.data('id');
                var ids = $('#flexmile_gallery_ids').val().split(',').filter(function(i) {
                    return i != id;
                });

                $('#flexmile_gallery_ids').val(ids.join(','));
                item.remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Renderuje meta box ze szczegółami
     * UPDATED: body_type i fuel_type są teraz dropdownami (tak jak transmission)
     */
    public function render_details_meta_box($post) {
        $config = $this->load_config();

        $car_brand_slug = get_post_meta($post->ID, '_car_brand_slug', true);
        $car_model = get_post_meta($post->ID, '_car_model', true);

        $rocznik = get_post_meta($post->ID, '_year', true);
        $moc = get_post_meta($post->ID, '_horsepower', true);
        $pojemnosc = get_post_meta($post->ID, '_engine_capacity', true);
        $skrzynia = get_post_meta($post->ID, '_transmission', true);
        $body_type = get_post_meta($post->ID, '_body_type', true); // META POLE
        $fuel_type = get_post_meta($post->ID, '_fuel_type', true); // META POLE
        $kolor = get_post_meta($post->ID, '_color', true);
        $liczba_miejsc = get_post_meta($post->ID, '_seats', true);
        $liczba_drzwi = get_post_meta($post->ID, '_doors', true);
        $naped = get_post_meta($post->ID, '_drivetrain', true);
        $silnik = get_post_meta($post->ID, '_engine', true);

        // Pobierz typy nadwozia i paliwa z config.json
        $body_types = $config['body_types'] ?? [];
        $fuel_types = $config['fuel_types'] ?? [];
        ?>
        <div class="flexmile-tabs">
            <ul class="flexmile-tab-nav">
                <li><a href="#tab-podstawowe">📋 Podstawowe</a></li>
                <li><a href="#tab-silnik">🔧 Silnik i napęd</a></li>
                <li><a href="#tab-wyglad">🎨 Wygląd i wnętrze</a></li>
            </ul>

            <div id="tab-podstawowe" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field">
                        <label for="car_brand">
                            <span class="flexmile-label-icon">🏷️</span>
                            <strong>Marka</strong>
                        </label>
                        <select id="car_brand" name="car_brand_slug" class="flexmile-input" required>
                            <option value="">-- Wybierz markę --</option>
                            <?php if ($config && isset($config['brands'])): ?>
                                <?php foreach ($config['brands'] as $slug => $brand): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($car_brand_slug, $slug); ?>>
                                        <?php echo esc_html($brand['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="description">Wybierz markę z listy</p>
                    </div>

                    <div class="flexmile-field">
                        <label for="car_model">
                            <span class="flexmile-label-icon">🚗</span>
                            <strong>Model</strong>
                        </label>
                        <select id="car_model"
                                name="car_model"
                                class="flexmile-input"
                                data-initial-model="<?php echo esc_attr($car_model); ?>"
                                <?php echo empty($car_brand_slug) ? 'disabled' : ''; ?>>
                            <option value="">-- Najpierw wybierz markę --</option>
                        </select>
                        <p class="description">Dostępne po wybraniu marki</p>
                    </div>

                    <div class="flexmile-field">
                        <label for="body_type">
                            <span class="flexmile-label-icon">🚙</span>
                            <strong>Typ nadwozia</strong>
                        </label>
                        <select id="body_type" name="body_type" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <?php foreach ($body_types as $type): ?>
                                <option value="<?php echo esc_attr($type['id']); ?>" <?php selected($body_type, $type['id']); ?>>
                                    <?php echo esc_html($type['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="fuel_type">
                            <span class="flexmile-label-icon">⛽</span>
                            <strong>Rodzaj paliwa</strong>
                        </label>
                        <select id="fuel_type" name="fuel_type" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <?php foreach ($fuel_types as $fuel): ?>
                                <option value="<?php echo esc_attr($fuel['id']); ?>" <?php selected($fuel_type, $fuel['id']); ?>>
                                    <?php echo esc_html($fuel['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="year">
                            <span class="flexmile-label-icon">📅</span>
                            <strong>Rocznik</strong>
                        </label>
                        <input type="number"
                               id="year"
                               name="year"
                               value="<?php echo esc_attr($rocznik); ?>"
                               class="flexmile-input"
                               min="1900"
                               max="<?php echo date('Y') + 1; ?>"
                               placeholder="np. 2022">
                    </div>

                    <div class="flexmile-field">
                        <label for="seats">
                            <span class="flexmile-label-icon">👥</span>
                            <strong>Liczba miejsc</strong>
                        </label>
                        <select id="seats" name="seats" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <?php for($i = 2; $i <= 9; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($liczba_miejsc, $i); ?>>
                                    <?php echo $i; ?> miejsc
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="doors">
                            <span class="flexmile-label-icon">🚪</span>
                            <strong>Liczba drzwi</strong>
                        </label>
                        <select id="doors" name="doors" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="2" <?php selected($liczba_drzwi, '2'); ?>>2/3 drzwi</option>
                            <option value="4" <?php selected($liczba_drzwi, '4'); ?>>4/5 drzwi</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="tab-silnik" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-full">
                        <label for="engine">
                            <span class="flexmile-label-icon">⚙️</span>
                            <strong>Oznaczenie silnika</strong>
                        </label>
                        <input type="text"
                               id="engine"
                               name="engine"
                               value="<?php echo esc_attr($silnik); ?>"
                               class="flexmile-input"
                               placeholder="np. 2.0 TDI, 1.5 TSI, 3.0d xDrive">
                        <p class="description">Pełna nazwa/model silnika</p>
                    </div>

                    <div class="flexmile-field">
                        <label for="horsepower">
                            <span class="flexmile-label-icon">💪</span>
                            <strong>Moc (KM)</strong>
                        </label>
                        <input type="number"
                               id="horsepower"
                               name="horsepower"
                               value="<?php echo esc_attr($moc); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 150">
                    </div>

                    <div class="flexmile-field">
                        <label for="engine_capacity">
                            <span class="flexmile-label-icon">🔋</span>
                            <strong>Pojemność (cm³)</strong>
                        </label>
                        <input type="number"
                               id="engine_capacity"
                               name="engine_capacity"
                               value="<?php echo esc_attr($pojemnosc); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 1984">
                    </div>

                    <div class="flexmile-field">
                        <label for="transmission">
                            <span class="flexmile-label-icon">⚡</span>
                            <strong>Skrzynia biegów</strong>
                        </label>
                        <select id="transmission" name="transmission" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="manual" <?php selected($skrzynia, 'manual'); ?>>Manualna</option>
                            <option value="automatic" <?php selected($skrzynia, 'automatic'); ?>>Automatyczna</option>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="drivetrain">
                            <span class="flexmile-label-icon">🔄</span>
                            <strong>Napęd</strong>
                        </label>
                        <select id="drivetrain" name="drivetrain" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="FWD" <?php selected($naped, 'FWD'); ?>>FWD (przedni)</option>
                            <option value="RWD" <?php selected($naped, 'RWD'); ?>>RWD (tylny)</option>
                            <option value="AWD" <?php selected($naped, 'AWD'); ?>>AWD (4x4)</option>
                            <option value="4WD" <?php selected($naped, '4WD'); ?>>4WD (4x4 dołączany)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="tab-wyglad" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-full">
                        <label for="color">
                            <span class="flexmile-label-icon">🎨</span>
                            <strong>Kolor lakieru</strong>
                        </label>
                        <input type="text"
                               id="color"
                               name="color"
                               value="<?php echo esc_attr($kolor); ?>"
                               class="flexmile-input"
                               placeholder="np. Czarny metalik, Srebrny perła">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje meta box z wyposażeniem standardowym
     */
    public function render_wyposazenie_meta_box($post) {
        $wyposazenie = get_post_meta($post->ID, '_standard_equipment', true);
        ?>
        <div class="flexmile-wyposazenie">
            <div class="wyposazenie-wlasne">
                <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">
                    📝 Wpisz wyposażenie standardowe - każda pozycja w nowej linii
                </p>
                <textarea name="standard_equipment"
                          rows="10"
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: 'Courier New', monospace; line-height: 1.6;"
                          placeholder="ABS&#10;ESP&#10;Klimatyzacja automatyczna&#10;Nawigacja GPS&#10;Bluetooth&#10;Poduszki powietrzne&#10;Elektryczne szyby&#10;Światła LED"><?php echo esc_textarea($wyposazenie); ?></textarea>
                <p class="description" style="margin-top: 10px;">
                    💡 Każda nowa linia to jeden element wyposażenia
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje meta box z wyposażeniem dodatkowym
     */
    public function render_wyposazenie_dodatkowe_meta_box($post) {
        $wyposazenie = get_post_meta($post->ID, '_additional_equipment', true);
        ?>
        <div class="flexmile-wyposazenie">
            <div class="wyposazenie-wlasne">
                <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">
                    📝 Wpisz wyposażenie dodatkowe - każda pozycja w nowej linii
                </p>
                <textarea name="additional_equipment"
                          rows="10"
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: 'Courier New', monospace; line-height: 1.6;"
                          placeholder="Skórzana tapicerka&#10;Dach panoramiczny&#10;Kamera 360°&#10;Asystent parkowania&#10;Tempomat adaptacyjny&#10;System audio premium&#10;Felgi aluminiowe 19&#34;&#10;Hak holowniczy"><?php echo esc_textarea($wyposazenie); ?></textarea>
                <p class="description" style="margin-top: 10px;">
                    💡 Każda nowa linia to jeden element wyposażenia
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje meta box z konfiguracją cen
     */
    public function render_pricing_meta_box($post) {
        $rezerwacja_aktywna = get_post_meta($post->ID, '_reservation_active', true);

        $config = get_post_meta($post->ID, '_pricing_config', true);

        if (empty($config)) {
            $config = [
                'rental_periods' => [12, 24, 36, 48],
                'mileage_limits' => [10000, 15000, 20000],
                'prices' => []
            ];
        }

        $cena_najnizsza = get_post_meta($post->ID, '_lowest_price', true);
        ?>
        <div style="padding: 5px;">
            <?php if ($cena_najnizsza): ?>
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; color: white;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">💰 NAJNIŻSZA CENA</div>
                <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($cena_najnizsza, 2, ',', ' '); ?> zł/mies.</div>
                <div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Cena widoczna na liście</div>
            </div>
            <?php endif; ?>

            <div style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #1e293b;">
                    📅 Dostępne okresy wynajmu (miesiące)
                </label>
                <input type="text"
                       id="flexmile_rental_periods"
                       name="rental_periods"
                       value="<?php echo esc_attr(implode(',', $config['rental_periods'])); ?>"
                       class="widefat"
                       style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;"
                       placeholder="np. 12,24,36,48">
                <p class="description" style="margin-top: 5px;">Oddziel przecinkami, np: 12,24,36,48</p>
            </div>

            <div style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #1e293b;">
                    🛣️ Roczne limity kilometrów
                </label>
                <input type="text"
                       id="flexmile_mileage_limits"
                       name="mileage_limits"
                       value="<?php echo esc_attr(implode(',', $config['mileage_limits'])); ?>"
                       class="widefat"
                       style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;"
                       placeholder="np. 10000,15000,20000">
                <p class="description" style="margin-top: 5px;">Oddziel przecinkami, np: 10000,15000,20000</p>
            </div>

            <button type="button"
                    id="flexmile_generate_price_matrix"
                    class="button button-secondary"
                    style="width: 100%; padding: 10px; margin-bottom: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; font-weight: 600; cursor: pointer;">
                🔄 Wygeneruj tabelę cen
            </button>

            <div id="flexmile_price_matrix" style="margin-top: 15px;">
                <?php $this->render_price_matrix($config); ?>
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <p style="background: #fef3c7; padding: 12px; border-radius: 6px; border-left: 4px solid #f59e0b;">
                <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                    <input type="checkbox"
                           name="reservation_active"
                           value="1"
                           <?php checked($rezerwacja_aktywna, '1'); ?>
                           style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                    <span><strong>🔒 Samochód zarezerwowany</strong></span>
                </label>
            </p>
            <p class="description" style="margin-top: 8px;">
                Zaznacz jeśli samochód jest aktualnie zarezerwowany.
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#flexmile_generate_price_matrix').on('click', function() {
                var button = $(this);
                var okresy = $('#flexmile_rental_periods').val();
                var limity = $('#flexmile_mileage_limits').val();

                if (!okresy || !limity) {
                    alert('Uzupełnij okresy i limity kilometrów!');
                    return;
                }

                button.prop('disabled', true).text('⏳ Generowanie...');

                $.post(ajaxurl, {
                    action: 'flexmile_generate_price_matrix',
                    post_id: <?php echo $post->ID; ?>,
                    okresy: okresy,
                    limity: limity,
                    nonce: '<?php echo wp_create_nonce('flexmile_price_matrix'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#flexmile_price_matrix').html(response.data.html);
                    } else {
                        alert('Błąd: ' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).html('🔄 Wygeneruj tabelę cen');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Renderuje macierz cen
     */
    private function render_price_matrix($config) {
        if (empty($config['rental_periods']) || empty($config['mileage_limits'])) {
            echo '<p style="text-align: center; color: #64748b; padding: 20px;">Uzupełnij okresy i limity, a następnie kliknij "Wygeneruj tabelę cen"</p>';
            return;
        }

        ?>
        <div style="overflow-x: auto;">
            <table class="widefat" style="border-collapse: collapse; width: 100%; background: white;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <th style="padding: 12px; text-align: left; color: white; font-weight: 600; border: 1px solid rgba(255,255,255,0.2);">
                            Okres / Limit km
                        </th>
                        <?php foreach ($config['mileage_limits'] as $limit): ?>
                        <th style="padding: 12px; text-align: center; color: white; font-weight: 600; border: 1px solid rgba(255,255,255,0.2);">
                            <?php echo number_format($limit, 0, '', ' '); ?> km/rok
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $min_price = PHP_FLOAT_MAX;
                    $min_key = '';

                    foreach ($config['rental_periods'] as $okres):
                    ?>
                    <tr>
                        <td style="padding: 12px; font-weight: 600; background: #f8fafc; border: 1px solid #e2e8f0;">
                            <?php echo $okres; ?> miesięcy
                        </td>
                        <?php foreach ($config['mileage_limits'] as $limit):
                            $key = $okres . '_' . $limit;
                            $cena = isset($config['prices'][$key]) ? $config['prices'][$key] : '';

                            if (!empty($cena) && $cena < $min_price) {
                                $min_price = $cena;
                                $min_key = $key;
                            }
                        ?>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; <?php echo ($key === $min_key && !empty($cena)) ? 'background: #d1fae5;' : ''; ?>">
                            <input type="number"
                                   name="price_matrix[<?php echo esc_attr($key); ?>]"
                                   value="<?php echo esc_attr($cena); ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   style="width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 4px; text-align: right; <?php echo ($key === $min_key && !empty($cena)) ? 'border-color: #10b981; font-weight: 600;' : ''; ?>">
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($min_price < PHP_FLOAT_MAX): ?>
            <p style="margin-top: 10px; padding: 10px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px; font-size: 13px;">
                💚 <strong>Najniższa cena:</strong> <?php echo number_format($min_price, 2, ',', ' '); ?> zł/mies.
                (podświetlona na zielono)
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Generuje macierz cen
     */
    public function ajax_generate_price_matrix() {
        check_ajax_referer('flexmile_price_matrix', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Brak uprawnień']);
        }

        $post_id = intval($_POST['post_id']);
        $okresy_str = sanitize_text_field($_POST['okresy']);
        $limity_str = sanitize_text_field($_POST['limity']);

        $okresy = array_map('intval', array_filter(explode(',', $okresy_str)));
        $limity_km = array_map('intval', array_filter(explode(',', $limity_str)));

        if (empty($okresy) || empty($limity_km)) {
            wp_send_json_error(['message' => 'Nieprawidłowe dane']);
        }

        $old_config = get_post_meta($post_id, '_pricing_config', true);
        $old_ceny = is_array($old_config) && isset($old_config['prices']) ? $old_config['prices'] : [];

        $config = [
            'rental_periods' => $okresy,
            'mileage_limits' => $limity_km,
            'prices' => $old_ceny
        ];

        ob_start();
        $this->render_price_matrix($config);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Renderuje meta box z flagami
     */
    public function render_flags_meta_box($post) {
        $nowy = get_post_meta($post->ID, '_new_car', true);
        $od_reki = get_post_meta($post->ID, '_available_immediately', true);
        $wkrotce = get_post_meta($post->ID, '_coming_soon', true);
        $wkrotce_data = get_post_meta($post->ID, '_coming_soon_date', true);
        $najczesciej = get_post_meta($post->ID, '_most_popular', true);
        $coming_soon_toggle_id = 'coming-soon-flag-' . absint($post->ID);
        $coming_soon_wrapper_id = 'coming-soon-wrapper-' . absint($post->ID);
        $coming_soon_input_id = 'coming-soon-date-' . absint($post->ID);
        ?>
        <div style="padding: 5px;">
            <div style="margin-bottom: 20px;">
                <p style="margin-bottom: 12px; font-weight: 600; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    🏷️ Statusy samochodu
                </p>

                <p style="margin: 0 0 10px 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="new_car" value="1" <?php checked($nowy, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #10b981;">
                        <span style="font-size: 14px;">🆕 Nowy samochód</span>
                    </label>
                </p>

                <p style="margin: 0 0 10px 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="available_immediately" value="1" <?php checked($od_reki, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #10b981;">
                        <span style="font-size: 14px;">⚡ Dostępny od ręki</span>
                    </label>
                </p>

                <p style="margin: 0 0 10px 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'"
                           for="<?php echo esc_attr($coming_soon_toggle_id); ?>">
                        <input type="checkbox" id="<?php echo esc_attr($coming_soon_toggle_id); ?>" name="coming_soon" value="1" <?php checked($wkrotce, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                        <span style="font-size: 14px;">⏳ Dostępny wkrótce</span>
                    </label>
                </p>
                <div id="<?php echo esc_attr($coming_soon_wrapper_id); ?>"
                     style="margin-left: 34px; margin-top: -6px; margin-bottom: 12px; <?php echo $wkrotce === '1' ? '' : 'display: none;'; ?>">
                    <label for="<?php echo esc_attr($coming_soon_input_id); ?>"
                           style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
                        Planowana dostępność (miesiąc i rok)
                    </label>
                    <input type="text"
                           id="<?php echo esc_attr($coming_soon_input_id); ?>"
                           name="coming_soon_date"
                           value="<?php echo esc_attr($wkrotce_data); ?>"
                           placeholder="Np. Listopad 2025"
                           autocomplete="off"
                           style="width: 100%; max-width: 240px; border: 1px solid #cbd5f5; border-radius: 6px; padding: 6px 10px; font-size: 13px; color: #0f172a;">
                    <span class="description" style="display: block; font-size: 12px; color: #64748b; margin-top: 4px;">
                        Możesz wpisać miesiąc i rok w formacie „Listopad 2025”.
                    </span>
                </div>

                <p style="margin: 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="most_popular" value="1" <?php checked($najczesciej, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                        <span style="font-size: 14px;">⭐ Najczęściej wybierany</span>
                    </label>
                </p>
            </div>

        </div>
        <script>
            (function() {
                const toggle = document.getElementById('<?php echo esc_js($coming_soon_toggle_id); ?>');
                const wrapper = document.getElementById('<?php echo esc_js($coming_soon_wrapper_id); ?>');
                if (!toggle || !wrapper) {
                    return;
                }

                const refreshVisibility = () => {
                    wrapper.style.display = toggle.checked ? 'block' : 'none';
                };

                toggle.addEventListener('change', refreshVisibility);
                refreshVisibility();
            })();
        </script>
        <?php
    }

    /**
     * Zapisuje meta dane
     * UPDATED: body_type i fuel_type są teraz zapisywane jako meta pola
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['flexmile_samochod_nonce']) ||
            !wp_verify_nonce($_POST['flexmile_samochod_nonce'], 'flexmile_samochod_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['gallery'])) {
            update_post_meta($post_id, '_gallery', sanitize_text_field($_POST['gallery']));
        }

        // Zapisz markę i model
        if (isset($_POST['car_brand_slug'])) {
            update_post_meta($post_id, '_car_brand_slug', sanitize_text_field($_POST['car_brand_slug']));
        }

        if (isset($_POST['car_model'])) {
            update_post_meta($post_id, '_car_model', sanitize_text_field($_POST['car_model']));
        }

        // Zapisz body_type i fuel_type jako meta pola (NOWOŚĆ!)
        if (isset($_POST['body_type'])) {
            update_post_meta($post_id, '_body_type', sanitize_text_field($_POST['body_type']));
        }

        if (isset($_POST['fuel_type'])) {
            update_post_meta($post_id, '_fuel_type', sanitize_text_field($_POST['fuel_type']));
        }

        $fields = [
            '_year' => 'intval',
            '_horsepower' => 'intval',
            '_engine_capacity' => 'intval',
            '_transmission' => 'sanitize_text_field',
            '_drivetrain' => 'sanitize_text_field',
            '_engine' => 'sanitize_text_field',
            '_color' => 'sanitize_text_field',
            '_seats' => 'intval',
            '_doors' => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitize) {
            $key = ltrim($field, '_');
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $field, $sanitize($_POST[$key]));
            }
        }

        if (isset($_POST['rental_periods']) && isset($_POST['mileage_limits'])) {
            $okresy = array_map('intval', array_filter(explode(',', $_POST['rental_periods'])));
            $limity_km = array_map('intval', array_filter(explode(',', $_POST['mileage_limits'])));

            $ceny = [];
            if (isset($_POST['price_matrix']) && is_array($_POST['price_matrix'])) {
                foreach ($_POST['price_matrix'] as $key => $value) {
                    if (!empty($value)) {
                        $ceny[sanitize_text_field($key)] = floatval($value);
                    }
                }
            }

            $config = [
                'rental_periods' => $okresy,
                'mileage_limits' => $limity_km,
                'prices' => $ceny
            ];

            update_post_meta($post_id, '_pricing_config', $config);

            $min_price = !empty($ceny) ? min($ceny) : 0;
            update_post_meta($post_id, '_lowest_price', $min_price);
        }

        $rezerwacja = isset($_POST['reservation_active']) ? '1' : '0';
        update_post_meta($post_id, '_reservation_active', $rezerwacja);

        $flags = [
            '_new_car' => 'new_car',
            '_available_immediately' => 'available_immediately',
            '_coming_soon' => 'coming_soon',
            '_most_popular' => 'most_popular',
        ];

        foreach ($flags as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? '1' : '0';
            update_post_meta($post_id, $meta_key, $value);
        }

        $coming_soon_flag = isset($_POST['coming_soon']) ? '1' : '0';
        $coming_soon_date = isset($_POST['coming_soon_date']) ? sanitize_text_field($_POST['coming_soon_date']) : '';
        $coming_soon_date = trim($coming_soon_date);

        if ($coming_soon_flag === '1' && !empty($coming_soon_date)) {
            update_post_meta($post_id, '_coming_soon_date', $coming_soon_date);
        } else {
            delete_post_meta($post_id, '_coming_soon_date');
        }

        if (isset($_POST['standard_equipment'])) {
            update_post_meta($post_id, '_standard_equipment', sanitize_textarea_field($_POST['standard_equipment']));
        }

        if (isset($_POST['additional_equipment'])) {
            update_post_meta($post_id, '_additional_equipment', sanitize_textarea_field($_POST['additional_equipment']));
        }
    }
}
