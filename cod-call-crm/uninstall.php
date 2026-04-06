<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$orders = $wpdb->prefix . 'cod_call_orders';
$logs = $wpdb->prefix . 'cod_call_logs';

$wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($orders) . '`');
$wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($logs) . '`');

$keys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('cod_crm_') . '%'
    )
);

if (!empty($keys)) {
    foreach ($keys as $key) {
        delete_option(sanitize_key($key));
    }
}

remove_role('cod_agent');
