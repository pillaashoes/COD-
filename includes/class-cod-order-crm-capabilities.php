<?php
/**
 * Capabilities manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class COD_Order_CRM_Capabilities {
	const CAPABILITY = 'manage_cod_order_crm';

	/**
	 * Hooks.
	 */
	public static function init() {
		register_activation_hook( COD_ORDER_CRM_FILE, array( __CLASS__, 'activate' ) );
		add_action( 'init', array( __CLASS__, 'ensure_caps' ) );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::ensure_caps();
	}

	/**
	 * Grant CRM access.
	 */
	public static function ensure_caps() {
		$roles = array( 'administrator', 'shop_manager' );
		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}
}
