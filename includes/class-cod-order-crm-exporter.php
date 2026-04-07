<?php
/**
 * CSV export endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class COD_Order_CRM_Exporter {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_cod_order_crm_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	/**
	 * Stream CSV for CRM data.
	 */
	public static function export_csv() {
		if ( ! current_user_can( COD_Order_CRM_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied', 'cod-order-crm' ) );
		}

		check_admin_referer( 'cod_order_crm_export' );

		$filters = array(
			'page'                => 1,
			'per_page'            => 200,
			'search'              => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'call_status'         => isset( $_GET['call_status'] ) ? sanitize_text_field( wp_unslash( $_GET['call_status'] ) ) : '',
			'confirmation_status' => isset( $_GET['confirmation_status'] ) ? sanitize_text_field( wp_unslash( $_GET['confirmation_status'] ) ) : '',
			'city'                => isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '',
			'date_from'           => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'             => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'high_value'          => ! empty( $_GET['high_value'] ) ? 1 : 0,
			'due_followup'        => ! empty( $_GET['due_followup'] ) ? 1 : 0,
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cod-order-crm-export-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv(
			$output,
			array(
				'Order ID',
				'Order Date',
				'Customer Name',
				'Phone',
				'City',
				'Product Name',
				'Order Value',
				'WooCommerce Status',
				'Call Status',
				'Call Attempts',
				'Last Call Time',
				'Confirmation Status',
				'Cancellation Reason',
				'Follow Up Date',
				'Priority',
				'Call Notes',
				'Duplicate Phone Flag',
			)
		);

		do {
			$chunk = COD_Order_CRM_Repository::get_orders( $filters );
			foreach ( $chunk['rows'] as $row ) {
				$order = wc_get_order( $row['order_id'] );
				fputcsv(
					$output,
					array(
						$row['order_id'],
						$row['order_date'],
						$row['customer_name'],
						$row['phone'],
						$row['city'],
						$row['product_name'],
						$row['order_value'],
						$order ? $order->get_status() : '',
						$row['call_status'],
						$row['call_attempts'],
						$row['last_call_time'],
						$row['confirmation_status'],
						$row['cancellation_reason'],
						$row['follow_up_date'],
						$row['order_priority'],
						$row['call_notes'],
						$row['duplicate_phone_flag'] ? 'yes' : 'no',
					)
				);
			}

			$filters['page']++;
		} while ( $filters['page'] <= $chunk['totalPage'] );

		fclose( $output );
		exit;
	}
}
