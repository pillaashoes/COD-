<?php
/**
 * Data access for CRM orders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class COD_Order_CRM_Repository {

	/**
	 * Fetch orders for CRM grid.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array<string,mixed>
	 */
	public static function get_orders( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'page'                => 1,
			'per_page'            => 50,
			'search'              => '',
			'call_status'         => '',
			'confirmation_status' => '',
			'city'                => '',
			'date_from'           => '',
			'date_to'             => '',
			'high_value'          => 0,
			'due_followup'        => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$postmeta = $wpdb->postmeta;
		$posts    = $wpdb->posts;

		$sql = "
			SELECT SQL_CALC_FOUND_ROWS p.ID
			FROM {$posts} p
			LEFT JOIN {$postmeta} pm_phone ON pm_phone.post_id = p.ID AND pm_phone.meta_key = '_billing_phone'
			LEFT JOIN {$postmeta} pm_city ON pm_city.post_id = p.ID AND pm_city.meta_key = '_billing_city'
			LEFT JOIN {$postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_order_total'
			LEFT JOIN {$postmeta} pm_call_status ON pm_call_status.post_id = p.ID AND pm_call_status.meta_key = 'call_status'
			LEFT JOIN {$postmeta} pm_confirmation_status ON pm_confirmation_status.post_id = p.ID AND pm_confirmation_status.meta_key = 'confirmation_status'
			LEFT JOIN {$postmeta} pm_follow_up_date ON pm_follow_up_date.post_id = p.ID AND pm_follow_up_date.meta_key = 'follow_up_date'
			WHERE p.post_type = 'shop_order'
			AND p.post_status IN ('wc-pending','wc-processing','wc-on-hold','wc-completed','wc-cancelled')
		";

		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$search   = trim( (string) $args['search'] );
			$order_id = absint( $search );
			if ( $order_id > 0 ) {
				$sql      .= ' AND (p.ID = %d OR pm_phone.meta_value LIKE %s)';
				$params[] = $order_id;
				$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			} else {
				$sql      .= ' AND pm_phone.meta_value LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			}
		}

		if ( ! empty( $args['call_status'] ) ) {
			$sql      .= ' AND pm_call_status.meta_value = %s';
			$params[] = sanitize_text_field( $args['call_status'] );
		}

		if ( ! empty( $args['confirmation_status'] ) ) {
			$sql      .= ' AND pm_confirmation_status.meta_value = %s';
			$params[] = sanitize_text_field( $args['confirmation_status'] );
		}

		if ( ! empty( $args['city'] ) ) {
			$sql      .= ' AND pm_city.meta_value = %s';
			$params[] = sanitize_text_field( $args['city'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$sql      .= ' AND p.post_date >= %s';
			$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$sql      .= ' AND p.post_date <= %s';
			$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		if ( ! empty( $args['high_value'] ) ) {
			$sql .= ' AND CAST(pm_total.meta_value AS DECIMAL(10,2)) > 2000';
		}

		if ( ! empty( $args['due_followup'] ) ) {
			$today    = wp_date( 'Y-m-d' );
			$sql      .= ' AND pm_follow_up_date.meta_value <> "" AND DATE(pm_follow_up_date.meta_value) = %s';
			$params[] = $today;
		}

		$sql .= ' GROUP BY p.ID ORDER BY p.post_date DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		$query = ! empty( $params ) ? $wpdb->prepare( $sql, $params ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids   = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$rows = array();
		foreach ( $ids as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order ) {
				continue;
			}
			$rows[] = self::map_order( $order );
		}

		return array(
			'rows'      => $rows,
			'total'     => $total,
			'page'      => $page,
			'per_page'  => $per_page,
			'totalPage' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Map order into CRM payload.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string,mixed>
	 */
	public static function map_order( $order ) {
		$order_id = $order->get_id();
		$items    = $order->get_items();

		$product_names = array();
		foreach ( $items as $item ) {
			$product_names[] = $item->get_name();
		}

		$phone = (string) $order->get_billing_phone();
		$city  = (string) $order->get_billing_city();

		$duplicate_count = self::get_duplicate_phone_count( $phone, $order_id );

		return array(
			'order_id'             => $order_id,
			'customer_name'        => trim( $order->get_formatted_billing_full_name() ),
			'phone'                => $phone,
			'city'                 => $city,
			'product_name'         => implode( ', ', $product_names ),
			'order_value'          => wc_format_decimal( $order->get_total(), 2 ),
			'call_status'          => COD_Order_CRM_Meta::get_value( $order_id, 'call_status' ),
			'confirmation_status'  => COD_Order_CRM_Meta::get_value( $order_id, 'confirmation_status' ),
			'cancellation_reason'  => COD_Order_CRM_Meta::get_value( $order_id, 'cancellation_reason' ),
			'call_attempts'        => (int) COD_Order_CRM_Meta::get_value( $order_id, 'call_attempts' ),
			'last_call_time'       => COD_Order_CRM_Meta::get_value( $order_id, 'last_call_time' ),
			'follow_up_date'       => COD_Order_CRM_Meta::get_value( $order_id, 'follow_up_date' ),
			'call_notes'           => COD_Order_CRM_Meta::get_value( $order_id, 'call_notes' ),
			'order_priority'       => COD_Order_CRM_Meta::get_value( $order_id, 'order_priority' ),
			'order_date'           => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : '',
			'duplicate_phone_flag' => $duplicate_count > 0,
			'duplicate_phone_count'=> $duplicate_count,
		);
	}

	/**
	 * Get duplicate phone order count.
	 *
	 * @param string $phone Billing phone.
	 * @param int    $exclude_order_id Order to exclude.
	 * @return int
	 */
	protected static function get_duplicate_phone_count( $phone, $exclude_order_id ) {
		global $wpdb;

		if ( '' === trim( $phone ) ) {
			return 0;
		}

		$sql = "
			SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = 'shop_order'
			AND p.ID <> %d
			AND pm.meta_key = '_billing_phone'
			AND pm.meta_value = %s
		";

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $exclude_order_id, $phone ) );
	}

	/**
	 * Fetch distinct cities for filters.
	 *
	 * @return string[]
	 */
	public static function get_distinct_cities() {
		global $wpdb;
		$sql = "
			SELECT DISTINCT meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_billing_city'
			AND meta_value <> ''
			ORDER BY meta_value ASC
		";
		return $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
