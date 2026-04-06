<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Woo_Sync {
    public static function init(): void {
        // Critical: register all order creation hooks.
        add_action('woocommerce_new_order', 'cod_crm_sync_order', 10, 1);
        add_action('woocommerce_checkout_order_processed', 'cod_crm_sync_order', 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', 'cod_crm_sync_order', 10, 1);

        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_status_changed'], 20, 4);
    }
    private static function debug_log(string $message): void {
        if ((bool) get_option('cod_crm_debug_mode', 1)) {
            error_log($message);
        }
    }


    public static function cod_crm_sync_order($order_input): bool {
        global $wpdb;
        $orders_table = COD_CRM_DB::orders_table();

        $order_id = 0;
        if ($order_input instanceof WC_Order) {
            $order_id = (int) $order_input->get_id();
        } else {
            $order_id = intval($order_input);
        }

        self::debug_log('COD CRM Sync Triggered: ' . $order_id);

        if ($order_id <= 0) {
            self::debug_log('COD CRM Sync Skipped: invalid order_id');
            return false;
        }

        if (!(bool) get_option('cod_crm_realtime_sync_enabled', 1)) {
            self::debug_log('COD CRM Sync Skipped: realtime sync disabled for order ' . $order_id);
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            self::debug_log('COD CRM Sync Failed: wc_get_order returned null for order ' . $order_id);
            return false;
        }

        $payment_method = (string) $order->get_payment_method();
        self::debug_log('COD CRM Payment Method: ' . $payment_method . ' for order ' . $order_id);

        if (!self::can_sync_payment($payment_method)) {
            COD_CRM_Sync_Logger::log((string) $order_id, 'realtime', 'skipped', 'Payment method disabled by settings.');
            self::debug_log('COD CRM Sync Skipped: payment filtered for order ' . $order_id);
            return false;
        }

        $dup_count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE order_id = %s", (string) $order_id)
        );
        if ($dup_count > 0) {
            COD_CRM_Sync_Logger::log((string) $order_id, 'realtime', 'skipped', 'Duplicate order detected.');
            self::debug_log('COD CRM Duplicate Order: ' . $order_id);
            return false;
        }

        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($customer_name === '') {
            $customer_name = trim((string) $order->get_formatted_billing_full_name());
        }
        if ($customer_name === '') {
            $customer_name = __('Guest', 'cod-call-crm');
        }

        $full_address = trim(implode(', ', array_filter([
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
        ])));

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => sanitize_text_field((string) $item->get_name()),
                'qty' => intval($item->get_quantity()),
            ];
        }

        $agent_id = self::assign_agent();

        $insert_data = [
            'order_id' => (string) $order_id,
            'customer_name' => sanitize_text_field($customer_name),
            'customer_phone' => sanitize_text_field((string) $order->get_billing_phone()),
            'customer_address' => wp_kses_post($full_address),
            'order_amount' => floatval($order->get_total()),
            'order_items' => wp_json_encode($items),
            'payment_method' => sanitize_text_field($payment_method),
            'wc_order_status' => sanitize_text_field((string) $order->get_status()),
            'order_status' => 'pending_call',
            'total_attempts' => 0,
            'max_attempts' => intval(get_option('cod_crm_max_attempts', 3)),
            'assigned_agent_id' => $agent_id,
            'source' => 'woocommerce',
            'crm_synced_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert(
            $orders_table,
            $insert_data,
            ['%s','%s','%s','%s','%f','%s','%s','%s','%s','%d','%d','%d','%s','%s']
        );

        if ($inserted === false) {
            self::debug_log('COD CRM Insert Failed: ' . $wpdb->last_error);
            COD_CRM_Sync_Logger::log((string) $order_id, 'realtime', 'error', 'Insert failed: ' . $wpdb->last_error);
            return false;
        }

        $order->update_meta_data('_cod_crm_synced', 1);
        $order->save();

        self::debug_log('COD CRM Insert Success: ' . $order_id);
        COD_CRM_Sync_Logger::log((string) $order_id, 'realtime', 'success', 'Order synced to CRM.');

        $fresh = COD_CRM_DB::get_order((string) $order_id);
        if ($fresh) {
            COD_CRM_Notifications::notify_agent_new_order(intval($agent_id), $fresh);
        }

        return true;
    }

    public static function sync_wc_order(WC_Order $order, string $sync_type = 'realtime'): bool {
        // Keep compatibility with bulk/manual callers while centralizing logic.
        $ok = self::cod_crm_sync_order((int) $order->get_id());
        COD_CRM_Sync_Logger::log((string) $order->get_id(), $sync_type, $ok ? 'success' : 'skipped', $ok ? 'Synced via centralized function.' : 'Skipped/failed via centralized function.');
        return $ok;
    }

    private static function can_sync_payment(string $payment): bool {
        // Simulated/fallback setting behavior included by defaults in activator.
        if ($payment === 'cod') {
            return (bool) get_option('cod_crm_sync_cod', 1);
        }

        $prepaid_methods = ['razorpay', 'stripe', 'paypal', 'payu', 'ccavenue', 'paytm'];
        if (in_array($payment, $prepaid_methods, true)) {
            return (bool) get_option('cod_crm_sync_prepaid', 1);
        }

        return (bool) get_option('cod_crm_sync_other', 0);
    }

    public static function assign_agent(): ?int {
        if (!(bool) get_option('cod_crm_auto_round_robin', 1)) {
            return null;
        }

        global $wpdb;
        $table = COD_CRM_DB::orders_table();
        $agents = COD_CRM_DB::get_agents();
        if (empty($agents)) {
            return null;
        }

        $best_id = null;
        $fewest = PHP_INT_MAX;
        foreach ($agents as $agent) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE assigned_agent_id=%d AND order_status IN ('pending_call','callback_scheduled')",
                    $agent->ID
                )
            );
            if ($count < $fewest) {
                $fewest = $count;
                $best_id = intval($agent->ID);
            }
        }

        update_option('cod_crm_last_agent_index', $best_id ?: 0);
        return $best_id;
    }

    public static function handle_status_changed($order_id, $old_status, $new_status, $order): void {
        $crm = COD_CRM_DB::get_order((string) $order_id);
        if (!$crm) {
            return;
        }

        if ($new_status === 'cancelled') {
            COD_CRM_DB::update_order((string) $order_id, ['order_status' => 'cancelled', 'wc_order_status' => $new_status]);
            COD_CRM_DB::insert_log([
                'order_id' => $crm['order_id'],
                'customer_name' => $crm['customer_name'],
                'customer_phone' => $crm['customer_phone'],
                'customer_address' => $crm['customer_address'],
                'order_amount' => $crm['order_amount'],
                'order_items' => json_decode((string) $crm['order_items'], true),
                'call_outcome' => 'cancelled',
                'call_reason' => 'Cancelled from WooCommerce',
                'agent_id' => 0,
                'attempt_number' => intval($crm['total_attempts']) + 1,
                'called_at' => current_time('mysql'),
            ]);
        }

        if ($new_status === 'completed') {
            COD_CRM_DB::update_order((string) $order_id, ['order_status' => 'confirmed', 'wc_order_status' => $new_status]);
        }

        COD_CRM_Sync_Logger::log((string) $order_id, 'status_change', 'success', "Status changed: {$old_status} -> {$new_status}");
    }
}
