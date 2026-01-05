<?php
namespace FlexMile\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pomocnicza klasa do czyszczenia cache WordPressa
 * Przydatna przy problemach z meta boxami
 */
class Cache_Clearer {

    /**
     * Czyści wszystkie cache WordPressa
     */
    public static function clear_all_cache() {
        // Wyczyść WordPress object cache
        wp_cache_flush();

        // Wyczyść rewrite rules cache
        flush_rewrite_rules();

        // Wyczyść transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");

        // Wyczyść cache meta boxów (jeśli istnieje)
        delete_user_meta(get_current_user_id(), 'meta-box-order_' . get_current_screen()->id);
        delete_user_meta(get_current_user_id(), 'closedpostboxes_' . get_current_screen()->id);
        delete_user_meta(get_current_user_id(), 'metaboxhidden_' . get_current_screen()->id);
    }

    /**
     * Czyści cache dla konkretnego typu posta
     */
    public static function clear_post_type_cache($post_type) {
        // Wyczyść cache meta boxów dla danego typu posta
        $users = get_users();
        foreach ($users as $user) {
            delete_user_meta($user->ID, 'meta-box-order_' . $post_type);
            delete_user_meta($user->ID, 'closedpostboxes_' . $post_type);
            delete_user_meta($user->ID, 'metaboxhidden_' . $post_type);
        }

        // Wyczyść rewrite rules
        flush_rewrite_rules();
    }
}












