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
        add_action('post_submitbox_misc_actions', [$this, 'add_duplicate_button']);
        add_action('post_submitbox_misc_actions', [$this, 'add_view_offer_button']);
        add_action('admin_action_flexmile_duplicate_offer', [$this, 'handle_duplicate_offer']);

        // Wyłącz Gutenberga dla typu postu 'offer'
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
        add_filter('gutenberg_can_edit_post_type', [$this, 'disable_gutenberg'], 10, 2);

        // Ukryj niepotrzebne meta boxy
        add_action('admin_head', [$this, 'hide_unnecessary_meta_boxes']);

        // Filtry w liście samochodów w panelu
        add_action('restrict_manage_posts', [$this, 'add_admin_filters']);
        add_filter('parse_query', [$this, 'apply_admin_filters']);
        
        // Rozszerz wyszukiwanie o ca_reference_id
        add_filter('posts_join', [$this, 'extend_search_join'], 10, 2);
        add_filter('posts_where', [$this, 'extend_search_where'], 10, 2);

        // Ukryj zbędne akcje w liście (szybka edycja, podgląd)
        add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);

        add_action('wp_ajax_flexmile_generate_price_matrix', [$this, 'ajax_generate_price_matrix']);
        add_action('wp_ajax_flexmile_get_models', [$this, 'ajax_get_models']);

        // Generuj reference ID przy tworzeniu nowego posta
        add_action('transition_post_status', [$this, 'generate_reference_id'], 10, 3);
        
        // Czyść cache marek przy zmianach ofert
        add_action('save_post_' . self::POST_TYPE, [$this, 'clear_brands_cache_on_save'], 20);
        add_action('transition_post_status', [$this, 'clear_brands_cache_on_status_change'], 10, 3);
        add_action('before_delete_post', [$this, 'clear_brands_cache_on_delete']);

        // Dodaj kolumnę z reference ID w liście postów
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_reference_id_column']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        
        // Dodaj sortowanie po ID oferty
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_reference_id_sortable']);
        add_action('pre_get_posts', [$this, 'handle_reference_id_sorting']);
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
     * Dodaje przycisk "Powiel ofertę" w boksie publikacji
     */
    public function add_duplicate_button() {
        global $post, $typenow;

        if (!is_admin()) {
            return;
        }

        if ($typenow !== self::POST_TYPE || !$post instanceof \WP_Post) {
            return;
        }

        if (empty($post->ID) || $post->post_status === 'auto-draft') {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=flexmile_duplicate_offer&post=' . absint($post->ID)),
            'flexmile_duplicate_offer_' . absint($post->ID)
        );
        ?>
        <div class="misc-pub-section">
            <a href="<?php echo esc_url($url); ?>" class="button button-secondary">
                Powiel ofertę
            </a>
        </div>
        <?php
    }

    /**
     * Dodaje przycisk "Zobacz ofertę" w boksie publikacji
     */
    public function add_view_offer_button() {
        global $post, $typenow;

        if (!is_admin()) {
            return;
        }

        if ($typenow !== self::POST_TYPE || !$post instanceof \WP_Post) {
            return;
        }

        // Przycisk widoczny tylko gdy oferta ma ID (jest zapisana)
        if (empty($post->ID) || $post->post_status === 'auto-draft') {
            return;
        }

        $frontend_domain = get_option('flexmile_frontend_domain', '');
        
        // Jeśli domena nie jest ustawiona, nie pokazuj przycisku
        if (empty($frontend_domain)) {
            return;
        }

        // Generuj link do oferty: {domena}/oferta/{id}
        $offer_url = rtrim($frontend_domain, '/') . '/oferta/' . absint($post->ID);
        ?>
        <div class="misc-pub-section">
            <a href="<?php echo esc_url($offer_url); ?>" target="_blank" class="button button-secondary" style="margin-top: 8px;">
                <span class="dashicons dashicons-external" style="margin-top: 3px;"></span> Zobacz ofertę
            </a>
        </div>
        <?php
    }

    /**
     * Obsługuje duplikowanie oferty z nowym reference ID
     */
    public function handle_duplicate_offer() {
        if (!is_admin()) {
            wp_die(__('Nieprawidłowe żądanie.', 'flexmile'));
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('Brak uprawnień.', 'flexmile'));
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

        if (!$post_id) {
            wp_die(__('Nieprawidłowe ID oferty.', 'flexmile'));
        }

        check_admin_referer('flexmile_duplicate_offer_' . $post_id);

        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            wp_die(__('Nie znaleziono oferty.', 'flexmile'));
        }

        $new_post_data = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ];

        $new_post_id = wp_insert_post($new_post_data);

        if (is_wp_error($new_post_id) || !$new_post_id) {
            wp_die(__('Nie udało się powielić oferty.', 'flexmile'));
        }

        // Skopiuj wszystkie meta dane poza tymi, które muszą być zresetowane
        $meta = get_post_meta($post_id);
        if (!empty($meta) && is_array($meta)) {
            foreach ($meta as $key => $values) {
                // Nie kopiujemy ID referencyjnego ani pól statusu rezerwacji/zamówienia
                if (in_array($key, ['_car_reference_id', '_reservation_active', '_order_approved'], true)) {
                    continue;
                }

                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, maybe_unserialize($value));
                }
            }
        }

        // Upewnij się, że nowa oferta nie jest oznaczona jako zarezerwowana / zamówiona
        update_post_meta($new_post_id, '_reservation_active', '0');
        delete_post_meta($new_post_id, '_order_approved');

        // Przekieruj do edycji nowej oferty
        wp_safe_redirect(
            admin_url('post.php?action=edit&post=' . $new_post_id)
        );
        exit;
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
                $new_columns['car_reference_id'] = 'ID oferty';
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
     * Umożliwia sortowanie po ID oferty
     */
    public function make_reference_id_sortable($columns) {
        $columns['car_reference_id'] = 'car_reference_id';
        return $columns;
    }

    /**
     * Obsługuje sortowanie po ID oferty (meta pole _car_reference_id)
     */
    public function handle_reference_id_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'car_reference_id') {
            $query->set('meta_key', '_car_reference_id');
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Usuwa zbędne akcje (Szybka edycja, Podgląd) z listy samochodów
     */
    public function filter_row_actions($actions, $post) {
        if ($post->post_type !== self::POST_TYPE) {
            return $actions;
        }

        // quick edit
        if (isset($actions['inline hide-if-no-js'])) {
            unset($actions['inline hide-if-no-js']);
        }

        // view
        if (isset($actions['view'])) {
            unset($actions['view']);
        }

        return $actions;
    }

    /**
     * Dodaje dropdown z filtrami na liście samochodów w panelu
     */
    public function add_admin_filters() {
        global $typenow;

        if ($typenow !== self::POST_TYPE) {
            return;
        }

        $current_filter = isset($_GET['car_availability']) ? sanitize_text_field($_GET['car_availability']) : '';
        ?>
        <select name="car_availability" id="car_availability" class="postform">
            <option value=""><?php esc_html_e('— Dostępność —', 'flexmile'); ?></option>
            <option value="available" <?php selected($current_filter, 'available'); ?>>Dostępne</option>
            <option value="reserved" <?php selected($current_filter, 'reserved'); ?>>Zarezerwowane</option>
            <option value="ordered" <?php selected($current_filter, 'ordered'); ?>>Zamówione</option>
            <option value="coming_soon" <?php selected($current_filter, 'coming_soon'); ?>>Dostępne wkrótce</option>
        </select>
        <?php
    }

    /**
     * Nakłada filtry z dropdownu na zapytanie w liście samochodów
     */
    public function apply_admin_filters($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        if (!$query->is_main_query()) {
            return;
        }

        $availability = isset($_GET['car_availability']) ? sanitize_text_field($_GET['car_availability']) : '';
        if (empty($availability)) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = $this->get_meta_query_for_availability($availability);
        $query->set('meta_query', $meta_query);
    }

    /**
     * Rozszerza wyszukiwanie o meta pole _car_reference_id
     */
    public function extend_search_join($join, $query) {
        global $wpdb;

        if (!is_admin() || !$query->is_main_query()) {
            return $join;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        if ($post_type !== self::POST_TYPE) {
            return $join;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $join;
        }

        // Dołącz tabelę postmeta dla _car_reference_id
        $join .= " LEFT JOIN {$wpdb->postmeta} AS pm_ref_id ON ({$wpdb->posts}.ID = pm_ref_id.post_id AND pm_ref_id.meta_key = '_car_reference_id')";

        return $join;
    }

    /**
     * Rozszerza warunek WHERE o wyszukiwanie w _car_reference_id
     */
    public function extend_search_where($where, $query) {
        global $wpdb;

        if (!is_admin() || !$query->is_main_query()) {
            return $where;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
        if ($post_type !== self::POST_TYPE) {
            return $where;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $where;
        }

        // Dodaj warunek wyszukiwania w meta polu _car_reference_id
        $search_term_like = '%' . $wpdb->esc_like($search_term) . '%';
        $where .= $wpdb->prepare(
            " OR (pm_ref_id.meta_value LIKE %s)",
            $search_term_like
        );

        return $where;
    }

    /**
     * Zwraca meta_query dla wybranego filtra dostępności
     */
    private function get_meta_query_for_availability($availability) {
        switch ($availability) {
            case 'available':
                // Dostępne = brak aktywnej rezerwacji i brak zatwierdzonego zamówienia
                return [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key' => '_reservation_active',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => '_reservation_active',
                            'value' => '1',
                            'compare' => '!=',
                        ],
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key' => '_order_approved',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => '_order_approved',
                            'value' => '1',
                            'compare' => '!=',
                        ],
                    ],
                ];

            case 'reserved':
                // Zarezerwowane = aktywna rezerwacja, brak zatwierdzonego zamówienia
                return [
                    'relation' => 'AND',
                    [
                        'key' => '_reservation_active',
                        'value' => '1',
                        'compare' => '=',
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key' => '_order_approved',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => '_order_approved',
                            'value' => '1',
                            'compare' => '!=',
                        ],
                    ],
                ];

            case 'ordered':
                // Zamówione = zatwierdzone zamówienie
                return [
                    [
                        'key' => '_order_approved',
                        'value' => '1',
                        'compare' => '=',
                    ],
                ];

            case 'coming_soon':
                // Dostępne wkrótce
                return [
                    [
                        'key' => '_coming_soon',
                        'value' => '1',
                        'compare' => '=',
                    ],
                ];

            default:
                return [];
        }
    }

    /**
     * Buduje znaczniki statusu na podstawie powiązanych rezerwacji i zamówień
     */
    private function build_status_badges($post_id) {
        $badges = [];

        $reserved_active = get_post_meta($post_id, '_reservation_active', true) === '1';
        if ($reserved_active) {
            $badges[] = '<span style="display:inline-flex;align-items:center;gap:6px;background:#fee2e2;color:#b91c1c;font-weight:600;padding:3px 10px;border-radius:999px;font-size:12px;">Zarezerwowany</span>';
        }

        $order_approved = get_post_meta($post_id, '_order_approved', true) === '1';
        if ($order_approved) {
            $badges[] = '<span style="display:inline-flex;align-items:center;gap:6px;background:#dbeafe;color:#1d4ed8;font-weight:600;padding:3px 10px;border-radius:999px;font-size:12px;">Zamówienie</span>';
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
            'name' => 'Oferty',
            'singular_name' => 'Oferta',
            'menu_name' => 'Oferty',
            'add_new' => 'Dodaj nową',
            'add_new_item' => 'Dodaj nową ofertę',
            'edit_item' => 'Edytuj ofertę',
            'new_item' => 'Nowy samochód',
            'view_item' => 'Zobacz ofertę',
            'search_items' => 'Szukaj ofert',
            'not_found' => 'Nie znaleziono ofert',
            'all_items' => 'Wszystkie oferty',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => false, // Wyłącz Gutenberga - używamy klasycznego edytora
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
     * Wyłącza Gutenberga (block editor) dla typu postu 'offer'
     */
    public function disable_gutenberg($use_block_editor, $post) {
        if (isset($post->post_type) && $post->post_type === self::POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Ukrywa niepotrzebne meta boxy (Custom Fields, Slug)
     */
    public function hide_unnecessary_meta_boxes() {
        global $post_type;
        
        if ($post_type === self::POST_TYPE) {
            // Ukryj meta box Custom Fields
            remove_meta_box('postcustom', self::POST_TYPE, 'normal');
            
            // Ukryj meta box Slug (w sidebarze)
            remove_meta_box('slugdiv', self::POST_TYPE, 'normal');
            
            // Ukryj również w innych kontekstach
            remove_meta_box('postcustom', self::POST_TYPE, 'side');
            remove_meta_box('slugdiv', self::POST_TYPE, 'side');
        }
    }

    /**
     * Ładuje skrypty admina
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        if (('post.php' === $hook || 'post-new.php' === $hook) && self::POST_TYPE === $post_type) {
            wp_enqueue_media();
            
            // Załaduj jQuery UI sortable przed skryptem galerii
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_script(
                'flexmile-gallery',
                plugins_url('../../assets/admin-gallery.js', __FILE__),
                ['jquery', 'jquery-ui-sortable'],
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

            global $post;
            $title_manually_edited = get_post_meta($post->ID ?? 0, '_title_manually_edited', true) === '1';
            
            wp_localize_script('flexmile-dropdown', 'flexmileDropdown', [
                'nonce' => wp_create_nonce('flexmile_dropdown'),
                'titleManuallyEdited' => $title_manually_edited ? '1' : '0'
            ]);

            wp_enqueue_script(
                'flexmile-form-validation',
                plugins_url('../../assets/admin-form-validation.js', __FILE__),
                ['jquery'],
                '1.0',
                true
            );

            // Przyklej box Publish tak, aby przycisk "Update" był zawsze widoczny
            wp_add_inline_script('flexmile-form-validation', '
                jQuery(document).ready(function($) {
                    var $box = $("#submitdiv");
                    if (!$box.length) {
                        return;
                    }

                    var originalStyle = $box.attr("style") || "";
                    var $placeholder = $("<div class=\"flexmile-submit-placeholder\"></div>").insertBefore($box).hide();

                    function getTopOffset() {
                        var adminBarHeight = $("#wpadminbar").length ? $("#wpadminbar").outerHeight() : 0;
                        return adminBarHeight + 12; // mały margines od paska admina
                    }

                    function updateSticky() {
                        var scrollTop = $(window).scrollTop();
                        var postBodyOffset = $("#poststuff").offset() ? $("#poststuff").offset().top : 0;
                        var footerTop = $("#wpfooter").offset() ? $("#wpfooter").offset().top : Number.MAX_VALUE;
                        var boxHeight = $box.outerHeight();
                        var topOffset = getTopOffset();
                        var containerOffset = $placeholder.is(":visible") ? $placeholder.offset() : $box.offset();
                        var containerLeft = containerOffset ? containerOffset.left : $box.offset().left;
                        var containerWidth = $placeholder.is(":visible") ? $placeholder.outerWidth() : $box.outerWidth();

                        // Aktywuj sticky gdy przewiniemy poniżej nagłówka formularza
                        if (scrollTop + topOffset > containerOffset.top && scrollTop + boxHeight + topOffset < footerTop) {
                            if (!$box.hasClass("flexmile-sticky-submit")) {
                                $placeholder.height(boxHeight).show();
                                $box
                                    .addClass("flexmile-sticky-submit")
                                    .css({
                                        position: "fixed",
                                        top: topOffset + "px",
                                        left: containerLeft + "px",
                                        width: containerWidth + "px",
                                        zIndex: 1000
                                    });
                            } else {
                                // Aktualizuj szerokość/lewą przy zmianie rozmiaru
                                $box.css({
                                    left: containerLeft + "px",
                                    width: containerWidth + "px"
                                });
                            }
                        } else {
                            if ($box.hasClass("flexmile-sticky-submit")) {
                                $box
                                    .removeClass("flexmile-sticky-submit")
                                    .attr("style", originalStyle);
                                $placeholder.hide();
                            }
                        }
                    }

                    $(window).on("scroll.flexmileSticky resize.flexmileSticky", updateSticky);
                    updateSticky();
                });
            ');

            // Dodaj ukryte pole do przechowywania flagi ręcznej edycji tytułu
            wp_add_inline_script('flexmile-dropdown', '
                jQuery(document).ready(function($) {
                    // Dodaj ukryte pole do formularza
                    var hiddenInput = $("<input type=\"hidden\" name=\"title_manually_edited\" id=\"title_manually_edited\" value=\"0\" />");
                    $("#post").append(hiddenInput);
                    
                    var titleInput = $("#title, input[name=\"post_title\"]").first();
                    if (titleInput.length) {
                        var hint = $("<p class=\"description\" style=\"margin-top: 6px; padding: 8px 12px; background: #f0f9ff; border-left: 3px solid #0ea5e9; border-radius: 4px; color: #0c4a6e; font-size: 12px;\"><span style=\"font-weight: 600;\">Podpowiedź:</span> Tytuł jest automatycznie generowany na podstawie wybranej marki i modelu. Możesz go zmienić ręcznie - zmiany będą zachowane.</p>");
                        titleInput.after(hint);
                        
                        // Sprawdź czy tytuł był już ręcznie edytowany (z meta)
                        if (typeof flexmileDropdown !== "undefined" && flexmileDropdown.titleManuallyEdited === "1") {
                            $("#title_manually_edited").val("1");
                        }
                        
                        // Zapamiętaj ostatni automatycznie wygenerowany tytuł
                        var lastAutoGeneratedTitle = titleInput.val().trim();
                        
                        // Wykryj ręczną zmianę tytułu - nasłuchuj na zmiany, ale z opóźnieniem,
                        // aby odróżnić ręczną edycję od automatycznej aktualizacji
                        var titleChangeTimeout;
                        var isAutoUpdating = false;
                        
                        // Event przed automatyczną aktualizacją
                        $(document).on("flexmile:beforeTitleUpdate", function() {
                            isAutoUpdating = true;
                            clearTimeout(titleChangeTimeout);
                        });
                        
                        // Event po automatycznej aktualizacji
                        $(document).on("flexmile:titleUpdated", function(e, newTitle) {
                            lastAutoGeneratedTitle = newTitle || titleInput.val().trim();
                            isAutoUpdating = false;
                            clearTimeout(titleChangeTimeout);
                        });
                        
                        titleInput.on("input change paste", function() {
                            clearTimeout(titleChangeTimeout);
                            
                            // Opóźnij sprawdzenie, aby dać czas na automatyczną aktualizację
                            titleChangeTimeout = setTimeout(function() {
                                if (!isAutoUpdating) {
                                    var currentTitle = titleInput.val().trim();
                                    // Jeśli tytuł różni się od automatycznie wygenerowanego, to użytkownik edytował ręcznie
                                    if (currentTitle && currentTitle !== lastAutoGeneratedTitle) {
                                        $("#title_manually_edited").val("1");
                                        console.log("FlexMile: Tytuł został ręcznie edytowany - automatyczne aktualizacje wyłączone");
                                    }
                                }
                            }, 300);
                        });
                        
                        // Aktualizuj lastAutoGeneratedTitle przy załadowaniu strony
                        lastAutoGeneratedTitle = titleInput.val().trim();
                    }
                });
            ');

            wp_enqueue_style(
                'flexmile-admin-styles',
                plugins_url('../../assets/admin-styles.css', __FILE__),
                [],
                '1.0'
            );

        }
    }

    /**
     * Dodaje meta boxy
     */
    public function add_meta_boxes($post_type) {
        // Upewnij się, że dodajemy meta boxy tylko dla typu 'offer'
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        // Wskaźnik postępu na górze
        add_action('edit_form_after_title', [$this, 'render_progress_indicator']);

        add_meta_box(
            'flexmile_car_reference',
            'Informacje o ofercie',
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
            'Konfiguracja cen',
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

        add_meta_box(
            'flexmile_samochod_additional_services',
            'Dodatkowe usługi i informacje',
            [$this, 'render_additional_services_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * Renderuje wskaźnik postępu na górze formularza
     */
    public function render_progress_indicator() {
        global $post;
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $sections = [
            'gallery' => ['label' => 'Galeria', 'id' => 'flexmile_samochod_gallery'],
            'details' => ['label' => 'Szczegóły', 'id' => 'flexmile_samochod_details'],
            'equipment' => ['label' => 'Wyposażenie', 'id' => 'flexmile_samochod_wyposazenie'],
            'pricing' => ['label' => 'Ceny', 'id' => 'flexmile_samochod_pricing'],
            'flags' => ['label' => 'Statusy', 'id' => 'flexmile_samochod_flags'],
        ];

        // Sprawdź które sekcje są wypełnione
        $gallery = get_post_meta($post->ID, '_gallery', true);
        $brand = get_post_meta($post->ID, '_car_brand_slug', true);
        $model = get_post_meta($post->ID, '_car_model', true);
        $pricing = get_post_meta($post->ID, '_pricing_config', true);
        $equipment = get_post_meta($post->ID, '_standard_equipment', true);

        $completed = [
            'gallery' => !empty($gallery),
            'details' => !empty($brand) && !empty($model),
            'equipment' => !empty($equipment),
            'pricing' => !empty($pricing) && !empty($pricing['prices']),
            'flags' => true, // Zawsze dostępne
        ];

        $total = count($sections);
        $completed_count = count(array_filter($completed));
        $percentage = $total > 0 ? round(($completed_count / $total) * 100) : 0;

        ?>
        <div id="flexmile-progress-indicator" style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b;">
                    Postęp wypełniania oferty
                </h3>
                <span class="progress-percentage" style="font-size: 18px; font-weight: 700; color: #712971;">
                    <?php echo $percentage; ?>%
                </span>
            </div>
            
            <div style="background: #e2e8f0; border-radius: 999px; height: 8px; overflow: hidden; margin-bottom: 20px;">
                <div class="progress-bar" style="background: linear-gradient(90deg, #712971 0%, #9333ea 100%); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s ease; border-radius: 999px;"></div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                <?php foreach ($sections as $key => $section): ?>
                <div class="flexmile-progress-section" 
                     data-section="<?php echo esc_attr($section['id']); ?>"
                     style="padding: 12px; background: <?php echo $completed[$key] ? '#ecfdf3' : '#ffffff'; ?>; border: 2px solid <?php echo $completed[$key] ? '#22c55e' : '#e2e8f0'; ?>; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                     onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1;">
                            <div style="font-size: 13px; font-weight: 600; color: #1e293b;">
                                <?php echo $section['label']; ?>
                            </div>
                            <div style="font-size: 11px; color: <?php echo $completed[$key] ? '#16a34a' : '#64748b'; ?>; margin-top: 2px;">
                                <?php echo $completed[$key] ? 'Uzupełnione' : 'Do wypełnienia'; ?>
                            </div>
                        </div>
                        <?php if ($completed[$key]): ?>
                        <span class="flexmile-section-checkmark" style="color: #22c55e; font-size: 18px; font-weight: bold;">✓</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.flexmile-progress-section').on('click', function() {
                var sectionId = $(this).data('section');
                var $section = $('#' + sectionId);
                
                if ($section.length) {
                    // Rozwiń sekcję jeśli jest zwinięta
                    if ($section.hasClass('closed')) {
                        $section.find('.handlediv').click();
                    }
                    
                    // Przewiń do sekcji
                    $('html, body').animate({
                        scrollTop: $section.offset().top - 100
                    }, 500);
                    
                    // Podświetl sekcję
                    $section.css({
                        'box-shadow': '0 0 0 3px rgba(113, 41, 113, 0.2)',
                        'border-color': '#712971'
                    });
                    
                    setTimeout(function() {
                        $section.css({
                            'box-shadow': '',
                            'border-color': ''
                        });
                    }, 2000);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Renderuje metabox z akcjami oferty (w tym przycisk "Powiel ofertę")
     */
    public function render_offer_actions_meta_box($post) {
        if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        if (empty($post->ID) || $post->post_status === 'auto-draft') {
            ?>
            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                Zapisz szkic, aby móc powielić ofertę.
            </p>
            <?php
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=flexmile_duplicate_offer&post=' . absint($post->ID)),
            'flexmile_duplicate_offer_' . absint($post->ID)
        );
        ?>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="<?php echo esc_url($url); ?>"
               class="button button-primary"
               style="width: 100%; text-align: center;">
                Powiel ofertę
            </a>
            <p class="description" style="margin: 0; font-size: 12px; color: #6b7280;">
                Utworzy nową ofertę jako szkic z tymi samymi danymi,
                ale z <strong>nowym ID oferty</strong>.
            </p>
        </div>
        <?php
    }

    /**
     * Renderuje meta box z Reference ID
     */
    public function render_reference_id_meta_box($post) {
        $ref_id = get_post_meta($post->ID, '_car_reference_id', true);
        $hero_slider_enabled = get_post_meta($post->ID, '_hero_slider_enabled', true) === '1';
        $hero_slider_image_id = get_post_meta($post->ID, '_hero_slider_image_id', true);
        $hero_slider_image_url = $hero_slider_image_id ? wp_get_attachment_image_url($hero_slider_image_id, 'medium') : '';

        ?>
        <div style="padding: 14px 16px; border-radius: 10px; border: 1px solid #e5e7eb; background: #ffffff; display: flex; flex-direction: column; gap: 14px;">
            <div>
                <div style="font-size: 11px; color: #6b7280; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.08em;">
                    ID oferty
                </div>
                <?php if (empty($ref_id)): ?>
                    <div style="padding: 10px 12px; border-radius: 8px; background: #f9fafb; border: 1px dashed #e5e7eb; text-align: left;">
                        <p style="margin: 0; color: #6b7280; font-size: 12px; line-height: 1.5;">
                            ID oferty zostanie wygenerowane automatycznie<br>po pierwszym opublikowaniu oferty.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: #f3e8ff;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #581c87; letter-spacing: 0.12em; font-weight: 600;">
                            REF
                        </span>
                        <span style="font-size: 16px; font-weight: 700; color: #111827; font-family: monospace; letter-spacing: 0.16em;">
                            <?php echo esc_html($ref_id); ?>
                        </span>
                    </div>

                <?php endif; ?>
            </div>

            <div style="margin-top: 4px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                <div style="font-size: 11px; color: #6b7280; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.08em;">
                    Akcje oferty
                </div>
                <?php
                if (
                    !$post instanceof \WP_Post ||
                    $post->post_type !== self::POST_TYPE ||
                    empty($post->ID) ||
                    $post->post_status === 'auto-draft'
                ) :
                    ?>
                    <p style="margin: 0; font-size: 12px; color: #6b7280;">
                        Zapisz szkic, aby skorzystać z dodatkowych akcji oferty.
                    </p>
                <?php
                else :
                    $url = wp_nonce_url(
                        admin_url('admin.php?action=flexmile_duplicate_offer&post=' . absint($post->ID)),
                        'flexmile_duplicate_offer_' . absint($post->ID)
                    );
                    ?>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <a href="<?php echo esc_url($url); ?>"
                           class="button button-primary"
                           style="width: 100%; text-align: center;">
                            Powiel ofertę
                        </a>
                        <p class="description" style="margin: 0; font-size: 11px; color: #6b7280; line-height: 1.5;">
                            Utworzy nową ofertę jako szkic z tymi samymi danymi,
                            ale z <strong>nowym ID oferty</strong>.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 8px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                <div style="font-size: 11px; color: #6b7280; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.08em;">
                    Slider na stronie głównej
                </div>

                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                    <input type="checkbox"
                           name="hero_slider_enabled"
                           value="1"
                           <?php checked($hero_slider_enabled); ?>
                           style="margin-top: 2px;">
                    <span style="font-size: 12px; color: #374151;">
                        <strong>Wyświetl ofertę w sliderze hero</strong><br>
                        <span style="color: #6b7280;">
                            Jeśli zaznaczone, oferta może pojawić się w sliderze na stronie głównej.
                        </span>
                    </span>
                </label>

                <div id="hero-slider-image-wrapper" style="margin-top: 10px; <?php echo $hero_slider_enabled ? '' : 'display: none;'; ?>">
                    <input type="hidden" id="hero_slider_image_id" name="hero_slider_image_id" value="<?php echo esc_attr($hero_slider_image_id); ?>">

                    <div id="hero-slider-image-preview" style="margin-bottom: 8px;">
                        <?php if ($hero_slider_image_id && $hero_slider_image_url): ?>
                            <img src="<?php echo esc_url($hero_slider_image_url); ?>" alt="" style="max-width: 100%; height: auto; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="button button-secondary" id="hero-slider-image-select">
                            Wybierz zdjęcie do slidera
                        </button>
                        <button type="button"
                                class="button button-link-delete"
                                id="hero-slider-image-remove"
                                style="<?php echo $hero_slider_image_id ? '' : 'display: none;'; ?>">
                            Usuń zdjęcie
                        </button>
                    </div>

                    <p class="description" style="margin-top: 6px; font-size: 11px; color: #6b7280;">
                        To zdjęcie będzie użyte w sliderze hero. Najlepiej dodaj poziome zdjęcie o wysokiej jakości.
                    </p>
                </div>

                <script>
                    (function($){
                        $(document).ready(function () {
                            var $checkbox = $('input[name="hero_slider_enabled"]');
                            var $wrapper = $('#hero-slider-image-wrapper');
                            var frame;

                            $checkbox.on('change', function () {
                                if ($(this).is(':checked')) {
                                    $wrapper.slideDown(150);
                                } else {
                                    $wrapper.slideUp(150);
                                }
                            });

                            $('#hero-slider-image-select').on('click', function (e) {
                                e.preventDefault();

                                if (frame) {
                                    frame.open();
                                    return;
                                }

                                frame = wp.media({
                                    title: 'Wybierz zdjęcie do slidera',
                                    button: {
                                        text: 'Użyj tego zdjęcia'
                                    },
                                    multiple: false
                                });

                                frame.on('select', function () {
                                    var attachment = frame.state().get('selection').first().toJSON();
                                    $('#hero_slider_image_id').val(attachment.id);
                                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                                    $('#hero-slider-image-preview').html(
                                        '<img src=\"' + url + '\" style=\"max-width:100%;height:auto;border-radius:6px;border:1px solid #e5e7eb;\" />'
                                    );
                                    $('#hero-slider-image-remove').show();
                                });

                                frame.open();
                            });

                            $('#hero-slider-image-remove').on('click', function (e) {
                                e.preventDefault();
                                $('#hero_slider_image_id').val('');
                                $('#hero-slider-image-preview').empty();
                                $(this).hide();
                            });
                        });
                    })(jQuery);
                </script>
            </div>
        </div>
        <?php
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
                Dodaj zdjęcia do galerii
            </button>

            <p class="description" style="margin-top: 10px;">
                Możesz dodać wiele zdjęć. Przeciągnij aby zmienić kolejność.
            </p>
        </div>
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
        $kolor_wnetrza = get_post_meta($post->ID, '_interior_color', true);
        $emisja_co2 = get_post_meta($post->ID, '_co2_emission', true);
        $stan = get_post_meta($post->ID, '_car_condition', true);
        $przebieg = get_post_meta($post->ID, '_mileage', true);

        // Pobierz typy nadwozia i paliwa z config.json
        $body_types = $config['body_types'] ?? [];
        $fuel_types = $config['fuel_types'] ?? [];
        ?>
        <div class="flexmile-sections">
            <div class="flexmile-section">
                <h3 class="flexmile-section-title">Podstawowe</h3>
                <div class="flexmile-section-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-required">
                        <label for="car_brand">
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

                    <div class="flexmile-field flexmile-field-required">
                        <label for="car_model">
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
         <div class="flexmile-field flexmile-field-full">
                        <label for="engine">

                            <strong>Wersja</strong>
                        </label>
                        <input type="text"
                               id="engine"
                               name="engine"
                               value="<?php echo esc_attr($silnik); ?>"
                               class="flexmile-input"
                               placeholder="np. 2.0 TDI, 1.5 TSI, 3.0d xDrive">
                        <p class="description">Wersja silnika / samochodu, np Wersja L 1.6 T-GDI 7DCT 2WD</p>
                    </div>
                    <div class="flexmile-field">
                        <label for="body_type">
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
                            <strong>Liczba drzwi</strong>
                        </label>
                        <select id="doors" name="doors" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="2" <?php selected($liczba_drzwi, '2'); ?>>2</option>
                            <option value="3" <?php selected($liczba_drzwi, '3'); ?>>3</option>
                            <option value="4" <?php selected($liczba_drzwi, '4'); ?>>4</option>
                            <option value="5" <?php selected($liczba_drzwi, '5'); ?>>5</option>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="car_condition">
                            <strong>Stan</strong>
                        </label>
                        <select id="car_condition" name="car_condition" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="new" <?php selected($stan, 'new'); ?>>Nowy</option>
                            <option value="demo" <?php selected($stan, 'demo'); ?>>Demo</option>
                            <option value="used" <?php selected($stan, 'used'); ?>>Używany</option>
                            <option value="ex_lease" <?php selected($stan, 'ex_lease'); ?>>Poleasingowy</option>
                            <option value="ex_contract" <?php selected($stan, 'ex_contract'); ?>>Pokontraktowy</option>
                        </select>
                    </div>

                    <div class="flexmile-field" id="mileage-field-wrapper" style="<?php echo in_array($stan, ['demo', 'used', 'ex_lease', 'ex_contract']) ? '' : 'display: none;'; ?>">
                        <label for="mileage">
                            <strong>Przebieg (km)</strong>
                        </label>
                        <input type="number"
                               id="mileage"
                               name="mileage"
                               value="<?php echo esc_attr($przebieg); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 15000">
                    </div>
                </div>
                </div>
            </div>

            <div class="flexmile-section">
                <h3 class="flexmile-section-title">Silnik i napęd</h3>
                <div class="flexmile-section-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field">
                        <label for="horsepower">
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
                            <strong>Napęd</strong>
                        </label>
                        <select id="drivetrain" name="drivetrain" class="flexmile-input">
                            <option value="">-- Wybierz --</option>
                            <option value="FWD" <?php selected($naped, 'FWD'); ?>>FWD (przedni)</option>
                            <option value="RWD" <?php selected($naped, 'RWD'); ?>>RWD (tylny)</option>
                            <option value="AWD" <?php selected($naped, 'AWD'); ?>>AWD (4x4)</option>
                            <option value="4WD" <?php selected($naped, '4WD'); ?>>4WD (4x4 dołączany)</option>
                            <option value="4Matic 4x4" <?php selected($naped, '4Matic 4x4'); ?>>4Matic 4x4</option>
                            <option value="Quattro - 4x4" <?php selected($naped, 'Quattro - 4x4'); ?>>Quattro - 4x4</option>
                            <option value="xDrive - 4x4" <?php selected($naped, 'xDrive - 4x4'); ?>>xDrive - 4x4</option>
                            <option value="SDrive - przód" <?php selected($naped, 'SDrive - przód'); ?>>SDrive - przód</option>
                        </select>
                    </div>

                    <div class="flexmile-field">
                        <label for="co2_emission">
                            <strong>Emisja CO2 (g/km)</strong>
                        </label>
                        <input type="number"
                               id="co2_emission"
                               name="co2_emission"
                               value="<?php echo esc_attr($emisja_co2); ?>"
                               class="flexmile-input"
                               min="0"
                               placeholder="np. 120">
                    </div>
                </div>
                </div>
            </div>

            <div class="flexmile-section">
                <h3 class="flexmile-section-title">Wygląd i wnętrze</h3>
                <div class="flexmile-section-content">
                <div class="flexmile-form-grid">
                    <div class="flexmile-field flexmile-field-full">
                        <label for="color">
                            <strong>Kolor lakieru</strong>
                        </label>
                        <input type="text"
                               id="color"
                               name="color"
                               value="<?php echo esc_attr($kolor); ?>"
                               class="flexmile-input"
                               placeholder="np. Czarny metalik, Srebrny perła">
                    </div>
                    <div class="flexmile-field flexmile-field-full">
                        <label for="interior_color">
                            <strong>Kolor wnętrza</strong>
                        </label>
                        <input type="text"
                               id="interior_color"
                               name="interior_color"
                               value="<?php echo esc_attr($kolor_wnetrza); ?>"
                               class="flexmile-input"
                               placeholder="np. Czarny, Szary, Beżowy">
                    </div>
                </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var $carCondition = $('#car_condition');
            var $mileageWrapper = $('#mileage-field-wrapper');
            
            function toggleMileageField() {
                var condition = $carCondition.val();
                if (condition === 'demo' || condition === 'used' || condition === 'ex_lease' || condition === 'ex_contract') {
                    $mileageWrapper.slideDown();
                } else {
                    $mileageWrapper.slideUp();
                }
            }
            
            $carCondition.on('change', toggleMileageField);
            toggleMileageField(); // Uruchom przy załadowaniu strony
        });
        </script>
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
                    Wpisz wyposażenie standardowe - każda pozycja w nowej linii
                </p>
                <textarea name="standard_equipment"
                          rows="10"
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: 'Courier New', monospace; line-height: 1.6;"
                          placeholder="ABS&#10;ESP&#10;Klimatyzacja automatyczna&#10;Nawigacja GPS&#10;Bluetooth&#10;Poduszki powietrzne&#10;Elektryczne szyby&#10;Światła LED"><?php echo esc_textarea($wyposazenie); ?></textarea>
                <p class="description" style="margin-top: 10px;">
                    Każda nowa linia to jeden element wyposażenia
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
                    Wpisz wyposażenie dodatkowe - każda pozycja w nowej linii
                </p>
                <textarea name="additional_equipment"
                          rows="10"
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: 'Courier New', monospace; line-height: 1.6;"
                          placeholder="Skórzana tapicerka&#10;Dach panoramiczny&#10;Kamera 360°&#10;Asystent parkowania&#10;Tempomat adaptacyjny&#10;System audio premium&#10;Felgi aluminiowe 19&#34;&#10;Hak holowniczy"><?php echo esc_textarea($wyposazenie); ?></textarea>
                <p class="description" style="margin-top: 10px;">
                    Każda nowa linia to jeden element wyposażenia
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
                'initial_payments' => [0],
                'prices' => []
            ];
        }
        
        // Upewnij się, że initial_payments istnieje (wsteczna kompatybilność)
        if (empty($config['initial_payments'])) {
            $config['initial_payments'] = [0];
        }

        $cena_najnizsza = get_post_meta($post->ID, '_lowest_price', true);
        $display_price_key = get_post_meta($post->ID, '_display_price_key', true);
        ?>
        <div style="padding: 0;">
            <div class="flexmile-pricing-input-group">
                <label for="flexmile_rental_periods">
                    <span>Dostępne okresy wynajmu (miesiące)</span>
                </label>
                <input type="text"
                       id="flexmile_rental_periods"
                       name="rental_periods"
                       value="<?php echo esc_attr(implode(',', $config['rental_periods'])); ?>"
                       placeholder="np. 12,24,36,48">
                <p class="description" style="margin-top: 8px; margin-bottom: 0; color: #64748b; font-size: 12px;">
                    Oddziel przecinkami, np: 12,24,36,48
                </p>
            </div>

            <div class="flexmile-pricing-input-group">
                <label for="flexmile_mileage_limits">
                    <span>Roczne limity kilometrów</span>
                </label>
                <input type="text"
                       id="flexmile_mileage_limits"
                       name="mileage_limits"
                       value="<?php echo esc_attr(implode(',', $config['mileage_limits'])); ?>"
                       placeholder="np. 10000,15000,20000">
                <p class="description" style="margin-top: 8px; margin-bottom: 0; color: #64748b; font-size: 12px;">
                    Oddziel przecinkami, np: 10000,15000,20000
                </p>
            </div>

            <div class="flexmile-pricing-input-group">
                <label for="flexmile_initial_payments">
                    <span>Opłaty początkowe (zł)</span>
                </label>
                <input type="text"
                       id="flexmile_initial_payments"
                       name="initial_payments"
                       value="<?php echo esc_attr(implode(',', $config['initial_payments'])); ?>"
                       placeholder="np. 0,5000,10000">
                <p class="description" style="margin-top: 8px; margin-bottom: 0; color: #64748b; font-size: 12px;">
                    Oddziel przecinkami, np: 0,5000,10000 (0 oznacza brak opłaty początkowej)
                </p>
            </div>

            <button type="button"
                    id="flexmile_generate_price_matrix"
                    class="button flexmile-btn-primary"
                    style="width: 100%; padding: 12px 18px; margin-bottom: 20px; justify-content: center; font-size: 14px;">
                Wygeneruj tabelę cen
            </button>

            <div id="flexmile_price_matrix">
                <?php $this->render_price_matrix($config, $display_price_key); ?>
            </div>
            <input type="hidden" name="display_price_key" value="<?php echo esc_attr($display_price_key); ?>" id="flexmile_display_price_key">

            <hr style="border: none; border-top: 2px solid #e5e7eb; margin: 24px 0;">

            <div class="flexmile-flag-item">
                <input type="checkbox"
                       id="reservation_active"
                       name="reservation_active"
                       value="1"
                       <?php checked($rezerwacja_aktywna, '1'); ?>>
                <label for="reservation_active" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                    <span><strong>Samochód zarezerwowany</strong></span>
                </label>
            </div>
            <p class="description" style="margin-top: 8px; margin-bottom: 0; color: #64748b; font-size: 12px;">
                Zaznacz jeśli samochód jest aktualnie zarezerwowany i niedostępny do wynajmu.
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var generateMatrix = function() {
                var okresy = $('#flexmile_rental_periods').val();
                var limity = $('#flexmile_mileage_limits').val();
                var oplaty = $('#flexmile_initial_payments').val() || '0';

                if (!okresy || !limity) {
                    $('#flexmile_price_matrix').html('<p style="text-align: center; color: #6b7280; padding: 16px;">Uzupełnij okresy i limity kilometrów, aby wygenerować tabelę cen.</p>');
                    return;
                }

                $.post(ajaxurl, {
                    action: 'flexmile_generate_price_matrix',
                    post_id: <?php echo $post->ID; ?>,
                    okresy: okresy,
                    limity: limity,
                    oplaty: oplaty,
                    nonce: '<?php echo wp_create_nonce('flexmile_price_matrix'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#flexmile_price_matrix').html(response.data.html);
                        // Upewnij się, że hidden input istnieje
                        if ($('#flexmile_display_price_key').length === 0) {
                            $('#flexmile_price_matrix').after('<input type="hidden" name="display_price_key" value="" id="flexmile_display_price_key">');
                        }
                        // Zaktualizuj wskaźnik postępu
                        if (typeof updateProgressIndicator === 'function') {
                            updateProgressIndicator();
                        }
                        // Re-inicjalizuj checkboxy
                        initDisplayPriceCheckboxes();
                    } else {
                        $('#flexmile_price_matrix').html('<p style="text-align: center; color: #ef4444; padding: 16px;">Błąd: ' + response.data.message + '</p>');
                    }
                });
            };

            // Automatyczne generowanie przy zmianie okresów, limitów lub opłat początkowych
            $('#flexmile_rental_periods, #flexmile_mileage_limits, #flexmile_initial_payments').on('blur', function() {
                var okresy = $('#flexmile_rental_periods').val();
                var limity = $('#flexmile_mileage_limits').val();
                
                if (okresy && limity) {
                    // Opóźnienie aby użytkownik mógł dokończyć wpisywanie
                    setTimeout(generateMatrix, 500);
                }
            });

            // Ręczne generowanie przyciskiem
            $('#flexmile_generate_price_matrix').on('click', function() {
                var button = $(this);
                var okresy = $('#flexmile_rental_periods').val();
                var limity = $('#flexmile_mileage_limits').val();
                var oplaty = $('#flexmile_initial_payments').val() || '0';

                if (!okresy || !limity) {
                    alert('Uzupełnij okresy i limity kilometrów!');
                    return;
                }

                button.prop('disabled', true).html('⏳ Generowanie...');

                $.post(ajaxurl, {
                    action: 'flexmile_generate_price_matrix',
                    post_id: <?php echo $post->ID; ?>,
                    okresy: okresy,
                    limity: limity,
                    oplaty: oplaty,
                    nonce: '<?php echo wp_create_nonce('flexmile_price_matrix'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#flexmile_price_matrix').html(response.data.html);
                        // Upewnij się, że hidden input istnieje
                        if ($('#flexmile_display_price_key').length === 0) {
                            $('#flexmile_price_matrix').after('<input type="hidden" name="display_price_key" value="" id="flexmile_display_price_key">');
                        }
                        // Zaktualizuj wskaźnik postępu
                        if (typeof updateProgressIndicator === 'function') {
                            updateProgressIndicator();
                        }
                        // Re-inicjalizuj checkboxy
                        initDisplayPriceCheckboxes();
                    } else {
                        alert('Błąd: ' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).html('🔄 Wygeneruj tabelę cen');
                });
            });

            // Inicjalizacja przy załadowaniu strony
            generateMatrix();

            // Obsługa checkboxów wyboru ceny wyświetlanej
            function initDisplayPriceCheckboxes() {
                // Upewnij się, że hidden input istnieje
                if ($('#flexmile_display_price_key').length === 0) {
                    $('#flexmile_price_matrix').after('<input type="hidden" name="display_price_key" value="" id="flexmile_display_price_key">');
                }
                
                $('.flexmile-display-price-checkbox').off('change').on('change', function() {
                    var $checkbox = $(this);
                    var priceKey = $checkbox.data('price-key');
                    var isChecked = $checkbox.is(':checked');

                    if (isChecked) {
                        // Odznacz wszystkie inne checkboxy
                        $('.flexmile-display-price-checkbox').not($checkbox).prop('checked', false);
                        // Ustaw wartość w hidden input
                        $('#flexmile_display_price_key').val(priceKey);
                    } else {
                        // Jeśli odznaczono, usuń wybór
                        $('#flexmile_display_price_key').val('');
                    }
                });
            }

            // Inicjalizuj przy załadowaniu
            initDisplayPriceCheckboxes();
        });
        </script>
        <?php
    }

    /**
     * Renderuje macierz cen (z obsługą opłat początkowych)
     */
    private function render_price_matrix($config, $display_price_key = '') {
        if (empty($config['rental_periods']) || empty($config['mileage_limits'])) {
            echo '<div style="text-align: center; padding: 40px 20px; background: #f9fafb; border-radius: 10px; border: 2px dashed #e5e7eb;">';
            echo '<p style="margin: 0; color: #6b7280; font-size: 14px; font-weight: 500;">Uzupełnij okresy i limity kilometrów powyżej,<br>a tabela cen wygeneruje się automatycznie.</p>';
            echo '</div>';
            return;
        }

        // Upewnij się, że initial_payments istnieje (wsteczna kompatybilność)
        if (empty($config['initial_payments'])) {
            $config['initial_payments'] = [0];
        }

        $min_price = PHP_FLOAT_MAX;
        $min_key = '';

        // Renderuj jedną tabelę dla każdej opłaty początkowej
        foreach ($config['initial_payments'] as $index => $initial_payment):
            $is_first = $index === 0;
            $is_last = $index === count($config['initial_payments']) - 1;
        ?>
        <div style="margin-bottom: <?php echo $is_last ? '0' : '32px'; ?>;">
            <div style="margin-bottom: 12px; padding: 10px 14px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid #0ea5e9; border-radius: 8px;">
                <div style="font-size: 14px; font-weight: 600; color: #0c4a6e;">
                    <?php if ($initial_payment == 0): ?>
                        Bez opłaty początkowej
                    <?php else: ?>
                        Opłata początkowa: <strong><?php echo number_format($initial_payment, 0, ',', ' '); ?> zł</strong>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="widefat" style="border-collapse: collapse; width: 100%; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                    <thead>
                        <tr>
                            <th style="padding: 14px 16px; text-align: left; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #ffffff; font-weight: 600; font-size: 13px; border-right: 1px solid rgba(255,255,255,0.2);">
                                Okres / Limit km
                            </th>
                            <?php foreach ($config['mileage_limits'] as $limit): ?>
                            <th style="padding: 14px 16px; text-align: center; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #ffffff; font-weight: 600; font-size: 13px; border-right: 1px solid rgba(255,255,255,0.2);">
                                <?php echo number_format($limit, 0, '', ' '); ?> km/rok
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config['rental_periods'] as $okres): ?>
                        <tr style="transition: background 0.2s ease;">
                            <td style="padding: 14px 16px; font-weight: 600; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 13px; color: #374151;">
                                <span style="display: inline-flex; align-items: center; gap: 6px;">
                                    <span><?php echo $okres; ?> miesięcy</span>
                                </span>
                            </td>
                            <?php foreach ($config['mileage_limits'] as $limit):
                                // Nowy format klucza: okres_limit_initial_payment
                                $key = $okres . '_' . $limit . '_' . $initial_payment;
                                
                                // Wsteczna kompatybilność: sprawdź też stary format (okres_limit)
                                $old_key = $okres . '_' . $limit;
                                $cena = '';
                                
                                if (isset($config['prices'][$key])) {
                                    $cena = $config['prices'][$key];
                                } elseif (isset($config['prices'][$old_key]) && $initial_payment == 0) {
                                    // Dla opłaty 0, użyj starego formatu jeśli istnieje
                                    $cena = $config['prices'][$old_key];
                                }

                                if (!empty($cena) && is_numeric($cena) && $cena < $min_price) {
                                    $min_price = $cena;
                                    $min_key = $key;
                                }
                            ?>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; background: #ffffff;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <input type="number"
                                           name="price_matrix[<?php echo esc_attr($key); ?>]"
                                           value="<?php echo esc_attr($cena); ?>"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00"
                                           style="width: 100%; padding: 10px 12px; border: 2px solid #d1d5db; border-radius: 6px; text-align: right; font-weight: 600; font-size: 13px; transition: all 0.2s ease;">
                                    <?php if (!empty($cena) && is_numeric($cena)): ?>
                                    <div style="margin-top: 6px;">
                                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; white-space: nowrap;">
                                            <input type="checkbox"
                                                   class="flexmile-display-price-checkbox"
                                                   data-price-key="<?php echo esc_attr($key); ?>"
                                                   <?php checked($display_price_key, $key); ?>
                                                   style="width: 16px; height: 16px; margin: 0; cursor: pointer; flex-shrink: 0;">
                                            <span style="font-size: 12px;">Wyświetlaj tę cenę</span>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($min_price < PHP_FLOAT_MAX): 
            // Sprawdź czy jest wybrana cena wyświetlana
            $display_price = '';
            $display_price_label = 'Najniższa cena';
            if (!empty($display_price_key) && isset($config['prices'][$display_price_key])) {
                $display_price = $config['prices'][$display_price_key];
                $display_price_label = 'Wybrana cena wyświetlana';
            } else {
                $display_price = $min_price;
            }
        ?>
        <div style="margin-top: 24px; padding: 14px 16px; background: linear-gradient(135deg, #ecfdf3 0%, #d1fae5 100%); border-left: 4px solid #22c55e; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
            <div style="flex: 1;">
                <div style="font-size: 12px; font-weight: 600; color: #166534; margin-bottom: 2px;">
                    <?php echo esc_html($display_price_label); ?> (widoczna na liście ofert)
                </div>
                <div style="font-size: 18px; font-weight: 700; color: #15803d;">
                    <?php echo number_format($display_price, 2, ',', ' '); ?> zł/mies.
                </div>
                <?php if (!empty($display_price_key) && $display_price != $min_price): ?>
                <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                    (Najniższa cena: <?php echo number_format($min_price, 2, ',', ' '); ?> zł/mies.)
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div style="margin-top: 24px; padding: 14px 16px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
            <div style="flex: 1; font-size: 13px; color: #92400e;">
                <strong>Uwaga:</strong> Wypełnij ceny w tabelach powyżej, aby wyświetlić najniższą cenę.
            </div>
        </div>
        <?php endif; ?>
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
        $oplaty_str = isset($_POST['oplaty']) ? sanitize_text_field($_POST['oplaty']) : '0';

        $okresy = array_map('intval', array_filter(explode(',', $okresy_str), function($v) { return trim($v) !== ''; }));
        $limity_km = array_map('intval', array_filter(explode(',', $limity_str), function($v) { return trim($v) !== ''; }));
        $oplaty_poczatkowe = array_map('intval', array_filter(explode(',', $oplaty_str), function($v) { return trim($v) !== ''; }));

        if (empty($okresy) || empty($limity_km)) {
            wp_send_json_error(['message' => 'Nieprawidłowe dane']);
        }

        // Jeśli brak opłat początkowych, użyj domyślnej [0]
        if (empty($oplaty_poczatkowe)) {
            $oplaty_poczatkowe = [0];
        }

        $old_config = get_post_meta($post_id, '_pricing_config', true);
        $old_ceny = is_array($old_config) && isset($old_config['prices']) ? $old_config['prices'] : [];
        $display_price_key = get_post_meta($post_id, '_display_price_key', true);

        $config = [
            'rental_periods' => $okresy,
            'mileage_limits' => $limity_km,
            'initial_payments' => $oplaty_poczatkowe,
            'prices' => $old_ceny
        ];

        ob_start();
        $this->render_price_matrix($config, $display_price_key);
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
        <div style="padding: 0;">
            <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #e5e7eb;">
                <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <span>Statusy samochodu</span>
                </h4>
            </div>

            <div class="flexmile-flag-item">
                <input type="checkbox" id="new_car" name="new_car" value="1" <?php checked($nowy, '1'); ?>>
                <label for="new_car" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                    <span>Nowy samochód</span>
                </label>
            </div>

            <div class="flexmile-flag-item">
                <input type="checkbox" id="available_immediately" name="available_immediately" value="1" <?php checked($od_reki, '1'); ?>>
                <label for="available_immediately" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                    <span>Dostępny od ręki</span>
                </label>
            </div>

            <div class="flexmile-flag-item">
                <input type="checkbox" id="<?php echo esc_attr($coming_soon_toggle_id); ?>" name="coming_soon" value="1" <?php checked($wkrotce, '1'); ?>>
                <label for="<?php echo esc_attr($coming_soon_toggle_id); ?>" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                    <span>Dostępny wkrótce</span>
                </label>
            </div>
            
            <div id="<?php echo esc_attr($coming_soon_wrapper_id); ?>"
                 style="margin-left: 12px; margin-top: 8px; margin-bottom: 12px; padding: 12px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; <?php echo $wkrotce === '1' ? '' : 'display: none;'; ?>">
                <label for="<?php echo esc_attr($coming_soon_input_id); ?>"
                       style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 8px;">
                    Planowana dostępność (miesiąc i rok)
                </label>
                <input type="text"
                       id="<?php echo esc_attr($coming_soon_input_id); ?>"
                       name="coming_soon_date"
                       value="<?php echo esc_attr($wkrotce_data); ?>"
                       placeholder="Np. Listopad 2025"
                       autocomplete="off"
                       class="flexmile-input"
                       style="width: 100%; max-width: 100%; margin-bottom: 6px;">
                <span class="description" style="display: block; font-size: 11px; color: #64748b; margin: 0;">
                    Możesz wpisać miesiąc i rok w formacie „Listopad 2025”.
                </span>
            </div>

            <div class="flexmile-flag-item">
                <input type="checkbox" id="most_popular" name="most_popular" value="1" <?php checked($najczesciej, '1'); ?>>
                <label for="most_popular" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                    <span>Najczęściej wybierany</span>
                </label>
            </div>
        </div>
        <script>
            (function() {
                const toggle = document.getElementById('<?php echo esc_js($coming_soon_toggle_id); ?>');
                const wrapper = document.getElementById('<?php echo esc_js($coming_soon_wrapper_id); ?>');
                const availableImmediately = document.getElementById('available_immediately');

                if (!toggle || !wrapper) {
                    return;
                }

                const refreshVisibility = () => {
                    wrapper.style.display = toggle.checked ? 'block' : 'none';
                };

                // Wzajemne wykluczanie: "Dostępny od ręki" vs "Dostępny wkrótce"
                const syncExclusiveFlags = () => {
                    if (toggle.checked && availableImmediately && availableImmediately.checked) {
                        // Jeśli zaznaczono "Dostępny wkrótce", odznacz "Dostępny od ręki"
                        availableImmediately.checked = false;
                    }
                    if (availableImmediately && availableImmediately.checked && toggle.checked) {
                        // Jeśli zaznaczono "Dostępny od ręki", odznacz "Dostępny wkrótce"
                        toggle.checked = false;
                        refreshVisibility();
                    }
                };

                toggle.addEventListener('change', function() {
                    refreshVisibility();
                    syncExclusiveFlags();
                });

                if (availableImmediately) {
                    availableImmediately.addEventListener('change', function() {
                        if (availableImmediately.checked) {
                            // Odznacz "Dostępny wkrótce" przy zaznaczeniu "Dostępny od ręki"
                            toggle.checked = false;
                            refreshVisibility();
                        }
                    });
                }

                // Startowy stan po załadowaniu edytora
                syncExclusiveFlags();
                refreshVisibility();
            })();
        </script>
        <?php
    }

    /**
     * Renderuje meta box z dodatkowymi usługami i informacjami
     */
    public function render_additional_services_meta_box($post) {
        $finansowanie = get_post_meta($post->ID, '_financing', true) === '1';
        $serwis_pojazdu = get_post_meta($post->ID, '_vehicle_service', true) === '1';
        $ubezpieczenie = get_post_meta($post->ID, '_insurance_oc_ac_nnw', true) === '1';
        $assistance_24h = get_post_meta($post->ID, '_assistance_24h', true) === '1';
        $opony = get_post_meta($post->ID, '_summer_winter_tires', true) === '1';

        // Pobierz tytuły i opisy dla każdej usługi
        $financing_title = get_post_meta($post->ID, '_financing_title', true) ?: 'Finansowanie';
        $financing_description = get_post_meta($post->ID, '_financing_description', true) ?: 'Elastyczne warunki najmu dopasowane do Twoich potrzeb i budżetu.';
        
        $vehicle_service_title = get_post_meta($post->ID, '_vehicle_service_title', true) ?: 'Serwis pojazdu';
        $vehicle_service_description = get_post_meta($post->ID, '_vehicle_service_description', true) ?: 'Regularne przeglądy i naprawy w cenie — bez dodatkowych kosztów.';
        
        $insurance_title = get_post_meta($post->ID, '_insurance_oc_ac_nnw_title', true) ?: 'Ubezpieczenie OC/AC/NNW';
        $insurance_description = get_post_meta($post->ID, '_insurance_oc_ac_nnw_description', true) ?: 'Pełna ochrona przez cały okres wynajmu, wliczona w ratę.';
        
        $assistance_title = get_post_meta($post->ID, '_assistance_24h_title', true) ?: 'Assistance 24h';
        $assistance_description = get_post_meta($post->ID, '_assistance_24h_description', true) ?: 'Pomoc na drodze o każdej porze — w Polsce i za granicą.';
        
        $tires_title = get_post_meta($post->ID, '_summer_winter_tires_title', true) ?: 'Opony letnie i zimowe';
        $tires_description = get_post_meta($post->ID, '_summer_winter_tires_description', true) ?: 'Zawsze gotowy do jazdy!';

        $custom_data = get_post_meta($post->ID, '_custom_additional_data', true);
        if (empty($custom_data) || !is_array($custom_data)) {
            $custom_data = [];
        }

        // Definicje usług z predefiniowanymi wartościami
        $services = [
            'financing' => [
                'label' => 'Finansowanie',
                'checked' => $finansowanie,
                'title' => $financing_title,
                'description' => $financing_description,
            ],
            'vehicle_service' => [
                'label' => 'Serwis pojazdu',
                'checked' => $serwis_pojazdu,
                'title' => $vehicle_service_title,
                'description' => $vehicle_service_description,
            ],
            'insurance_oc_ac_nnw' => [
                'label' => 'Ubezpieczenie OC/AC/NNW',
                'checked' => $ubezpieczenie,
                'title' => $insurance_title,
                'description' => $insurance_description,
            ],
            'assistance_24h' => [
                'label' => 'Assistance 24h',
                'checked' => $assistance_24h,
                'title' => $assistance_title,
                'description' => $assistance_description,
            ],
            'summer_winter_tires' => [
                'label' => 'Opony letnie i zimowe',
                'checked' => $opony,
                'title' => $tires_title,
                'description' => $tires_description,
            ],
        ];
        ?>
        <div style="padding: 0;">
            <div style="margin-bottom: 24px;">
                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 600; color: #1e293b;">
                    Dodatkowe usługi
                </h4>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($services as $key => $service): ?>
                    <div class="flexmile-service-item" style="padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div style="margin-bottom: 12px;display: flex;align-items: center;justify-content: flex-start;gap: 16px;">
                            <input type="checkbox" 
                                   id="<?php echo esc_attr($key); ?>" 
                                   name="<?php echo esc_attr($key); ?>" 
                                   value="1" 
                                   class="flexmile-service-checkbox"
                                   <?php checked($service['checked']); ?>>
                            <label for="<?php echo esc_attr($key); ?>" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-weight: 600; color: #1e293b;">
                                <span><?php echo esc_html($service['label']); ?></span>
                            </label>
                        </div>
                        <div class="flexmile-service-fields" style="display: <?php echo $service['checked'] ? 'block' : 'none'; ?>; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
                                    Tytuł <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text"
                                       name="<?php echo esc_attr($key); ?>_title"
                                       value="<?php echo esc_attr($service['title']); ?>"
                                       class="flexmile-service-title"
                                       required
                                       style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
                                    Opis <span style="color: #ef4444;">*</span>
                                </label>
                                <textarea name="<?php echo esc_attr($key); ?>_description"
                                          rows="3"
                                          class="flexmile-service-description"
                                          required
                                          style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; resize: vertical; font-family: inherit;"><?php echo esc_textarea($service['description']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr style="border: none; border-top: 2px solid #e5e7eb; margin: 24px 0;">

            <div style="margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b;">
                        Dodatkowe informacje
                    </h4>
                    <button type="button" 
                            id="flexmile-add-custom-data" 
                            class="button button-secondary"
                            style="display: <?php echo count($custom_data) < 5 ? 'inline-block' : 'none'; ?>;">
                        <span style="font-size: 16px; line-height: 1; margin-right: 4px;">+</span> Dodaj
                    </button>
                </div>
                <p class="description" style="margin: 0; color: #64748b; font-size: 13px;">
                    Możesz dodać maksymalnie 5 dodatkowych elementów informacyjnych. Każdy element składa się z tytułu i opisu.
                </p>
            </div>

            <div id="flexmile-custom-additional-data-container" style="display: flex; flex-direction: column; gap: 16px;">
                <?php
                $item_index = 0;
                foreach ($custom_data as $index => $item):
                    if (empty($item['title']) && empty($item['description'])) {
                        continue;
                    }
                ?>
                <div class="flexmile-custom-data-item" data-index="<?php echo $item_index; ?>" style="position: relative; padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <button type="button" 
                            class="flexmile-remove-custom-data" 
                            style="position: absolute; top: 12px; right: 12px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; line-height: 1; padding: 0; transition: background 0.2s;"
                            title="Usuń">
                        ×
                    </button>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px; padding-right: 32px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
                                Tytuł
                            </label>
                            <input type="text"
                                   name="custom_additional_data[<?php echo $item_index; ?>][title]"
                                   value="<?php echo esc_attr($item['title']); ?>"
                                   placeholder="np. Gwarancja producenta"
                                   class="flexmile-input"
                                   style="width: 100%;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
                                Opis
                            </label>
                            <textarea name="custom_additional_data[<?php echo $item_index; ?>][description]"
                                      rows="3"
                                      placeholder="np. Pełna gwarancja producenta na okres 3 lat lub 100 000 km"
                                      class="flexmile-input"
                                      style="width: 100%; resize: vertical;"><?php echo esc_textarea($item['description']); ?></textarea>
                        </div>
                    </div>
                </div>
                <?php
                    $item_index++;
                endforeach;
                ?>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Obsługa pokazywania/ukrywania pól dla dodatkowych usług
                $('.flexmile-service-checkbox').on('change', function() {
                    var checkbox = $(this);
                    var serviceItem = checkbox.closest('.flexmile-service-item');
                    var fieldsContainer = serviceItem.find('.flexmile-service-fields');
                    var titleInput = serviceItem.find('.flexmile-service-title');
                    var descriptionInput = serviceItem.find('.flexmile-service-description');
                    
                    if (checkbox.is(':checked')) {
                        fieldsContainer.slideDown(300);
                        titleInput.prop('required', true);
                        descriptionInput.prop('required', true);
                    } else {
                        fieldsContainer.slideUp(300);
                        titleInput.prop('required', false);
                        descriptionInput.prop('required', false);
                    }
                });

                // Inicjalizacja przy załadowaniu strony
                $('.flexmile-service-checkbox').each(function() {
                    var checkbox = $(this);
                    var serviceItem = checkbox.closest('.flexmile-service-item');
                    var fieldsContainer = serviceItem.find('.flexmile-service-fields');
                    var titleInput = serviceItem.find('.flexmile-service-title');
                    var descriptionInput = serviceItem.find('.flexmile-service-description');
                    
                    if (!checkbox.is(':checked')) {
                        fieldsContainer.hide();
                        titleInput.prop('required', false);
                        descriptionInput.prop('required', false);
                    }
                });

                var maxItems = 5;
                var currentIndex = <?php echo $item_index; ?>;
                var container = $('#flexmile-custom-additional-data-container');
                var addButton = $('#flexmile-add-custom-data');

                // Szablon nowego pola
                function getNewItemTemplate(index) {
                    return '<div class="flexmile-custom-data-item" data-index="' + index + '" style="position: relative; padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; animation: slideDown 0.3s ease;">' +
                        '<button type="button" class="flexmile-remove-custom-data" style="position: absolute; top: 12px; right: 12px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; line-height: 1; padding: 0; transition: background 0.2s;" title="Usuń">×</button>' +
                        '<div style="display: grid; grid-template-columns: 1fr; gap: 12px; padding-right: 32px;">' +
                        '<div>' +
                        '<label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">Tytuł</label>' +
                        '<input type="text" name="custom_additional_data[' + index + '][title]" value="" placeholder="np. Gwarancja producenta" class="flexmile-input" style="width: 100%;">' +
                        '</div>' +
                        '<div>' +
                        '<label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">Opis</label>' +
                        '<textarea name="custom_additional_data[' + index + '][description]" rows="3" placeholder="np. Pełna gwarancja producenta na okres 3 lat lub 100 000 km" class="flexmile-input" style="width: 100%; resize: vertical;"></textarea>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                }

                // Dodaj nowe pole
                addButton.on('click', function() {
                    if (container.find('.flexmile-custom-data-item').length >= maxItems) {
                        alert('Możesz dodać maksymalnie ' + maxItems + ' elementów.');
                        return;
                    }

                    var newItem = $(getNewItemTemplate(currentIndex));
                    container.append(newItem);
                    currentIndex++;

                    // Przewiń do nowego pola
                    $('html, body').animate({
                        scrollTop: newItem.offset().top - 100
                    }, 300);

                    // Ukryj przycisk jeśli osiągnięto limit
                    if (container.find('.flexmile-custom-data-item').length >= maxItems) {
                        addButton.hide();
                    }
                });

                // Usuń pole
                container.on('click', '.flexmile-remove-custom-data', function() {
                    var item = $(this).closest('.flexmile-custom-data-item');
                    item.fadeOut(300, function() {
                        $(this).remove();
                        // Pokaż przycisk jeśli jest miejsce
                        if (container.find('.flexmile-custom-data-item').length < maxItems) {
                            addButton.show();
                        }
                    });
                });

                // Aktualizuj widoczność przycisku przy załadowaniu
                if (container.find('.flexmile-custom-data-item').length >= maxItems) {
                    addButton.hide();
                }
            });
            </script>
            <style>
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .flexmile-remove-custom-data:hover {
                background: #dc2626 !important;
            }
            </style>
        </div>
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

        // Zapisz flagę ręcznej edycji tytułu
        if (isset($_POST['title_manually_edited'])) {
            update_post_meta($post_id, '_title_manually_edited', '1');
        } else {
            // Jeśli nie ma flagi, może użytkownik chce automatycznie generować tytuł
            // Ale nie usuwamy flagi, jeśli już była ustawiona (użytkownik może chcieć wrócić do automatycznego)
            // Usuwamy tylko jeśli użytkownik wyraźnie to zaznaczył (nie ma takiej opcji w formularzu na razie)
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

        // Slider hero na stronie głównej
        $hero_slider_enabled = isset($_POST['hero_slider_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_hero_slider_enabled', $hero_slider_enabled);

        if (!empty($_POST['hero_slider_image_id'])) {
            $image_id = intval($_POST['hero_slider_image_id']);
            if ($image_id > 0) {
                update_post_meta($post_id, '_hero_slider_image_id', $image_id);
            }
        } else {
            delete_post_meta($post_id, '_hero_slider_image_id');
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
            '_interior_color' => 'sanitize_text_field',
            '_co2_emission' => 'intval',
            '_car_condition' => 'sanitize_text_field',
            // _mileage jest obsługiwane osobno poniżej
        ];

        foreach ($fields as $field => $sanitize) {
            $key = ltrim($field, '_');
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $field, $sanitize($_POST[$key]));
            }
        }

        // Obsługa stanu i przebiegu
        if (isset($_POST['car_condition'])) {
            $car_condition = sanitize_text_field($_POST['car_condition']);
            
            if ($car_condition === 'new') {
                // Wyczyść przebieg dla nowych samochodów
                delete_post_meta($post_id, '_mileage');
            } elseif (in_array($car_condition, ['demo', 'used', 'ex_lease', 'ex_contract'])) {
                // Zapisz przebieg dla demo, używanych, poleasingowych i pokontraktowych
                if (isset($_POST['mileage']) && !empty($_POST['mileage'])) {
                    $mileage = intval($_POST['mileage']);
                    if ($mileage > 0) {
                        update_post_meta($post_id, '_mileage', $mileage);
                    } else {
                        delete_post_meta($post_id, '_mileage');
                    }
                } else {
                    delete_post_meta($post_id, '_mileage');
                }
            } else {
                // Jeśli stan nie jest ustawiony lub jest pusty, wyczyść przebieg
                delete_post_meta($post_id, '_mileage');
            }
        }

        if (isset($_POST['rental_periods']) && isset($_POST['mileage_limits'])) {
            $okresy = array_map('intval', array_filter(explode(',', $_POST['rental_periods']), function($v) { return trim($v) !== ''; }));
            $limity_km = array_map('intval', array_filter(explode(',', $_POST['mileage_limits']), function($v) { return trim($v) !== ''; }));
            
            // Opłaty początkowe
            $oplaty_poczatkowe = [];
            if (isset($_POST['initial_payments']) && !empty($_POST['initial_payments'])) {
                $oplaty_poczatkowe = array_map('intval', array_filter(explode(',', $_POST['initial_payments']), function($v) { return trim($v) !== ''; }));
            }
            // Domyślnie [0] jeśli brak
            if (empty($oplaty_poczatkowe)) {
                $oplaty_poczatkowe = [0];
            }

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
                'initial_payments' => $oplaty_poczatkowe,
                'prices' => $ceny
            ];

            update_post_meta($post_id, '_pricing_config', $config);

            $min_price = !empty($ceny) ? min($ceny) : 0;
            update_post_meta($post_id, '_lowest_price', $min_price);

            // Zapisz wybraną cenę wyświetlaną
            if (isset($_POST['display_price_key']) && !empty($_POST['display_price_key'])) {
                $display_key = sanitize_text_field($_POST['display_price_key']);
                // Sprawdź czy klucz istnieje w cenach
                if (isset($ceny[$display_key])) {
                    update_post_meta($post_id, '_display_price_key', $display_key);
                    update_post_meta($post_id, '_display_price', floatval($ceny[$display_key]));
                } else {
                    // Jeśli klucz nie istnieje, usuń wybraną cenę
                    delete_post_meta($post_id, '_display_price_key');
                    delete_post_meta($post_id, '_display_price');
                }
            } else {
                // Jeśli nie wybrano ceny, usuń zapisaną
                delete_post_meta($post_id, '_display_price_key');
                delete_post_meta($post_id, '_display_price');
            }
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

        // Zapisz dodatkowe usługi (5 pól boolean) wraz z tytułami i opisami
        $additional_services = [
            '_financing' => 'financing',
            '_vehicle_service' => 'vehicle_service',
            '_insurance_oc_ac_nnw' => 'insurance_oc_ac_nnw',
            '_assistance_24h' => 'assistance_24h',
            '_summer_winter_tires' => 'summer_winter_tires',
        ];

        foreach ($additional_services as $meta_key => $post_key) {
            $value = isset($_POST[$post_key]) ? '1' : '0';
            update_post_meta($post_id, $meta_key, $value);
            
            // Zapisz tytuł i opis dla każdej usługi
            $title_key = $meta_key . '_title';
            $description_key = $meta_key . '_description';
            
            if (isset($_POST[$post_key . '_title'])) {
                $title = sanitize_text_field($_POST[$post_key . '_title']);
                update_post_meta($post_id, $title_key, $title);
            }
            
            if (isset($_POST[$post_key . '_description'])) {
                $description = sanitize_textarea_field($_POST[$post_key . '_description']);
                update_post_meta($post_id, $description_key, $description);
            }
            
            // Jeśli usługa nie jest zaznaczona, usuń tytuł i opis
            if ($value === '0') {
                delete_post_meta($post_id, $title_key);
                delete_post_meta($post_id, $description_key);
            }
        }

        // Zapisz customowe dodatkowe dane (max 5 elementów)
        if (isset($_POST['custom_additional_data']) && is_array($_POST['custom_additional_data'])) {
            $custom_data = [];
            foreach ($_POST['custom_additional_data'] as $item) {
                if (isset($item['title']) && isset($item['description'])) {
                    $title = sanitize_text_field($item['title']);
                    $description = sanitize_textarea_field($item['description']);
                    
                    // Dodaj tylko jeśli przynajmniej tytuł jest wypełniony
                    if (!empty($title)) {
                        $custom_data[] = [
                            'title' => $title,
                            'description' => $description,
                        ];
                    }
                }
            }
            
            // Ogranicz do maksymalnie 5 elementów
            $custom_data = array_slice($custom_data, 0, 5);
            
            if (!empty($custom_data)) {
                update_post_meta($post_id, '_custom_additional_data', $custom_data);
            } else {
                delete_post_meta($post_id, '_custom_additional_data');
            }
        } else {
            delete_post_meta($post_id, '_custom_additional_data');
        }
    }

    /**
     * Czyści cache marek i modeli przy zapisie oferty
     */
    public function clear_brands_cache_on_save($post_id) {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        // Wyczyść cache marek
        \FlexMile\API\Offers_Endpoint::clear_brands_cache();
        
        // Wyczyść cache modeli - sprawdź starą i nową markę
        $old_brand_slug = get_post_meta($post_id, '_car_brand_slug', true);
        if (isset($_POST['car_brand_slug'])) {
            $new_brand_slug = sanitize_text_field($_POST['car_brand_slug']);
            // Wyczyść cache dla starej marki (jeśli się zmieniła)
            if ($old_brand_slug && $old_brand_slug !== $new_brand_slug) {
                \FlexMile\API\Offers_Endpoint::clear_models_cache($old_brand_slug);
            }
            // Wyczyść cache dla nowej marki
            \FlexMile\API\Offers_Endpoint::clear_models_cache($new_brand_slug);
        } elseif ($old_brand_slug) {
            // Jeśli marka się nie zmienia, wyczyść cache dla obecnej marki
            \FlexMile\API\Offers_Endpoint::clear_models_cache($old_brand_slug);
        }
    }

    /**
     * Czyści cache marek i modeli przy zmianie statusu oferty
     */
    public function clear_brands_cache_on_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Czyść cache tylko jeśli status wpływa na widoczność (publish/unpublish)
        if (($old_status === 'publish' && $new_status !== 'publish') || 
            ($old_status !== 'publish' && $new_status === 'publish')) {
            \FlexMile\API\Offers_Endpoint::clear_brands_cache();
            
            // Wyczyść cache modeli dla marki tej oferty
            $brand_slug = get_post_meta($post->ID, '_car_brand_slug', true);
            if ($brand_slug) {
                \FlexMile\API\Offers_Endpoint::clear_models_cache($brand_slug);
            }
        }
    }

    /**
     * Czyści cache marek i modeli przy usuwaniu oferty
     */
    public function clear_brands_cache_on_delete($post_id) {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        \FlexMile\API\Offers_Endpoint::clear_brands_cache();
        
        // Wyczyść cache modeli dla marki tej oferty
        $brand_slug = get_post_meta($post_id, '_car_brand_slug', true);
        if ($brand_slug) {
            \FlexMile\API\Offers_Endpoint::clear_models_cache($brand_slug);
        }
    }
}
