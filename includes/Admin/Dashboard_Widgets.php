<?php
namespace FlexMile\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widgety statystyk na głównym dashboardzie WordPressa
 */
class Dashboard_Widgets {

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
        add_action('admin_head-index.php', [$this, 'add_dashboard_styles']);
    }

    /**
     * Dodaje widgety na dashboardzie
     */
    public function add_dashboard_widgets() {
        // Widget statystyk zamówień
        wp_add_dashboard_widget(
            'flexmile_orders_stats',
            'Statystyki Zamówień',
            [$this, 'render_orders_stats_widget']
        );

        // Widget statystyk rezerwacji
        wp_add_dashboard_widget(
            'flexmile_reservations_stats',
            'Statystyki Rezerwacji',
            [$this, 'render_reservations_stats_widget']
        );

        // Widget najnowszych zamówień
        wp_add_dashboard_widget(
            'flexmile_recent_orders',
            'Najnowsze Zamówienia',
            [$this, 'render_recent_orders_widget']
        );

        // Widget najnowszych rezerwacji
        wp_add_dashboard_widget(
            'flexmile_recent_reservations',
            'Najnowsze Rezerwacje',
            [$this, 'render_recent_reservations_widget']
        );

        // Widget ogólnych statystyk
        wp_add_dashboard_widget(
            'flexmile_overview',
            'Przegląd FlexMile',
            [$this, 'render_overview_widget']
        );
    }

    /**
     * Renderuje widget statystyk zamówień
     */
    public function render_orders_stats_widget() {
        $orders = get_posts([
            'post_type' => 'order',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $stats = [
            'total' => count($orders),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'today' => 0,
            'week' => 0,
        ];

        $today = strtotime('today');
        $week_ago = strtotime('-7 days');

        foreach ($orders as $order) {
            $status = get_post_meta($order->ID, '_status', true);
            if (empty($status)) {
                $status = 'pending';
            }

            switch ($status) {
                case 'pending':
                    $stats['pending']++;
                    break;
                case 'approved':
                    $stats['approved']++;
                    break;
                case 'rejected':
                    $stats['rejected']++;
                    break;
            }

            $order_date = strtotime($order->post_date);
            if ($order_date >= $today) {
                $stats['today']++;
            }
            if ($order_date >= $week_ago) {
                $stats['week']++;
            }
        }

        ?>
        <div class="flexmile-dashboard-stats">
            <div class="flexmile-stat-grid">
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Wszystkie</div>
                    <div class="flexmile-stat-value flexmile-stat-total"><?php echo esc_html($stats['total']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Oczekujące</div>
                    <div class="flexmile-stat-value flexmile-stat-pending"><?php echo esc_html($stats['pending']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Zatwierdzone</div>
                    <div class="flexmile-stat-value flexmile-stat-approved"><?php echo esc_html($stats['approved']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Odrzucone</div>
                    <div class="flexmile-stat-value flexmile-stat-rejected"><?php echo esc_html($stats['rejected']); ?></div>
                </div>
            </div>
            <div class="flexmile-stat-period">
                <div class="flexmile-stat-period-item">
                    <span class="flexmile-stat-period-label">Dziś:</span>
                    <strong><?php echo esc_html($stats['today']); ?></strong>
                </div>
                <div class="flexmile-stat-period-item">
                    <span class="flexmile-stat-period-label">Ostatni tydzień:</span>
                    <strong><?php echo esc_html($stats['week']); ?></strong>
                </div>
            </div>
            <div class="flexmile-stat-actions">
                <a href="<?php echo admin_url('edit.php?post_type=order'); ?>" class="button button-primary">Zarządzaj zamówieniami</a>
                <?php if ($stats['pending'] > 0): ?>
                    <span class="flexmile-pending-badge"><?php echo esc_html($stats['pending']); ?> oczekuje na akcję</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje widget statystyk rezerwacji
     */
    public function render_reservations_stats_widget() {
        $reservations = get_posts([
            'post_type' => 'reservation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $stats = [
            'total' => count($reservations),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'today' => 0,
            'week' => 0,
        ];

        $today = strtotime('today');
        $week_ago = strtotime('-7 days');

        foreach ($reservations as $reservation) {
            $status = get_post_meta($reservation->ID, '_status', true);
            if (empty($status)) {
                $status = 'pending';
            }

            switch ($status) {
                case 'pending':
                    $stats['pending']++;
                    break;
                case 'approved':
                    $stats['approved']++;
                    break;
                case 'rejected':
                    $stats['rejected']++;
                    break;
            }

            $reservation_date = strtotime($reservation->post_date);
            if ($reservation_date >= $today) {
                $stats['today']++;
            }
            if ($reservation_date >= $week_ago) {
                $stats['week']++;
            }
        }

        ?>
        <div class="flexmile-dashboard-stats">
            <div class="flexmile-stat-grid">
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Wszystkie</div>
                    <div class="flexmile-stat-value flexmile-stat-total"><?php echo esc_html($stats['total']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Oczekujące</div>
                    <div class="flexmile-stat-value flexmile-stat-pending"><?php echo esc_html($stats['pending']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Zatwierdzone</div>
                    <div class="flexmile-stat-value flexmile-stat-approved"><?php echo esc_html($stats['approved']); ?></div>
                </div>
                <div class="flexmile-stat-item">
                    <div class="flexmile-stat-label">Odrzucone</div>
                    <div class="flexmile-stat-value flexmile-stat-rejected"><?php echo esc_html($stats['rejected']); ?></div>
                </div>
            </div>
            <div class="flexmile-stat-period">
                <div class="flexmile-stat-period-item">
                    <span class="flexmile-stat-period-label">Dziś:</span>
                    <strong><?php echo esc_html($stats['today']); ?></strong>
                </div>
                <div class="flexmile-stat-period-item">
                    <span class="flexmile-stat-period-label">Ostatni tydzień:</span>
                    <strong><?php echo esc_html($stats['week']); ?></strong>
                </div>
            </div>
            <div class="flexmile-stat-actions">
                <a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>" class="button button-primary">Zarządzaj rezerwacjami</a>
                <?php if ($stats['pending'] > 0): ?>
                    <span class="flexmile-pending-badge"><?php echo esc_html($stats['pending']); ?> oczekuje na akcję</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje widget najnowszych zamówień
     */
    public function render_recent_orders_widget() {
        $orders = get_posts([
            'post_type' => 'order',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($orders)) {
            echo '<p>Brak zamówień.</p>';
            echo '<p><a href="' . admin_url('edit.php?post_type=order') . '" class="button">Zobacz wszystkie zamówienia</a></p>';
            return;
        }

        ?>
        <ul class="flexmile-recent-list">
            <?php foreach ($orders as $order): 
                $status = get_post_meta($order->ID, '_status', true);
                if (empty($status)) {
                    $status = 'pending';
                }

                $status_labels = [
                    'pending' => '<span class="flexmile-status-badge flexmile-status-pending">Oczekujące</span>',
                    'approved' => '<span class="flexmile-status-badge flexmile-status-approved">Zatwierdzone</span>',
                    'rejected' => '<span class="flexmile-status-badge flexmile-status-rejected">Odrzucone</span>',
                ];

                $company_name = get_post_meta($order->ID, '_company_name', true);
                $first_name = get_post_meta($order->ID, '_first_name', true);
                $last_name = get_post_meta($order->ID, '_last_name', true);
                $client_name = !empty($company_name) ? $company_name : trim($first_name . ' ' . $last_name);
                
                $offer_id = get_post_meta($order->ID, '_offer_id', true);
                $car_name = '';
                if ($offer_id) {
                    $car = get_post($offer_id);
                    if ($car) {
                        $car_name = $car->post_title;
                    }
                }

                $total_price = get_post_meta($order->ID, '_total_price', true);
                $price_display = $total_price ? number_format($total_price, 0, ',', ' ') . ' zł' : 'Brak ceny';
                ?>
                <li class="flexmile-recent-item">
                    <div class="flexmile-recent-header">
                        <strong><a href="<?php echo get_edit_post_link($order->ID); ?>"><?php echo esc_html($client_name ?: 'Brak nazwy'); ?></a></strong>
                        <?php echo $status_labels[$status]; ?>
                    </div>
                    <?php if ($car_name): ?>
                        <div class="flexmile-recent-meta">
                            <span class="dashicons dashicons-car"></span> <?php echo esc_html($car_name); ?>
                        </div>
                    <?php endif; ?>
                    <div class="flexmile-recent-meta">
                        <span class="dashicons dashicons-money-alt"></span> <?php echo esc_html($price_display); ?>
                        <span class="flexmile-recent-date"><?php echo human_time_diff(strtotime($order->post_date), current_time('timestamp')) . ' temu'; ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p><a href="<?php echo admin_url('edit.php?post_type=order'); ?>" class="button">Zobacz wszystkie zamówienia →</a></p>
        <?php
    }

    /**
     * Renderuje widget najnowszych rezerwacji
     */
    public function render_recent_reservations_widget() {
        $reservations = get_posts([
            'post_type' => 'reservation',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($reservations)) {
            echo '<p>Brak rezerwacji.</p>';
            echo '<p><a href="' . admin_url('edit.php?post_type=reservation') . '" class="button">Zobacz wszystkie rezerwacje</a></p>';
            return;
        }

        ?>
        <ul class="flexmile-recent-list">
            <?php foreach ($reservations as $reservation): 
                $status = get_post_meta($reservation->ID, '_status', true);
                if (empty($status)) {
                    $status = 'pending';
                }

                $status_labels = [
                    'pending' => '<span class="flexmile-status-badge flexmile-status-pending">Oczekująca</span>',
                    'approved' => '<span class="flexmile-status-badge flexmile-status-approved">Zatwierdzona</span>',
                    'rejected' => '<span class="flexmile-status-badge flexmile-status-rejected">Odrzucona</span>',
                ];

                $company_name = get_post_meta($reservation->ID, '_company_name', true);
                $first_name = get_post_meta($reservation->ID, '_first_name', true);
                $last_name = get_post_meta($reservation->ID, '_last_name', true);
                $client_name = !empty($company_name) ? $company_name : trim($first_name . ' ' . $last_name);
                
                $offer_id = get_post_meta($reservation->ID, '_offer_id', true);
                $car_name = '';
                if ($offer_id) {
                    $car = get_post($offer_id);
                    if ($car) {
                        $car_name = $car->post_title;
                    }
                }

                $total_price = get_post_meta($reservation->ID, '_total_price', true);
                $price_display = $total_price ? number_format($total_price, 0, ',', ' ') . ' zł' : 'Brak ceny';
                ?>
                <li class="flexmile-recent-item">
                    <div class="flexmile-recent-header">
                        <strong><a href="<?php echo get_edit_post_link($reservation->ID); ?>"><?php echo esc_html($client_name ?: 'Brak nazwy'); ?></a></strong>
                        <?php echo $status_labels[$status]; ?>
                    </div>
                    <?php if ($car_name): ?>
                        <div class="flexmile-recent-meta">
                            <span class="dashicons dashicons-car"></span> <?php echo esc_html($car_name); ?>
                        </div>
                    <?php endif; ?>
                    <div class="flexmile-recent-meta">
                        <span class="dashicons dashicons-money-alt"></span> <?php echo esc_html($price_display); ?>
                        <span class="flexmile-recent-date"><?php echo human_time_diff(strtotime($reservation->post_date), current_time('timestamp')) . ' temu'; ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p><a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>" class="button">Zobacz wszystkie rezerwacje →</a></p>
        <?php
    }

    /**
     * Renderuje widget ogólnego przeglądu
     */
    public function render_overview_widget() {
        $total_offers = wp_count_posts('offer');
        $total_orders = wp_count_posts('order');
        $total_reservations = wp_count_posts('reservation');

        $offers_count = $total_offers->publish ?? 0;
        $orders_count = $total_orders->publish ?? 0;
        $reservations_count = $total_reservations->publish ?? 0;

        // Policz aktywne zamówienia i rezerwacje
        $orders = get_posts([
            'post_type' => 'order',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_status',
                    'value' => 'approved',
                    'compare' => '=',
                ],
            ],
        ]);
        $active_orders_count = count($orders);

        $reservations = get_posts([
            'post_type' => 'reservation',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_status',
                    'value' => 'approved',
                    'compare' => '=',
                ],
            ],
        ]);
        $active_reservations_count = count($reservations);

        ?>
        <div class="flexmile-overview">
            <div class="flexmile-overview-stats">
                <div class="flexmile-overview-stat">
                    <div class="flexmile-overview-stat-content">
                        <div class="flexmile-overview-stat-value"><?php echo esc_html($offers_count); ?></div>
                        <div class="flexmile-overview-stat-label">Ofert</div>
                    </div>
                    <div class="flexmile-overview-stat-action">
                        <a href="<?php echo admin_url('edit.php?post_type=offer'); ?>">Zobacz →</a>
                    </div>
                </div>
                <div class="flexmile-overview-stat">
                    <div class="flexmile-overview-stat-content">
                        <div class="flexmile-overview-stat-value"><?php echo esc_html($orders_count); ?></div>
                        <div class="flexmile-overview-stat-label">Zamówień (<?php echo esc_html($active_orders_count); ?> aktywnych)</div>
                    </div>
                    <div class="flexmile-overview-stat-action">
                        <a href="<?php echo admin_url('edit.php?post_type=order'); ?>">Zobacz →</a>
                    </div>
                </div>
                <div class="flexmile-overview-stat">
                    <div class="flexmile-overview-stat-content">
                        <div class="flexmile-overview-stat-value"><?php echo esc_html($reservations_count); ?></div>
                        <div class="flexmile-overview-stat-label">Rezerwacji (<?php echo esc_html($active_reservations_count); ?> aktywnych)</div>
                    </div>
                    <div class="flexmile-overview-stat-action">
                        <a href="<?php echo admin_url('edit.php?post_type=reservation'); ?>">Zobacz →</a>
                    </div>
                </div>
            </div>
            <div class="flexmile-overview-actions">
                <a href="<?php echo admin_url('admin.php?page=flexmile'); ?>" class="button button-primary">Panel FlexMile</a>
                <a href="<?php echo admin_url('post-new.php?post_type=offer'); ?>" class="button">Dodaj ofertę</a>
            </div>
        </div>
        <?php
    }

    /**
     * Dodaje style CSS dla dashboardu
     */
    public function add_dashboard_styles() {
        ?>
        <style>
            .flexmile-dashboard-stats {
                padding: 5px 0;
            }

            .flexmile-stat-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }

            .flexmile-stat-item {
                text-align: center;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 6px;
                border: 1px solid #e0e0e0;
            }

            .flexmile-stat-label {
                font-size: 11px;
                text-transform: uppercase;
                color: #666;
                margin-bottom: 5px;
                font-weight: 500;
            }

            .flexmile-stat-value {
                font-size: 24px;
                font-weight: bold;
                line-height: 1.2;
            }

            .flexmile-stat-total {
                color: #2271b1;
            }

            .flexmile-stat-pending {
                color: #f0a238;
            }

            .flexmile-stat-approved {
                color: #00a32a;
            }

            .flexmile-stat-rejected {
                color: #d63638;
            }

            .flexmile-stat-period {
                display: flex;
                justify-content: space-around;
                padding: 10px 0;
                border-top: 1px solid #e0e0e0;
                border-bottom: 1px solid #e0e0e0;
                margin-bottom: 15px;
            }

            .flexmile-stat-period-item {
                text-align: center;
            }

            .flexmile-stat-period-label {
                display: block;
                font-size: 11px;
                color: #666;
                margin-bottom: 3px;
            }

            .flexmile-stat-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .flexmile-stat-actions .button {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }

            .flexmile-pending-badge {
                display: inline-block;
                padding: 6px 12px;
                background: #fff3cd;
                color: #856404;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                margin-left: 8px;
                border: 1px solid #ffc107;
            }

            .flexmile-recent-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .flexmile-recent-item {
                padding: 12px 0;
                border-bottom: 1px solid #e0e0e0;
            }

            .flexmile-recent-item:last-child {
                border-bottom: none;
            }

            .flexmile-recent-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
            }

            .flexmile-recent-header strong {
                font-size: 13px;
            }

            .flexmile-recent-header a {
                text-decoration: none;
            }

            .flexmile-recent-header a:hover {
                text-decoration: underline;
            }

            .flexmile-recent-meta {
                font-size: 12px;
                color: #666;
                margin: 4px 0;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .flexmile-recent-meta .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            .flexmile-recent-date {
                margin-left: auto;
                color: #999;
            }

            .flexmile-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .flexmile-status-pending {
                background: #fff3cd;
                color: #856404;
            }

            .flexmile-status-approved {
                background: #d1fae5;
                color: #065f46;
            }

            .flexmile-status-rejected {
                background: #fee2e2;
                color: #991b1b;
            }

            .flexmile-overview {
                padding: 5px 0;
            }

            .flexmile-overview-stats {
                margin-bottom: 20px;
            }

            .flexmile-overview-stat {
                display: flex;
                align-items: center;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 6px;
                margin-bottom: 10px;
                border: 1px solid #e0e0e0;
            }

            .flexmile-overview-stat-content {
                flex: 1;
            }

            .flexmile-overview-stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
                line-height: 1.2;
            }

            .flexmile-overview-stat-label {
                font-size: 12px;
                color: #666;
                margin-top: 2px;
            }

            .flexmile-overview-stat-action {
                margin-left: 10px;
            }

            .flexmile-overview-stat-action a {
                text-decoration: none;
                color: #2271b1;
                font-size: 12px;
                font-weight: 500;
            }

            .flexmile-overview-stat-action a:hover {
                text-decoration: underline;
            }

            .flexmile-overview-actions {
                display: flex;
                gap: 8px;
                padding-top: 15px;
                border-top: 1px solid #e0e0e0;
            }

            .flexmile-overview-actions .button {
                flex: 1;
                text-align: center;
            }
        </style>
        <?php
    }
}

