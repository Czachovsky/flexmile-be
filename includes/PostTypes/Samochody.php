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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
            'rest_base' => 'samochody',
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
     * Ładuje skrypty admina
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        if (('post.php' === $hook || 'post-new.php' === $hook) && self::POST_TYPE === $post_type) {
            wp_enqueue_media();

            // Galeria
            wp_enqueue_script(
                'flexmile-gallery',
                plugins_url('../../assets/admin-gallery.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );

            // Niestandardowe style
            wp_enqueue_style(
                'flexmile-admin-styles',
                plugins_url('../../assets/admin-styles.css', __FILE__),
                [],
                '1.0'
            );

            // jQuery UI Tabs (wbudowane w WordPress)
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('wp-jquery-ui-dialog');

            // Niestandardowy JS dla zakładek
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
            'Kalkulator ceny wynajmu',
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
     * Renderuje meta box z galerią
     */
    public function render_gallery_meta_box($post) {
        wp_nonce_field('flexmile_samochod_meta', 'flexmile_samochod_nonce');

        $gallery_ids = get_post_meta($post->ID, '_galeria', true);
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

            <input type="hidden" id="flexmile_gallery_ids" name="galeria" value="<?php echo esc_attr($gallery_ids); ?>">

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

            // Dodawanie zdjęć
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

            // Usuwanie zdjęcia
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
     */
    public function render_details_meta_box($post) {
        $rocznik = get_post_meta($post->ID, '_rocznik', true);
        $przebieg = get_post_meta($post->ID, '_przebieg', true);
        $moc = get_post_meta($post->ID, '_moc', true);
        $pojemnosc = get_post_meta($post->ID, '_pojemnosc', true);
        $skrzynia = get_post_meta($post->ID, '_skrzynia', true);
        $kolor = get_post_meta($post->ID, '_kolor', true);
        $liczba_miejsc = get_post_meta($post->ID, '_liczba_miejsc', true);
        $liczba_drzwi = get_post_meta($post->ID, '_liczba_drzwi', true);
        $naped = get_post_meta($post->ID, '_naped', true);
        $silnik = get_post_meta($post->ID, '_silnik', true);
        $numer_vin = get_post_meta($post->ID, '_numer_vin', true);
        ?>
        <div class="flexmile-tabs">
            <ul class="flexmile-tab-nav">
                <li><a href="#tab-podstawowe">📋 Podstawowe</a></li>
                <li><a href="#tab-silnik">🔧 Silnik i napęd</a></li>
                <li><a href="#tab-wyglad">🎨 Wygląd i wnętrze</a></li>
            </ul>

            <!-- Zakładka: Podstawowe -->
            <div id="tab-podstawowe" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field">
                        <label for="rocznik">
                            <span class="flexmile-label-icon">📅</span>
                            <strong>Rocznik</strong>
                        </label>
                        <input type="number"
                               id="rocznik"
                               name="rocznik"
                               value="<?php echo esc_attr($rocznik); ?>"
                               class="flexmile-input"
                               min="1900"
                               max="<?php echo date('Y') + 1; ?>"
                               placeholder="np. 2022">
                    </div>

                    <div class="flexmile-field">
                        <label for="przebieg">
                            <span class="flexmile-label-icon">🛣️</span>
                            <strong>Przebieg (km)</strong>
                        </label>
                        <input type="number"
                               id="przebieg"
                               name="przebieg"
                               value="<?php echo esc_attr($przebieg); ?>"
                               class="flexmile-input"
                               min="0"
                               step="1000"
                               placeholder="np. 45000">
                    </div>

                    <div class="flexmile-field">
                        <label for="liczba_miejsc">
                            <span class="flexmile-label-icon">👥</span>
                            <strong>Liczba miejsc</strong>
                        </label>
                        <select id="liczba_miejsc" name="liczba_miejsc" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <?php for($i = 2; $i <= 9; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($liczba_miejsc, $i); ?>>
                                    <?php echo $i; ?> miejsc
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="liczba_drzwi">
                            <span class="flexmile-label-icon">🚪</span>
                            <strong>Liczba drzwi</strong>
                        </label>
                        <select id="liczba_drzwi" name="liczba_drzwi" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="2" <?php selected($liczba_drzwi, '2'); ?>>2/3 drzwi</option>
                            <option value="4" <?php selected($liczba_drzwi, '4'); ?>>4/5 drzwi</option>
                        </select>
                    </div>

                    <div class="flexmile-field flexmile-field-full">
                        <label for="numer_vin">
                            <span class="flexmile-label-icon">🔢</span>
                            <strong>Numer VIN</strong>
                        </label>
                        <input type="text"
                               id="numer_vin"
                               name="numer_vin"
                               value="<?php echo esc_attr($numer_vin); ?>"
                               class="flexmile-input"
                               maxlength="17"
                               placeholder="np. WBAKR810501A23456">
                        <p class="description">17-znakowy numer identyfikacyjny pojazdu</p>
                    </div>
                </div>
            </div>

            <!-- Zakładka: Silnik i napęd -->
            <div id="tab-silnik" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-full">
                        <label for="silnik">
                            <span class="flexmile-label-icon">⚙️</span>
                            <strong>Oznaczenie silnika</strong>
                        </label>
                        <input type="text"
                               id="silnik"
                               name="silnik"
                               value="<?php echo esc_attr($silnik); ?>"
                               class="flexmile-input"
                               placeholder="np. 2.0 TDI, 1.5 TSI, 3.0d xDrive">
                        <p class="description">Pełna nazwa/model silnika</p>
                    </div>

                    <div class="flexmile-field">
                        <label for="moc">
                            <span class="flexmile-label-icon">💪</span>
                            <strong>Moc (KM)</strong>
                        </label>
                        <input type="number"
                               id="moc"
                               name="moc"
                               value="<?php echo esc_attr($moc); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 150">
                    </div>

                    <div class="flexmile-field">
                        <label for="pojemnosc">
                            <span class="flexmile-label-icon">🔋</span>
                            <strong>Pojemność (cm³)</strong>
                        </label>
                        <input type="number"
                               id="pojemnosc"
                               name="pojemnosc"
                               value="<?php echo esc_attr($pojemnosc); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 1984">
                    </div>

                    <div class="flexmile-field">
                        <label for="skrzynia">
                            <span class="flexmile-label-icon">⚡</span>
                            <strong>Skrzynia biegów</strong>
                        </label>
                        <select id="skrzynia" name="skrzynia" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="manual" <?php selected($skrzynia, 'manual'); ?>>Manualna</option>
                            <option value="automatic" <?php selected($skrzynia, 'automatic'); ?>>Automatyczna</option>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="naped">
                            <span class="flexmile-label-icon">🔄</span>
                            <strong>Napęd</strong>
                        </label>
                        <select id="naped" name="naped" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="FWD" <?php selected($naped, 'FWD'); ?>>FWD (przedni)</option>
                            <option value="RWD" <?php selected($naped, 'RWD'); ?>>RWD (tylny)</option>
                            <option value="AWD" <?php selected($naped, 'AWD'); ?>>AWD (4x4)</option>
                            <option value="4WD" <?php selected($naped, '4WD'); ?>>4WD (4x4 dołączany)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Zakładka: Wygląd -->
            <div id="tab-wyglad" class="flexmile-tab-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-full">
                        <label for="kolor">
                            <span class="flexmile-label-icon">🎨</span>
                            <strong>Kolor lakieru</strong>
                        </label>
                        <input type="text"
                               id="kolor"
                               name="kolor"
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
        $wyposazenie = get_post_meta($post->ID, '_wyposazenie_standardowe', true);
        ?>
        <div class="flexmile-wyposazenie">
            <div class="wyposazenie-wlasne">
                <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">
                    📝 Wpisz wyposażenie standardowe - każda pozycja w nowej linii
                </p>
                <textarea name="wyposazenie_standardowe"
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
        $wyposazenie = get_post_meta($post->ID, '_wyposazenie_dodatkowe', true);
        ?>
        <div class="flexmile-wyposazenie">
            <div class="wyposazenie-wlasne">
                <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">
                    📝 Wpisz wyposażenie dodatkowe - każda pozycja w nowej linii
                </p>
                <textarea name="wyposazenie_dodatkowe"
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
     * Renderuje meta box z kalkulatorem cen
     */
    public function render_pricing_meta_box($post) {
        $cena_bazowa = get_post_meta($post->ID, '_cena_bazowa', true);
        $cena_za_km = get_post_meta($post->ID, '_cena_za_km', true);
        $rezerwacja_aktywna = get_post_meta($post->ID, '_rezerwacja_aktywna', true);
        ?>
        <div style="padding: 5px;">
            <p>
                <label for="cena_bazowa">
                    <span style="font-size: 16px;">💰</span>
                    <strong>Cena bazowa / miesiąc</strong>
                </label><br>
                <input type="number"
                       id="cena_bazowa"
                       name="cena_bazowa"
                       value="<?php echo esc_attr($cena_bazowa); ?>"
                       class="widefat"
                       style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; margin-top: 5px;"
                       step="0.01"
                       min="0"
                       placeholder="np. 1500.00"> <span style="color: #64748b;">zł</span>
            </p>

            <p>
                <label for="cena_za_km">
                    <span style="font-size: 16px;">🛣️</span>
                    <strong>Dopłata za km</strong>
                </label><br>
                <input type="number"
                       id="cena_za_km"
                       name="cena_za_km"
                       value="<?php echo esc_attr($cena_za_km); ?>"
                       class="widefat"
                       style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; margin-top: 5px;"
                       step="0.01"
                       min="0"
                       placeholder="np. 0.50"> <span style="color: #64748b;">zł</span>
                <p class="description" style="margin-top: 5px;">Powyżej limitu 1000 km/miesiąc</p>
            </p>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <p style="background: #fef3c7; padding: 12px; border-radius: 6px; border-left: 4px solid #f59e0b;">
                <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                    <input type="checkbox"
                           name="rezerwacja_aktywna"
                           value="1"
                           <?php checked($rezerwacja_aktywna, '1'); ?>
                           style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                    <span><strong>🔒 Samochód zarezerwowany</strong></span>
                </label>
            </p>
            <p class="description" style="margin-top: 8px;">
                Zaznacz jeśli samochód jest aktualnie zarezerwowany i nie powinien być wyświetlany w ofercie.
            </p>
        </div>
        <?php
    }

    /**
     * Renderuje meta box z flagami statusu i wyróżnienia
     */
    public function render_flags_meta_box($post) {
        $nowy = get_post_meta($post->ID, '_nowy_samochod', true);
        $od_reki = get_post_meta($post->ID, '_dostepny_od_reki', true);
        $wkrotce = get_post_meta($post->ID, '_dostepny_wkrotce', true);
        $najczesciej = get_post_meta($post->ID, '_najczesciej_wybierany', true);
        $wyrozniany = get_post_meta($post->ID, '_wyrozniany', true);
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
                        <input type="checkbox" name="nowy_samochod" value="1" <?php checked($nowy, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #10b981;">
                        <span style="font-size: 14px;">🆕 Nowy samochód</span>
                    </label>
                </p>

                <p style="margin: 0 0 10px 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="dostepny_od_reki" value="1" <?php checked($od_reki, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #10b981;">
                        <span style="font-size: 14px;">⚡ Dostępny od ręki</span>
                    </label>
                </p>

                <p style="margin: 0 0 10px 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="dostepny_wkrotce" value="1" <?php checked($wkrotce, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                        <span style="font-size: 14px;">⏳ Dostępny wkrótce</span>
                    </label>
                </p>

                <p style="margin: 0;">
                    <label style="display: flex; align-items: center; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;"
                           onmouseover="this.style.background='#f8fafc'"
                           onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="najczesciej_wybierany" value="1" <?php checked($najczesciej, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                        <span style="font-size: 14px;">⭐ Najczęściej wybierany</span>
                    </label>
                </p>
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div>
                <p style="margin-bottom: 12px; font-weight: 600; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    ⭐ Wyróżnienie
                </p>

                <p style="margin: 0;">
                    <label style="display: flex; align-items: center; padding: 12px; border-radius: 8px; cursor: pointer; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b;">
                        <input type="checkbox" name="wyrozniany" value="1" <?php checked($wyrozniany, '1'); ?>
                               style="margin-right: 10px; width: 18px; height: 18px; accent-color: #f59e0b;">
                        <span style="font-size: 14px; font-weight: 600; color: #92400e;">🌟 Wyróżniony samochód</span>
                    </label>
                </p>

                <p class="description" style="margin-top: 10px; font-size: 12px; color: #64748b;">
                    💡 Wyróżnione samochody są wyświetlane na górze listy i w specjalnej sekcji.
                </p>
            </div>
        </div>
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

        // Zapisz galerię
        if (isset($_POST['galeria'])) {
            update_post_meta($post_id, '_galeria', sanitize_text_field($_POST['galeria']));
        }

        // Zapisz wszystkie pola
        $fields = [
            '_rocznik' => 'intval',
            '_przebieg' => 'intval',
            '_moc' => 'intval',
            '_pojemnosc' => 'intval',
            '_skrzynia' => 'sanitize_text_field',
            '_naped' => 'sanitize_text_field',
            '_silnik' => 'sanitize_text_field',
            '_kolor' => 'sanitize_text_field',
            '_liczba_miejsc' => 'intval',
            '_liczba_drzwi' => 'sanitize_text_field',
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

        // Flagi statusu
        $flags = [
            '_nowy_samochod' => 'nowy_samochod',
            '_dostepny_od_reki' => 'dostepny_od_reki',
            '_dostepny_wkrotce' => 'dostepny_wkrotce',
            '_najczesciej_wybierany' => 'najczesciej_wybierany',
            '_wyrozniany' => 'wyrozniany',
        ];

        foreach ($flags as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? '1' : '0';
            update_post_meta($post_id, $meta_key, $value);
        }

        // Wyposażenie standardowe - zapisz jako textarea (każda linia to element)
        if (isset($_POST['wyposazenie_standardowe'])) {
            update_post_meta($post_id, '_wyposazenie_standardowe', sanitize_textarea_field($_POST['wyposazenie_standardowe']));
        }

        // Wyposażenie dodatkowe - zapisz jako textarea (każda linia to element)
        if (isset($_POST['wyposazenie_dodatkowe'])) {
            update_post_meta($post_id, '_wyposazenie_dodatkowe', sanitize_textarea_field($_POST['wyposazenie_dodatkowe']));
        }
    }
}
