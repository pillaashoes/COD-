<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Woo_Columns {
    public static function init(): void {
        add_filter('manage_woocommerce_page_wc-orders_columns', [__CLASS__, 'add_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [__CLASS__, 'render_hpos_column'], 10, 2);

        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'add_column']);
        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'render_legacy_column'], 10, 2);

        add_action('add_meta_boxes', [__CLASS__, 'register_meta_box']);
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'render_inline_panel']);
    }

    public static function add_column(array $columns): array {
        $columns['cod_crm_status'] = __('CRM Status', 'cod-call-crm');
        return $columns;
    }

    public static function render_hpos_column(string $column, WC_Order $order): void {
        if ($column === 'cod_crm_status') {
            self::render_status_badge((string)$order->get_id());
        }
    }

    public static function render_legacy_column(string $column, int $post_id): void {
        if ($column === 'cod_crm_status') {
            self::render_status_badge((string)$post_id);
        }
    }

    private static function render_status_badge(string $order_id): void {
        $order = COD_CRM_DB::get_order($order_id);
        $status = $order['order_status'] ?? 'not_in_crm';
        $map = [
            'confirmed' => ['Confirmed', '#00a32a'],
            'cancelled' => ['Cancelled', '#d63638'],
            'pending_call' => ['Pending Call', '#dba617'],
            'callback_scheduled' => ['Callback', '#0073aa'],
            'prepaid_converted' => ['Prepaid', '#9b59b6'],
            'not_in_crm' => ['Not in CRM', '#9da0a5'],
        ];
        $label = $map[$status][0] ?? ucfirst(str_replace('_', ' ', $status));
        $color = $map[$status][1] ?? '#9da0a5';

        $url = admin_url('admin.php?page=cod-crm-call-queue&search=' . rawurlencode($order_id));
        echo '<a href="' . esc_url($url) . '"><span class="cod-crm-status-badge" style="background:' . esc_attr($color) . ';">' . esc_html($label) . '</span></a>';
    }

    public static function register_meta_box(): void {
        add_meta_box(
            'cod-crm-status-box',
            __('COD CRM Status', 'cod-call-crm'),
            [__CLASS__, 'render_meta_box'],
            'shop_order',
            'side',
            'high'
        );

        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            add_meta_box(
                'cod-crm-status-box-hpos',
                __('COD CRM Status', 'cod-call-crm'),
                [__CLASS__, 'render_meta_box'],
                wc_get_page_screen_id('shop-order'),
                'side',
                'high'
            );
        }
    }

    public static function render_meta_box($post_or_order): void {
        $order_id = is_a($post_or_order, 'WC_Order') ? $post_or_order->get_id() : (is_object($post_or_order) ? $post_or_order->ID : 0);
        if (!$order_id) {
            return;
        }
        $crm = COD_CRM_DB::get_order((string)$order_id);
        $last_log = COD_CRM_DB::get_last_log_for_order((string)$order_id);
        echo '<div class="cod-crm-meta-box">';
        if ($crm) {
            echo '<p><strong>Status:</strong> ' . esc_html($crm['order_status']) . '</p>';
            echo '<p><strong>Attempts:</strong> ' . esc_html((string)$crm['total_attempts']) . '</p>';
        } else {
            echo '<p><strong>Status:</strong> Not in CRM</p>';
        }
        if ($last_log) {
            $agent = get_user_by('id', intval($last_log['agent_id']));
            echo '<p><strong>Last Outcome:</strong> ' . esc_html($last_log['call_outcome']) . '</p>';
            echo '<p><strong>Agent:</strong> ' . esc_html($agent ? $agent->display_name : 'System') . '</p>';
            echo '<p><strong>At:</strong> ' . esc_html($last_log['called_at']) . '</p>';
            if (!empty($last_log['next_callback_at'])) {
                echo '<p><strong>Next callback:</strong> ' . esc_html($last_log['next_callback_at']) . '</p>';
            }
        }
        $logs_url = admin_url('admin.php?page=cod-crm-call-logs&search=' . rawurlencode((string)$order_id));
        echo '<p><a class="button" href="' . esc_url($logs_url) . '">View All Call Logs</a></p>';
        if (!$crm) {
            echo '<p><button class="button button-primary cod-crm-add-order" data-order-id="' . esc_attr((string)$order_id) . '">Add to CRM Queue</button></p>';
        }
        echo '</div>';
        wp_nonce_field('cod_crm_nonce', 'cod_crm_meta_nonce');
    }

    public static function render_inline_panel($order): void {
        self::render_meta_box($order);
    }
}
