<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Ajax {
    public static function init(): void {
        add_action('wp_ajax_cod_crm_get_call_panel', [__CLASS__, 'get_call_panel']);
        add_action('wp_ajax_cod_crm_save_outcome', [__CLASS__, 'save_outcome']);
        add_action('wp_ajax_cod_crm_bulk_sync', [__CLASS__, 'bulk_sync']);
        add_action('wp_ajax_cod_crm_preview_csv', [__CLASS__, 'preview_csv']);
        add_action('wp_ajax_cod_crm_import_csv', [__CLASS__, 'import_csv']);
        add_action('wp_ajax_cod_crm_export_logs', [__CLASS__, 'export_logs']);
        add_action('wp_ajax_cod_crm_clear_sync_log', [__CLASS__, 'clear_sync_log']);
        add_action('wp_ajax_cod_crm_add_wc_order_to_queue', [__CLASS__, 'add_wc_order_to_queue']);
    }

    private static function can_call_agent(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('cod_agent') || current_user_can('read');
    }

    public static function get_call_panel(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!self::can_call_agent()) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }

        $order_id = sanitize_text_field((string) ($_POST['order_id'] ?? ''));
        $order = COD_CRM_DB::get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'cod-call-crm')]);
        }

        if (!current_user_can('manage_woocommerce') && intval($order['assigned_agent_id']) !== get_current_user_id()) {
            wp_send_json_error(['message' => __('Not assigned', 'cod-call-crm')], 403);
        }

        $items = json_decode((string) $order['order_items'], true);
        $first_product = !empty($items[0]['name']) ? $items[0]['name'] : __('Product', 'cod-call-crm');

        $script = str_replace(
            ['{customer_name}', '{product_name}', '{amount}', '{delivery_address}', '{order_id}'],
            [$order['customer_name'], $first_product, $order['order_amount'], $order['customer_address'], $order['order_id']],
            (string) get_option('cod_crm_script', '')
        );

        ob_start();
        ?>
        <div class="cod-crm-call-panel">
            <div class="cod-crm-order-summary">
                <h4><?php echo esc_html__('Order Summary', 'cod-call-crm'); ?></h4>
                <p><strong><?php echo esc_html($order['customer_name']); ?></strong> - <a href="tel:<?php echo esc_attr($order['customer_phone']); ?>"><?php echo esc_html($order['customer_phone']); ?></a></p>
                <p><?php echo esc_html($order['customer_address']); ?></p>
                <p><span class="cod-crm-badge"><?php echo esc_html(strtoupper($order['payment_method'])); ?></span> ₹<?php echo esc_html(number_format((float)$order['order_amount'], 2)); ?></p>
            </div>
            <details>
                <summary><?php echo esc_html__('Call Script Preview', 'cod-call-crm'); ?></summary>
                <pre><?php echo esc_html($script); ?></pre>
            </details>
            <form class="cod-crm-outcome-form" data-order-id="<?php echo esc_attr($order['order_id']); ?>">
                <?php wp_nonce_field('cod_crm_nonce', 'nonce'); ?>
                <?php
                $outcomes = ['confirmed','cancelled','no_answer','wrong_number','call_not_picked','switched_off','callback_requested','prepaid_converted','busy'];
                foreach ($outcomes as $o) {
                    echo '<label><input type="radio" name="outcome" value="' . esc_attr($o) . '"> ' . esc_html(ucwords(str_replace('_', ' ', $o))) . '</label> ';
                }
                ?>
                <p class="cod-crm-callback-field" style="display:none;"><input type="datetime-local" name="next_callback_at"></p>
                <p class="cod-crm-payment-link" style="display:none;"><input type="url" name="payment_link" placeholder="https://"></p>
                <p><textarea name="reason" placeholder="Reason / Notes"></textarea></p>
                <p><input type="text" name="duration" placeholder="MM:SS"></p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Outcome', 'cod-call-crm'); ?></button>
            </form>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    }

    public static function save_outcome(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!self::can_call_agent()) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }

        $order_id = sanitize_text_field((string) ($_POST['order_id'] ?? ''));
        $outcome = sanitize_text_field((string) ($_POST['outcome'] ?? ''));
        $reason = wp_kses_post((string) ($_POST['reason'] ?? ''));
        $duration = sanitize_text_field((string) ($_POST['duration'] ?? ''));
        $next_callback = sanitize_text_field((string) ($_POST['next_callback_at'] ?? ''));

        if (in_array($outcome, ['cancelled', 'wrong_number'], true) && empty($reason)) {
            wp_send_json_error(['message' => __('Reason is required.', 'cod-call-crm')]);
        }

        $order = COD_CRM_DB::get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'cod-call-crm')]);
        }

        if (!current_user_can('manage_woocommerce') && intval($order['assigned_agent_id']) !== get_current_user_id()) {
            wp_send_json_error(['message' => __('Not assigned.', 'cod-call-crm')], 403);
        }

        $duration_seconds = 0;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $duration, $m)) {
            $duration_seconds = intval($m[1]) * 60 + intval($m[2]);
        }

        $attempt = intval($order['total_attempts']) + 1;
        COD_CRM_DB::insert_log([
            'order_id' => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'customer_address' => $order['customer_address'],
            'order_amount' => $order['order_amount'],
            'order_items' => json_decode((string)$order['order_items'], true),
            'call_outcome' => $outcome,
            'call_reason' => $reason,
            'call_duration_seconds' => $duration_seconds,
            'agent_id' => get_current_user_id(),
            'attempt_number' => $attempt,
            'next_callback_at' => $next_callback,
            'called_at' => current_time('mysql'),
        ]);

        $status_map = [
            'confirmed' => 'confirmed',
            'cancelled' => 'cancelled',
            'callback_requested' => 'callback_scheduled',
            'prepaid_converted' => 'prepaid_converted',
        ];

        $new_status = $status_map[$outcome] ?? 'pending_call';
        $max_attempts = intval($order['max_attempts']);
        if ($attempt >= $max_attempts && !in_array($outcome, ['confirmed', 'cancelled', 'prepaid_converted'], true)) {
            $new_status = 'max_attempts_reached';
            COD_CRM_Notifications::notify_admin('max_attempts', $order);
        }

        COD_CRM_DB::update_order($order_id, [
            'order_status' => $new_status,
            'total_attempts' => $attempt,
        ]);

        if ((bool) get_option('cod_crm_realtime_sync_enabled', 1) && function_exists('wc_get_order')) {
            $wc_order = wc_get_order($order_id);
            if ($wc_order) {
                if ($outcome === 'confirmed') {
                    $wc_order->update_status('processing', 'Confirmed from COD CRM');
                }
                if ($outcome === 'cancelled') {
                    $wc_order->update_status('cancelled', 'Cancelled from COD CRM');
                }
            }
        }

        if ($outcome === 'confirmed') {
            COD_CRM_Notifications::notify_admin('confirmed', $order);
        } elseif ($outcome === 'cancelled') {
            COD_CRM_Notifications::notify_admin('cancelled', $order);
        }

        wp_send_json_success(['message' => __('Outcome saved.', 'cod-call-crm'), 'order_status' => $new_status, 'attempts' => $attempt]);
    }

    public static function bulk_sync(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }
        if (!function_exists('wc_get_orders')) {
            wp_send_json_error(['message' => __('WooCommerce not active', 'cod-call-crm')]);
        }

        $offset = intval($_POST['offset'] ?? 0);
        $limit = 50;
        $statuses = ['processing', 'pending', 'on-hold'];
        $orders = wc_get_orders([
            'limit' => $limit,
            'offset' => $offset,
            'status' => $statuses,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        $total = wc_get_orders(['limit' => -1, 'status' => $statuses, 'return' => 'ids']);

        $imported = 0;
        $skipped = 0;
        foreach ($orders as $order) {
            $ok = COD_CRM_Woo_Sync::sync_wc_order($order, 'bulk');
            if ($ok) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        $processed = $offset + count($orders);
        $done = $processed >= count($total) || count($orders) < $limit;
        if ($done) {
            update_option('cod_crm_last_bulk_sync', current_time('mysql'));
        }

        wp_send_json_success([
            'imported' => $imported,
            'skipped' => $skipped,
            'processed' => $processed,
            'total' => count($total),
            'done' => $done,
            'next_offset' => $processed,
        ]);
    }

    public static function preview_csv(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'cod-call-crm')]);
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if (!$handle) {
            wp_send_json_error(['message' => __('Cannot read file', 'cod-call-crm')]);
        }

        $header = fgetcsv($handle);
        $preview = [];
        for ($i = 0; $i < 5; $i++) {
            $row = fgetcsv($handle);
            if (!$row) {
                break;
            }
            $preview[] = array_combine($header, $row);
        }
        fclose($handle);
        wp_send_json_success(['header' => $header, 'preview' => $preview]);
    }

    public static function import_csv(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'cod-call-crm')]);
        }

        $required = ['order_id', 'customer_name', 'customer_phone', 'customer_address', 'order_amount', 'order_items', 'payment_method'];
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        if (array_diff($required, $header)) {
            fclose($handle);
            wp_send_json_error(['message' => __('CSV columns missing', 'cod-call-crm')]);
        }

        $success = 0; $skip = 0; $error = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $line = array_combine($header, $row);
            try {
                $inserted = COD_CRM_DB::insert_order([
                    'order_id' => $line['order_id'],
                    'customer_name' => $line['customer_name'],
                    'customer_phone' => $line['customer_phone'],
                    'customer_address' => $line['customer_address'],
                    'order_amount' => $line['order_amount'],
                    'order_items' => $line['order_items'],
                    'payment_method' => $line['payment_method'],
                    'source' => 'csv_import',
                    'order_status' => 'pending_call',
                ]);
                if ($inserted) {
                    $success++;
                } else {
                    $skip++;
                }
            } catch (Throwable $e) {
                $error++;
            }
        }
        fclose($handle);
        wp_send_json_success(['success' => $success, 'skip' => $skip, 'error' => $error]);
    }

    public static function export_logs(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!self::can_call_agent()) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }
        $filters = [
            'outcome' => sanitize_text_field((string)($_GET['outcome'] ?? '')),
            'agent_id' => intval($_GET['agent_id'] ?? 0),
            'date_from' => sanitize_text_field((string)($_GET['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string)($_GET['date_to'] ?? '')),
            'search' => sanitize_text_field((string)($_GET['search'] ?? '')),
        ];
        $rows = COD_CRM_DB::get_logs($filters, 5000, 0);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cod-crm-logs.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Log ID','Order ID','Customer','Phone','Outcome','Reason','Duration','Agent','Attempt','Called At']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['order_id'],$r['customer_name'],$r['customer_phone'],$r['call_outcome'],$r['call_reason'],$r['call_duration_seconds'],$r['agent_id'],$r['attempt_number'],$r['called_at']]);
        }
        fclose($out);
        exit;
    }

    public static function clear_sync_log(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }
        COD_CRM_Sync_Logger::clear();
        wp_send_json_success();
    }

    public static function add_wc_order_to_queue(): void {
        check_ajax_referer('cod_crm_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'cod-call-crm')], 403);
        }
        $order_id = intval($_POST['order_id'] ?? 0);
        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'cod-call-crm')]);
        }
        $ok = COD_CRM_Woo_Sync::sync_wc_order($order, 'manual');
        wp_send_json_success(['added' => (bool)$ok]);
    }
}
