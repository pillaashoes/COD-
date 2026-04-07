<?php
/**
 * Meta definitions and helper methods.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class COD_Order_CRM_Meta {

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register order meta keys.
	 */
	public static function register_meta() {
		$meta_args = array(
			'type'              => 'string',
			'show_in_rest'      => false,
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => array( __CLASS__, 'can_edit_order_meta' ),
		);

		foreach ( array_keys( self::fields() ) as $meta_key ) {
			register_post_meta( 'shop_order', $meta_key, $meta_args );
		}
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public static function can_edit_order_meta() {
		return current_user_can( COD_Order_CRM_Capabilities::CAPABILITY );
	}

	/**
	 * Enumerated field options.
	 *
	 * @return array<string, mixed>
	 */
	public static function fields() {
		return array(
			'call_status'         => array(
				'default' => 'not_called',
				'options' => array(
					'not_called'       => __( 'Not Called', 'cod-order-crm' ),
					'called_picked'    => __( 'Called - Picked', 'cod-order-crm' ),
					'called_not_picked'=> __( 'Called - Not Picked', 'cod-order-crm' ),
					'callback_needed'  => __( 'Callback Needed', 'cod-order-crm' ),
				),
			),
			'confirmation_status' => array(
				'default' => 'pending',
				'options' => array(
					'pending'       => __( 'Pending', 'cod-order-crm' ),
					'cod_confirmed' => __( 'COD Confirmed', 'cod-order-crm' ),
					'cancelled'     => __( 'Cancelled', 'cod-order-crm' ),
					'fake_order'    => __( 'Fake Order', 'cod-order-crm' ),
				),
			),
			'cancellation_reason' => array(
				'default' => '',
				'options' => array(
					''                => __( '—', 'cod-order-crm' ),
					'customer_denied' => __( 'Customer Denied', 'cod-order-crm' ),
					'wrong_number'    => __( 'Wrong Number', 'cod-order-crm' ),
					'duplicate_order' => __( 'Duplicate Order', 'cod-order-crm' ),
					'price_issue'     => __( 'Price Issue', 'cod-order-crm' ),
					'other'           => __( 'Other', 'cod-order-crm' ),
				),
			),
			'call_attempts'       => array(
				'default' => 0,
			),
			'last_call_time'      => array(
				'default' => '',
			),
			'call_notes'          => array(
				'default' => '',
			),
			'follow_up_date'      => array(
				'default' => '',
			),
			'order_priority'      => array(
				'default' => 'normal',
				'options' => array(
					'normal' => __( 'Normal', 'cod-order-crm' ),
					'high'   => __( 'High', 'cod-order-crm' ),
				),
			),
		);
	}

	/**
	 * Retrieve meta value with default.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public static function get_value( $order_id, $key ) {
		$fields  = self::fields();
		$default = isset( $fields[ $key ]['default'] ) ? $fields[ $key ]['default'] : '';
		$value   = get_post_meta( $order_id, $key, true );
		return '' === $value ? $default : $value;
	}
}
