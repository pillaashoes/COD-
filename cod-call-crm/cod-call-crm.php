<?php
/**
 * Plugin Name: COD Call CRM
 * Plugin URI: https://example.com
 * Description: COD order call CRM for WooCommerce stores.
 * Version: 1.0.0
 * Author: COD CRM
 * Text Domain: cod-call-crm
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COD_CRM_VERSION', '1.0.0');
define('COD_CRM_FILE', __FILE__);
define('COD_CRM_PATH', plugin_dir_path(__FILE__));
define('COD_CRM_URL', plugin_dir_url(__FILE__));

require_once COD_CRM_PATH . 'includes/class-activator.php';
require_once COD_CRM_PATH . 'includes/class-deactivator.php';
require_once COD_CRM_PATH . 'includes/class-db.php';
require_once COD_CRM_PATH . 'includes/class-sync-logger.php';
require_once COD_CRM_PATH . 'includes/class-notifications.php';
require_once COD_CRM_PATH . 'includes/class-ajax.php';
require_once COD_CRM_PATH . 'includes/class-woo-sync.php';
require_once COD_CRM_PATH . 'includes/class-woo-columns.php';
require_once COD_CRM_PATH . 'admin/class-admin.php';

register_activation_hook(__FILE__, ['COD_CRM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['COD_CRM_Deactivator', 'deactivate']);

function cod_crm_woocommerce_active(): bool {
    if (class_exists('WooCommerce') || function_exists('wc_get_order')) {
        return true;
    }

    $plugins = (array) get_option('active_plugins', []);
    if (in_array('woocommerce/woocommerce.php', $plugins, true)) {
        return true;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', []);
        return isset($network_plugins['woocommerce/woocommerce.php']);
    }

    return false;
}

/**
 * Centralized WooCommerce sync function used by all creation hooks.
 *
 * @param int|WC_Order $order_id
 */
function cod_crm_sync_order($order_id): void {
    if (!cod_crm_woocommerce_active()) {
        error_log('COD CRM Sync Skipped: WooCommerce inactive while trigger fired.');
        return;
    }

    COD_CRM_Woo_Sync::cod_crm_sync_order($order_id);
}

function cod_crm_bootstrap(): void {
    load_plugin_textdomain('cod-call-crm', false, dirname(plugin_basename(__FILE__)) . '/languages');

    COD_CRM_DB::init();
    COD_CRM_Admin::init();
    COD_CRM_Ajax::init();

    if (cod_crm_woocommerce_active()) {
        COD_CRM_Woo_Sync::init();
        COD_CRM_Woo_Columns::init();
    }
}
add_action('plugins_loaded', 'cod_crm_bootstrap');
