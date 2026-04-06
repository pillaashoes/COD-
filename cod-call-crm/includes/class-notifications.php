<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Notifications {
    public static function notify_admin(string $type, array $order): void {
        $enabled = [
            'confirmed' => (bool) get_option('cod_crm_notify_admin_confirmed', 0),
            'cancelled' => (bool) get_option('cod_crm_notify_admin_cancelled', 0),
            'max_attempts' => (bool) get_option('cod_crm_notify_admin_max_attempts', 0),
        ];

        if (empty($enabled[$type])) {
            return;
        }

        $to = get_option('admin_email');
        $subject = sprintf('[COD CRM] %s: Order #%s', ucfirst($type), $order['order_id']);
        $body = sprintf(
            "Order: %s\nCustomer: %s\nPhone: %s\nAmount: %s",
            $order['order_id'],
            $order['customer_name'],
            $order['customer_phone'],
            $order['order_amount']
        );
        wp_mail($to, $subject, $body);
    }

    public static function notify_agent_new_order(int $agent_id, array $order): void {
        if (!$agent_id) {
            return;
        }
        $agent = get_user_by('id', $agent_id);
        if (!$agent) {
            return;
        }

        if ((bool) get_option('cod_crm_email_on_assignment', 1) && (bool) get_option('cod_crm_notify_agent_new_order', 1)) {
            $subject = sprintf('[COD CRM] New Assigned Order #%s', $order['order_id']);
            $body = sprintf(
                "Hi %s,\nYou have a new order assigned.\nOrder: %s\nCustomer: %s\nPhone: %s\nAmount: %s",
                $agent->display_name,
                $order['order_id'],
                $order['customer_name'],
                $order['customer_phone'],
                $order['order_amount']
            );
            wp_mail($agent->user_email, $subject, $body);
        }

        if ((bool) get_option('cod_crm_whatsapp_on_assignment', 0)) {
            $url = esc_url_raw((string) get_option('cod_crm_whatsapp_webhook_url', ''));
            if (!empty($url)) {
                wp_remote_post($url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'timeout' => 10,
                    'body' => wp_json_encode([
                        'agent_name' => $agent->display_name,
                        'order_id' => $order['order_id'],
                        'customer_name' => $order['customer_name'],
                        'phone' => $order['customer_phone'],
                        'amount' => $order['order_amount'],
                    ]),
                ]);
            }
        }
    }
}
