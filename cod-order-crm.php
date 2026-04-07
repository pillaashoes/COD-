<?php
/**
 * Plugin Name: COD Order CRM for WooCommerce
 * Description: CRM dashboard for COD confirmation calls with inline updates, follow-ups, filtering, and CSV exports.
 * Version: 1.0.0
 * Author: COD Team
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: cod-order-crm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'COD_ORDER_CRM_VERSION' ) ) {
	define( 'COD_ORDER_CRM_VERSION', '1.0.0' );
}

if ( ! defined( 'COD_ORDER_CRM_FILE' ) ) {
	define( 'COD_ORDER_CRM_FILE', __FILE__ );
}

if ( ! defined( 'COD_ORDER_CRM_PATH' ) ) {
	define( 'COD_ORDER_CRM_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'COD_ORDER_CRM_URL' ) ) {
	define( 'COD_ORDER_CRM_URL', plugin_dir_url( __FILE__ ) );
}

require_once COD_ORDER_CRM_PATH . 'includes/class-cod-order-crm-capabilities.php';
require_once COD_ORDER_CRM_PATH . 'includes/class-cod-order-crm-meta.php';
require_once COD_ORDER_CRM_PATH . 'includes/class-cod-order-crm-repository.php';
require_once COD_ORDER_CRM_PATH . 'includes/class-cod-order-crm-exporter.php';
require_once COD_ORDER_CRM_PATH . 'admin/class-cod-order-crm-admin.php';

final class COD_Order_CRM_Plugin {

	/**
	 * Boot plugin.
	 */
	public static function init() {
		COD_Order_CRM_Capabilities::init();
		COD_Order_CRM_Meta::init();
		COD_Order_CRM_Admin::init();
		COD_Order_CRM_Exporter::init();
	}
}

add_action( 'plugins_loaded', array( 'COD_Order_CRM_Plugin', 'init' ) );
