<?php
/**
 * Admin CRM screen and AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class COD_Order_CRM_Admin {

	/**
	 * Wire up admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'wp_ajax_cod_order_crm_fetch_orders', array( __CLASS__, 'ajax_fetch_orders' ) );
		add_action( 'wp_ajax_cod_order_crm_update_field', array( __CLASS__, 'ajax_update_field' ) );
		add_action( 'wp_ajax_cod_order_crm_call_now', array( __CLASS__, 'ajax_call_now' ) );
		add_action( 'wp_ajax_cod_order_crm_bulk_update', array( __CLASS__, 'ajax_bulk_update' ) );
	}

	/**
	 * Register admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Order CRM', 'cod-order-crm' ),
			__( 'Order CRM', 'cod-order-crm' ),
			COD_Order_CRM_Capabilities::CAPABILITY,
			'cod-order-crm',
			array( __CLASS__, 'render_page' ),
			'dashicons-phone',
			56
		);
	}

	/**
	 * Load CSS/JS.
	 *
	 * @param string $hook Hook name.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_cod-order-crm' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cod-order-crm-admin',
			COD_ORDER_CRM_URL . 'assets/css/admin.css',
			array(),
			COD_ORDER_CRM_VERSION
		);

		wp_enqueue_script(
			'cod-order-crm-admin',
			COD_ORDER_CRM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			COD_ORDER_CRM_VERSION,
			true
		);

		wp_localize_script(
			'cod-order-crm-admin',
			'codOrderCrm',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'cod_order_crm_nonce' ),
				'fields'     => COD_Order_CRM_Meta::fields(),
				'dueToday'   => wp_date( 'Y-m-d' ),
				'canManage'  => current_user_can( COD_Order_CRM_Capabilities::CAPABILITY ),
			)
		);
	}

	/**
	 * Render CRM container.
	 */
	public static function render_page() {
		if ( ! current_user_can( COD_Order_CRM_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access Order CRM.', 'cod-order-crm' ) );
		}
		?>
		<div class="wrap cod-order-crm-wrap">
			<h1><?php esc_html_e( 'Order CRM', 'cod-order-crm' ); ?></h1>
			<div class="cod-order-crm-toolbar">
				<input type="search" id="crm-search" placeholder="<?php esc_attr_e( 'Search order ID / phone', 'cod-order-crm' ); ?>" />
				<select id="crm-filter-call-status"><option value=""><?php esc_html_e( 'Call Status', 'cod-order-crm' ); ?></option><?php self::render_options( COD_Order_CRM_Meta::fields()['call_status']['options'] ); ?></select>
				<select id="crm-filter-confirmation-status"><option value=""><?php esc_html_e( 'Confirmation Status', 'cod-order-crm' ); ?></option><?php self::render_options( COD_Order_CRM_Meta::fields()['confirmation_status']['options'] ); ?></select>
				<select id="crm-filter-city"><option value=""><?php esc_html_e( 'City', 'cod-order-crm' ); ?></option><?php foreach ( COD_Order_CRM_Repository::get_distinct_cities() as $city ) : ?><option value="<?php echo esc_attr( $city ); ?>"><?php echo esc_html( $city ); ?></option><?php endforeach; ?></select>
				<label><input type="checkbox" id="crm-filter-high-value" /> <?php esc_html_e( 'High Value (> ₹2000)', 'cod-order-crm' ); ?></label>
				<label><input type="checkbox" id="crm-filter-due-followup" /> <?php esc_html_e( 'Due Follow-ups', 'cod-order-crm' ); ?></label>
				<input type="date" id="crm-date-from" />
				<input type="date" id="crm-date-to" />
				<button class="button" id="crm-apply-filters"><?php esc_html_e( 'Apply', 'cod-order-crm' ); ?></button>
				<button class="button" id="crm-reset-filters"><?php esc_html_e( 'Reset', 'cod-order-crm' ); ?></button>
				<a class="button button-primary" id="crm-export-csv" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cod_order_crm_export_csv' ), 'cod_order_crm_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'cod-order-crm' ); ?></a>
			</div>
			<div class="cod-order-crm-bulk">
				<select id="crm-bulk-action">
					<option value=""><?php esc_html_e( 'Bulk action', 'cod-order-crm' ); ?></option>
					<option value="call_status:called_not_picked"><?php esc_html_e( 'Mark Called - Not Picked', 'cod-order-crm' ); ?></option>
					<option value="call_status:callback_needed"><?php esc_html_e( 'Mark Callback Needed', 'cod-order-crm' ); ?></option>
					<option value="confirmation_status:cod_confirmed"><?php esc_html_e( 'Mark COD Confirmed', 'cod-order-crm' ); ?></option>
					<option value="confirmation_status:cancelled"><?php esc_html_e( 'Mark Cancelled', 'cod-order-crm' ); ?></option>
				</select>
				<button class="button" id="crm-apply-bulk"><?php esc_html_e( 'Apply Bulk Update', 'cod-order-crm' ); ?></button>
			</div>
			<div class="cod-order-crm-table-wrap">
				<table class="wp-list-table widefat striped fixed" id="cod-order-crm-table">
					<thead>
					<tr>
						<th><input type="checkbox" id="crm-select-all" /></th>
						<th><?php esc_html_e( 'Order ID', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Customer Name', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'City', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Product Name', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Order Value', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Call Status', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Confirmation', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Cancellation Reason', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Attempts', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Follow-up', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'cod-order-crm' ); ?></th>
						<th><?php esc_html_e( 'Order Date', 'cod-order-crm' ); ?></th>
					</tr>
					</thead>
					<tbody id="cod-order-crm-body"></tbody>
				</table>
			</div>
			<div class="tablenav"><div class="tablenav-pages" id="crm-pagination"></div></div>
		</div>
		<?php
	}

	/**
	 * Print select options.
	 *
	 * @param array<string,string> $options Key/value list.
	 */
	private static function render_options( $options ) {
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
	}

	/**
	 * Verify AJAX permissions.
	 */
	private static function assert_ajax_access() {
		check_ajax_referer( 'cod_order_crm_nonce', 'nonce' );
		if ( ! current_user_can( COD_Order_CRM_Capabilities::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cod-order-crm' ) ), 403 );
		}
	}

	/**
	 * AJAX order list.
	 */
	public static function ajax_fetch_orders() {
		self::assert_ajax_access();
		$payload = COD_Order_CRM_Repository::get_orders(
			array(
				'page'                => isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1,
				'per_page'            => isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 50,
				'search'              => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
				'call_status'         => isset( $_POST['call_status'] ) ? sanitize_text_field( wp_unslash( $_POST['call_status'] ) ) : '',
				'confirmation_status' => isset( $_POST['confirmation_status'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_status'] ) ) : '',
				'city'                => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'date_from'           => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
				'date_to'             => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
				'high_value'          => ! empty( $_POST['high_value'] ) ? 1 : 0,
				'due_followup'        => ! empty( $_POST['due_followup'] ) ? 1 : 0,
			)
		);
		wp_send_json_success( $payload );
	}

	/**
	 * AJAX single field update.
	 */
	public static function ajax_update_field() {
		self::assert_ajax_access();

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$field    = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value    = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

		$allowed_fields = array( 'call_status', 'confirmation_status', 'cancellation_reason', 'call_notes', 'follow_up_date', 'order_priority' );

		if ( ! $order_id || ! in_array( $field, $allowed_fields, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'cod-order-crm' ) ), 400 );
		}

		if ( 'call_notes' === $field ) {
			$value = sanitize_textarea_field( $value );
		} elseif ( 'follow_up_date' === $field ) {
			$value = sanitize_text_field( $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		update_post_meta( $order_id, $field, $value );

		wp_send_json_success(
			array(
				'order_id' => $order_id,
				'field'    => $field,
				'value'    => $value,
			)
		);
	}

	/**
	 * Call now action.
	 */
	public static function ajax_call_now() {
		self::assert_ajax_access();

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'cod-order-crm' ) ), 400 );
		}

		$current_attempts = (int) get_post_meta( $order_id, 'call_attempts', true );
		$attempts        = $current_attempts + 1;
		$time_now        = current_time( 'mysql' );

		update_post_meta( $order_id, 'call_attempts', $attempts );
		update_post_meta( $order_id, 'last_call_time', $time_now );

		wp_send_json_success(
			array(
				'call_attempts'  => $attempts,
				'last_call_time' => $time_now,
			)
		);
	}

	/**
	 * Bulk update action.
	 */
	public static function ajax_bulk_update() {
		self::assert_ajax_access();

		$order_ids = isset( $_POST['order_ids'] ) ? (array) $_POST['order_ids'] : array();
		$field     = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value     = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		$allowed   = array( 'call_status', 'confirmation_status' );

		if ( empty( $order_ids ) || ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bulk request.', 'cod-order-crm' ) ), 400 );
		}

		foreach ( $order_ids as $order_id ) {
			update_post_meta( absint( $order_id ), $field, $value );
		}

		wp_send_json_success( array( 'updated' => count( $order_ids ) ) );
	}
}
