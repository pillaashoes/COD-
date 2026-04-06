<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Sync_Logger {
    public static function log(string $order_id, string $sync_type, string $status, string $message): void {
        $log = json_decode((string) get_option('cod_crm_sync_log', '[]'), true);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'order_id' => sanitize_text_field($order_id),
            'sync_type' => sanitize_text_field($sync_type),
            'timestamp' => current_time('mysql'),
            'status' => sanitize_text_field($status),
            'message' => sanitize_text_field($message),
        ];
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        update_option('cod_crm_sync_log', wp_json_encode($log));
    }

    public static function clear(): void {
        update_option('cod_crm_sync_log', wp_json_encode([]));
    }

    public static function get_entries(): array {
        $log = json_decode((string) get_option('cod_crm_sync_log', '[]'), true);
        return is_array($log) ? array_reverse($log) : [];
    }
}
