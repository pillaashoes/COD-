<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Woo_Sync {
    public static function init(): void {
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'handle_checkout_order'], 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'handle_store_api_order'], 20, 1);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_status_changed'], 20, 4);
    }

    public static function handle_checkout_order($order_id): void {
        if (!(bool) get_option('cod_crm_realtime_sync_enabled', 1)) {
            return;
        }
        $order = wc_get_order($order_id);
        if ($order) {
            self::sync_wc_order($order, 'realtime');
        }
    }

    public static function handle_store_api_order($order): void {
        if (!(bool) get_option('cod_crm_realtime_sync_enabled', 1)) {
            return;
        }
        if ($order instanceof WC_Order) {
            self::sync_wc_order($order, 'realtime');
        }
    }

    public static function sync_wc_order(WC_Order $order, string $sync_type = 'realtime'): bool {
        $payment = $order->get_payment_method();
        if (!self::can_sync_payment($payment)) {
            COD_CRM_Sync_Logger::log((string)$order->get_id(), $sync_type, 'skipped', 'Payment method disabled by settings.');
            return false;
        }

        $order_id = (string) $order->get_id();
        if (COD_CRM_DB::order_exists($order_id)) {
            COD_CRM_Sync_Logger::log($order_id, $sync_type, 'skipped', 'Duplicate order.');
            return false;
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
            $items[] = ['name' => $item->get_name(), 'qty' => $item->get_quantity()];
        }

        $agent_id = self::assign_agent();

        $insert = COD_CRM_DB::insert_order([
            'order_id' => $order_id,
            'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'customer_phone' => $order->get_billing_phone(),
            'customer_address' => $full_address,
            'order_amount' => $order->get_total(),
            'order_items' => $items,
            'payment_method' => $payment,
            'wc_order_status' => $order->get_status(),
            'order_status' => 'pending_call',
            'assigned_agent_id' => $agent_id,
            'source' => 'woocommerce',
            'crm_synced_at' => current_time('mysql'),
        ]);

        if (!$insert) {
            COD_CRM_Sync_Logger::log($order_id, $sync_type, 'error', 'Insert failed.');
            return false;
        }

        $order->update_meta_data('_cod_crm_synced', 1);
        $order->save();

        COD_CRM_Sync_Logger::log($order_id, $sync_type, 'success', 'Order synced to CRM.');
        COD_CRM_Notifications::notify_agent_new_order($agent_id, COD_CRM_DB::get_order($order_id) ?: []);
        return true;
    }

    private static function can_sync_payment(string $payment): bool {
        if ($payment === 'cod') {
            return (bool) get_option('cod_crm_sync_cod', 1);
        }
        $prepaid = ['razorpay', 'stripe', 'paypal', 'payu'];
        if (in_array($payment, $prepaid, true)) {
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
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE assigned_agent_id=%d AND order_status IN ('pending_call','callback_scheduled')", $agent->ID));
            if ($count < $fewest) {
                $fewest = $count;
                $best_id = intval($agent->ID);
            }
        }

        update_option('cod_crm_last_agent_index', $best_id ?: 0);
        return $best_id;
    }

    public static function handle_status_changed($order_id, $old_status, $new_status, $order): void {
        $crm = COD_CRM_DB::get_order((string)$order_id);
        if (!$crm) {
            return;
        }

        if ($new_status === 'cancelled') {
            COD_CRM_DB::update_order((string)$order_id, ['order_status' => 'cancelled', 'wc_order_status' => $new_status]);
            COD_CRM_DB::insert_log([
                'order_id' => $crm['order_id'],
                'customer_name' => $crm['customer_name'],
                'customer_phone' => $crm['customer_phone'],
                'customer_address' => $crm['customer_address'],
                'order_amount' => $crm['order_amount'],
                'order_items' => json_decode((string)$crm['order_items'], true),
                'call_outcome' => 'cancelled',
                'call_reason' => 'Cancelled from WooCommerce',
                'agent_id' => 0,
                'attempt_number' => intval($crm['total_attempts']) + 1,
                'called_at' => current_time('mysql'),
            ]);
        }

        if ($new_status === 'completed') {
            COD_CRM_DB::update_order((string)$order_id, ['order_status' => 'confirmed', 'wc_order_status' => $new_status]);
        }

        COD_CRM_Sync_Logger::log((string)$order_id, 'status_change', 'success', "Status changed: {$old_status} -> {$new_status}");
    }
}
