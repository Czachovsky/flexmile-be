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
            wp_enqueue_script(
                'flexmile-gallery',
                plugins_url('../../assets/admin-gallery.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );
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

        <style>
            .flexmile-gallery-images {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
                margin-bottom: 15px;
                min-height: 50px;
            }
            .gallery-item {
                position: relative;
                border: 2px solid #ddd;
                border-radius: 4px;
                padding: 5px;
                background: #f9f9f9;
                cursor: move;
            }
            .gallery-item img {
                width: 100%;
                height: auto;
                display: block;
            }
            .gallery-item .remove-gallery-image {
                position: absolute;
                top: 5px;
                right: 5px;
                background: #dc3232;
                color: white;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
            }
            .gallery-item .remove-gallery-image:hover {
                background: #a00;
            }
        </style>

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
                <th><label for="silnik">Silnik</label></th>
                <td>
                    <input type="text" id="silnik" name="silnik" value="<?php echo esc_attr($silnik); ?>" class="regular-text" placeholder="np. 2.0 TDI, 1.5 TSI">
                    <p class="description">Nazwa/model silnika (np. 2.0 TDI, 3.0d, 1.8 Hybrid)</p>
                </td>
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
                        <option value="manual" <?php selected($skrzynia, 'manual'); ?>>Manualna</option>
                        <option value="automatic" <?php selected($skrzynia, 'automatic'); ?>>Automatyczna</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="naped">Napęd</label></th>
                <td>
                    <select id="naped" name="naped">
                        <option value="">-- Wybierz --</option>
                        <option value="FWD" <?php selected($naped, 'FWD'); ?>>FWD (przedni)</option>
                        <option value="RWD" <?php selected($naped, 'RWD'); ?>>RWD (tylny)</option>
                        <option value="AWD" <?php selected($naped, 'AWD'); ?>>AWD (4x4)</option>
                        <option value="4WD" <?php selected($naped, '4WD'); ?>>4WD (4x4 dołączany)</option>
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
                <th><label for="liczba_drzwi">Liczba drzwi</label></th>
                <td>
                    <select id="liczba_drzwi" name="liczba_drzwi">
                        <option value="">-- Wybierz --</option>
                        <option value="2" <?php selected($liczba_drzwi, '2'); ?>>2-drzwiowy</option>
                        <option value="3" <?php selected($liczba_drzwi, '3'); ?>>3-drzwiowy</option>
                        <option value="4" <?php selected($liczba_drzwi, '4'); ?>>4-drzwiowy</option>
                        <option value="5" <?php selected($liczba_drzwi, '5'); ?>>5-drzwiowy</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="numer_vin">Numer VIN</label></th>
                <td><input type="text" id="numer_vin" name="numer_vin" value="<?php echo esc_attr($numer_vin); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Lista wyposażenia standardowego
     */
    private function get_wyposazenie_standardowe_options() {
        return [
            'Bezpieczeństwo' => [
                'abs' => 'ABS',
                'esp' => 'ESP',
                'asr' => 'ASR/Kontrola trakcji',
                'poduszki' => 'Poduszki powietrzne',
                'isofix' => 'ISOFIX',
                'alarm' => 'Alarm',
                'immobiliser' => 'Immobiliser',
            ],
            'Komfort' => [
                'klimatyzacja' => 'Klimatyzacja',
                'klimatyzacja_auto' => 'Klimatyzacja automatyczna',
                'nawigacja' => 'Nawigacja GPS',
                'bluetooth' => 'Bluetooth',
                'tempomat' => 'Tempomat',
                'el_szyby' => 'Elektryczne szyby',
                'el_lusterka' => 'Elektryczne lusterka',
                'podgrzewane_siedzenia' => 'Podgrzewane siedzenia',
            ],
            'Multimedia' => [
                'radio' => 'Radio',
                'cd' => 'Odtwarzacz CD',
                'usb' => 'USB/AUX',
                'glosniki' => 'Zestaw głośników',
            ],
            'Oświetlenie' => [
                'led' => 'Światła LED',
                'xenon' => 'Xenon',
                'halogen' => 'Halogen',
                'swiatla_dzienne' => 'Światła dzienne LED',
            ],
        ];
    }

    /**
     * Lista wyposażenia dodatkowego
     */
    private function get_wyposazenie_dodatkowe_options() {
        return [
            'Premium' => [
                'skorzana_tapicerka' => 'Skórzana tapicerka',
                'dach_panoramiczny' => 'Dach panoramiczny',
                'zawieszenie_pneumatyczne' => 'Zawieszenie pneumatyczne',
                'fotele_sportowe' => 'Fotele sportowe',
                'fotele_wentylowane' => 'Fotele wentylowane',
                'masaz_foteli' => 'Masaż foteli',
            ],
            'Technologia' => [
                'kamera_360' => 'Kamera 360°',
                'kamera_cofania' => 'Kamera cofania',
                'czujniki_parkowania' => 'Czujniki parkowania',
                'asystent_pasa' => 'Asystent pasa ruchu',
                'tempomat_adaptacyjny' => 'Tempomat adaptacyjny',
                'head_up_display' => 'Head-up display',
                'keyless' => 'Keyless Go',
            ],
            'Audio' => [
                'system_audio_premium' => 'System audio premium',
                'subwoofer' => 'Subwoofer',
                'android_auto' => 'Android Auto',
                'apple_carplay' => 'Apple CarPlay',
            ],
            'Inne' => [
                'felgi_aluminiowe' => 'Felgi aluminiowe',
                'hak' => 'Hak holowniczy',
                'relingi' => 'Relingi dachowe',
            ],
        ];
    }

    /**
     * Renderuje meta box z wyposażeniem standardowym
     */
    public function render_wyposazenie_meta_box($post) {
        $wyposazenie = get_post_meta($post->ID, '_wyposazenie_standardowe', true);
        $wyposazenie = is_array($wyposazenie) ? $wyposazenie : [];
        $wlasne = get_post_meta($post->ID, '_wyposazenie_standardowe_wlasne', true);

        $options = $this->get_wyposazenie_standardowe_options();
        ?>
        <div class="flexmile-wyposazenie">
            <?php foreach ($options as $kategoria => $items): ?>
                <div class="wyposazenie-kategoria">
                    <h4><?php echo esc_html($kategoria); ?></h4>
                    <div class="wyposazenie-items">
                        <?php foreach ($items as $key => $label): ?>
                            <label class="wyposazenie-item">
                                <input type="checkbox"
                                       name="wyposazenie_standardowe[]"
                                       value="<?php echo esc_attr($key); ?>"
                                       <?php checked(in_array($key, $wyposazenie)); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="wyposazenie-wlasne" style="margin-top: 20px;">
                <h4>Dodatkowe pozycje (własne)</h4>
                <textarea name="wyposazenie_standardowe_wlasne"
                          rows="3"
                          class="widefat"
                          placeholder="Wpisz dodatkowe wyposażenie, oddzielone przecinkami"><?php echo esc_textarea($wlasne); ?></textarea>
                <p class="description">Każda pozycja w nowej linii lub oddzielona przecinkiem</p>
            </div>
        </div>

        <style>
            .wyposazenie-kategoria {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .wyposazenie-kategoria h4 {
                margin: 0 0 10px 0;
                color: #2271b1;
            }
            .wyposazenie-items {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 8px;
            }
            .wyposazenie-item {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                padding: 4px;
            }
            .wyposazenie-item:hover {
                background: #e8e8e8;
            }
            .wyposazenie-item input[type="checkbox"] {
                margin: 0;
            }
        </style>
        <?php
    }

    /**
     * Renderuje meta box z wyposażeniem dodatkowym
     */
    public function render_wyposazenie_dodatkowe_meta_box($post) {
        $wyposazenie = get_post_meta($post->ID, '_wyposazenie_dodatkowe', true);
        $wyposazenie = is_array($wyposazenie) ? $wyposazenie : [];
        $wlasne = get_post_meta($post->ID, '_wyposazenie_dodatkowe_wlasne', true);

        $options = $this->get_wyposazenie_dodatkowe_options();
        ?>
        <div class="flexmile-wyposazenie">
            <?php foreach ($options as $kategoria => $items): ?>
                <div class="wyposazenie-kategoria">
                    <h4><?php echo esc_html($kategoria); ?></h4>
                    <div class="wyposazenie-items">
                        <?php foreach ($items as $key => $label): ?>
                            <label class="wyposazenie-item">
                                <input type="checkbox"
                                       name="wyposazenie_dodatkowe[]"
                                       value="<?php echo esc_attr($key); ?>"
                                       <?php checked(in_array($key, $wyposazenie)); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="wyposazenie-wlasne" style="margin-top: 20px;">
                <h4>Dodatkowe pozycje (własne)</h4>
                <textarea name="wyposazenie_dodatkowe_wlasne"
                          rows="3"
                          class="widefat"
                          placeholder="Wpisz dodatkowe wyposażenie, oddzielone przecinkami"><?php echo esc_textarea($wlasne); ?></textarea>
                <p class="description">Każda pozycja w nowej linii lub oddzielona przecinkiem</p>
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
     * Renderuje meta box z flagami statusu i wyróżnienia
     */
    public function render_flags_meta_box($post) {
        $nowy = get_post_meta($post->ID, '_nowy_samochod', true);
        $od_reki = get_post_meta($post->ID, '_dostepny_od_reki', true);
        $wkrotce = get_post_meta($post->ID, '_dostepny_wkrotce', true);
        $najczesciej = get_post_meta($post->ID, '_najczesciej_wybierany', true);
        $wyrozniany = get_post_meta($post->ID, '_wyrozniany', true);
        ?>
        <div style="margin-bottom: 15px;">
            <p style="margin-bottom: 10px;"><strong>Statusy samochodu:</strong></p>

            <p style="margin: 8px 0;">
                <label>
                    <input type="checkbox" name="nowy_samochod" value="1" <?php checked($nowy, '1'); ?>>
                    🆕 Nowy samochód
                </label>
            </p>

            <p style="margin: 8px 0;">
                <label>
                    <input type="checkbox" name="dostepny_od_reki" value="1" <?php checked($od_reki, '1'); ?>>
                    ⚡ Dostępny od ręki
                </label>
            </p>

            <p style="margin: 8px 0;">
                <label>
                    <input type="checkbox" name="dostepny_wkrotce" value="1" <?php checked($wkrotce, '1'); ?>>
                    ⏳ Dostępny wkrótce
                </label>
            </p>

            <p style="margin: 8px 0;">
                <label>
                    <input type="checkbox" name="najczesciej_wybierany" value="1" <?php checked($najczesciej, '1'); ?>>
                    ⭐ Najczęściej wybierany
                </label>
            </p>
        </div>

        <hr>

        <div style="margin-top: 15px;">
            <p style="margin-bottom: 10px;"><strong>Wyróżnienie:</strong></p>
            <p style="margin: 8px 0;">
                <label>
                    <input type="checkbox" name="wyrozniany" value="1" <?php checked($wyrozniany, '1'); ?>>
                    ⭐ Wyróżniony samochód
                </label>
            </p>
            <p class="description">Wyróżnione samochody są wyświetlane na górze listy i w specjalnej sekcji.</p>
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

        // Wyposażenie standardowe
        $wyposazenie_std = isset($_POST['wyposazenie_standardowe']) && is_array($_POST['wyposazenie_standardowe'])
            ? array_map('sanitize_text_field', $_POST['wyposazenie_standardowe'])
            : [];
        update_post_meta($post_id, '_wyposazenie_standardowe', $wyposazenie_std);

        // Wyposażenie standardowe - własne
        if (isset($_POST['wyposazenie_standardowe_wlasne'])) {
            update_post_meta($post_id, '_wyposazenie_standardowe_wlasne', sanitize_textarea_field($_POST['wyposazenie_standardowe_wlasne']));
        }

        // Wyposażenie dodatkowe
        $wyposazenie_dod = isset($_POST['wyposazenie_dodatkowe']) && is_array($_POST['wyposazenie_dodatkowe'])
            ? array_map('sanitize_text_field', $_POST['wyposazenie_dodatkowe'])
            : [];
        update_post_meta($post_id, '_wyposazenie_dodatkowe', $wyposazenie_dod);

        // Wyposażenie dodatkowe - własne
        if (isset($_POST['wyposazenie_dodatkowe_wlasne'])) {
            update_post_meta($post_id, '_wyposazenie_dodatkowe_wlasne', sanitize_textarea_field($_POST['wyposazenie_dodatkowe_wlasne']));
        }
    }
}
