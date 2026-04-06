<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Activator {
    public static function activate(): void {
        self::create_tables();
        self::create_role();
        self::set_defaults();
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $orders = $wpdb->prefix . 'cod_call_orders';
        $logs = $wpdb->prefix . 'cod_call_logs';

        $sql1 = "CREATE TABLE {$orders} (
            id INT NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(50) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_address TEXT NULL,
            order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            order_items LONGTEXT NULL,
            payment_method VARCHAR(50) NOT NULL,
            wc_order_status VARCHAR(50) NULL,
            order_status ENUM('pending_call','confirmed','cancelled','callback_scheduled','prepaid_converted','max_attempts_reached') NOT NULL DEFAULT 'pending_call',
            total_attempts TINYINT NOT NULL DEFAULT 0,
            max_attempts TINYINT NOT NULL DEFAULT 3,
            assigned_agent_id INT NULL,
            source ENUM('woocommerce','manual','csv_import') NOT NULL DEFAULT 'manual',
            crm_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order_id (order_id),
            KEY idx_agent (assigned_agent_id),
            KEY idx_status (order_status)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$logs} (
            id INT NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(50) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_address TEXT NULL,
            order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            order_items LONGTEXT NULL,
            call_outcome ENUM('confirmed','cancelled','no_answer','wrong_number','call_not_picked','busy','switched_off','callback_requested','prepaid_converted') NOT NULL,
            call_reason TEXT NULL,
            call_duration_seconds INT NULL,
            agent_id INT NOT NULL DEFAULT 0,
            attempt_number TINYINT NOT NULL DEFAULT 1,
            next_callback_at DATETIME NULL,
            called_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_agent (agent_id),
            KEY idx_outcome (call_outcome),
            KEY idx_called_at (called_at)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
    }

    private static function create_role(): void {
        add_role('cod_agent', __('COD Agent', 'cod-call-crm'), ['read' => true]);
    }

    private static function set_defaults(): void {
        $defaults = [
            'cod_crm_version' => COD_CRM_VERSION,
            'cod_crm_brand_name' => get_bloginfo('name'),
            'cod_crm_whatsapp_support_number' => '',
            'cod_crm_max_attempts' => 3,
            'cod_crm_default_assignment' => 'round_robin',
            'cod_crm_script' => self::default_script(),
            'cod_crm_notify_admin_confirmed' => 0,
            'cod_crm_notify_admin_cancelled' => 0,
            'cod_crm_notify_admin_max_attempts' => 0,
            'cod_crm_notify_agent_new_order' => 1,
            'cod_crm_whatsapp_webhook_url' => '',
            'cod_crm_realtime_sync_enabled' => 1,
            'cod_crm_sync_cod' => 1,
            'cod_crm_sync_prepaid' => 1,
            'cod_crm_sync_other' => 0,
            'cod_crm_auto_round_robin' => 1,
            'cod_crm_email_on_assignment' => 1,
            'cod_crm_whatsapp_on_assignment' => 0,
            'cod_crm_sync_log' => wp_json_encode([]),
            'cod_crm_last_bulk_sync' => '',
            'cod_crm_last_agent_index' => 0,
        ];

        foreach ($defaults as $k => $v) {
            if (get_option($k, null) === null) {
                add_option($k, $v);
            }
        }
    }

    private static function default_script(): string {
        return "STEP 1 — Opening:\nHello, main baat kar raha/rahi hoon [Brand Name] ki taraf se.\nKya aap {customer_name} ji baat kar rahe hain?\n\nSTEP 2 — Order Confirm:\nAapne order kiya hai {product_name}. Total amount hai\n₹{amount}, Cash on Delivery ke saath. Kya yeh sahi hai?\n\nSTEP 3 — Address Verify:\nAapki delivery address hai {delivery_address}. Confirm hai?\n\nSTEP 4 — Delivery Info:\nAapka order 3-5 business days mein deliver ho jayega.\nDelivery ke waqt ₹{amount} cash ready rakhein.\n\nSTEP 5 — Closing:\nAapka order confirm ho gaya! Order ID hai {order_id}.\nDhanyavaad aur have a great day!";
    }
}
