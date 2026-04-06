<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_DB {
    public static function init(): void {}

    public static function orders_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'cod_call_orders';
    }

    public static function logs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'cod_call_logs';
    }

    public static function order_exists(string $order_id): bool {
        global $wpdb;
        $table = self::orders_table();
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE order_id = %s", $order_id));
        return $count > 0;
    }

    public static function insert_order(array $data): int {
        global $wpdb;
        if (self::order_exists((string)$data['order_id'])) {
            return 0;
        }

        $table = self::orders_table();
        $insert = [
            'order_id' => sanitize_text_field((string) $data['order_id']),
            'customer_name' => sanitize_text_field((string) $data['customer_name']),
            'customer_phone' => sanitize_text_field((string) $data['customer_phone']),
            'customer_address' => wp_kses_post((string) $data['customer_address']),
            'order_amount' => floatval($data['order_amount']),
            'order_items' => wp_json_encode($data['order_items']),
            'payment_method' => sanitize_text_field((string) $data['payment_method']),
            'wc_order_status' => sanitize_text_field((string) ($data['wc_order_status'] ?? '')),
            'order_status' => sanitize_text_field((string) ($data['order_status'] ?? 'pending_call')),
            'total_attempts' => intval($data['total_attempts'] ?? 0),
            'max_attempts' => intval($data['max_attempts'] ?? intval(get_option('cod_crm_max_attempts', 3))),
            'assigned_agent_id' => isset($data['assigned_agent_id']) ? intval($data['assigned_agent_id']) : null,
            'source' => sanitize_text_field((string) ($data['source'] ?? 'manual')),
            'crm_synced_at' => isset($data['crm_synced_at']) ? sanitize_text_field((string)$data['crm_synced_at']) : current_time('mysql'),
        ];

        $formats = ['%s','%s','%s','%s','%f','%s','%s','%s','%s','%d','%d','%d','%s','%s'];
        $wpdb->insert($table, $insert, $formats);
        return (int) $wpdb->insert_id;
    }

    public static function get_order(string $order_id): ?array {
        global $wpdb;
        $table = self::orders_table();
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id=%s", $order_id), ARRAY_A);
        return $order ?: null;
    }

    public static function get_orders(array $filters = [], int $limit = 25, int $offset = 0): array {
        global $wpdb;
        $table = self::orders_table();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'order_status = %s';
            $params[] = sanitize_text_field($filters['status']);
        }
        if (!empty($filters['payment_method'])) {
            $where[] = 'payment_method = %s';
            $params[] = sanitize_text_field($filters['payment_method']);
        }
        if (!empty($filters['source'])) {
            $where[] = 'source = %s';
            $params[] = sanitize_text_field($filters['source']);
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'assigned_agent_id = %d';
            $params[] = intval($filters['agent_id']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where[] = '(customer_name LIKE %s OR customer_phone LIKE %s OR order_id LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }

        if (!current_user_can('manage_woocommerce') && current_user_can('read')) {
            $where[] = 'assigned_agent_id = %d';
            $params[] = get_current_user_id();
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    public static function count_orders(array $filters = []): int {
        global $wpdb;
        $table = self::orders_table();
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'order_status = %s';
            $params[] = sanitize_text_field($filters['status']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where[] = '(customer_name LIKE %s OR customer_phone LIKE %s OR order_id LIKE %s)';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if (!current_user_can('manage_woocommerce') && current_user_can('read')) {
            $where[] = 'assigned_agent_id = %d';
            $params[] = get_current_user_id();
        }
        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function update_order(string $order_id, array $data): bool {
        global $wpdb;
        $table = self::orders_table();

        $safe = [];
        foreach ($data as $k => $v) {
            if ($k === 'order_amount') {
                $safe[$k] = floatval($v);
            } elseif (in_array($k, ['total_attempts', 'max_attempts', 'assigned_agent_id'], true)) {
                $safe[$k] = intval($v);
            } elseif ($k === 'customer_address') {
                $safe[$k] = wp_kses_post((string)$v);
            } else {
                $safe[$k] = sanitize_text_field((string)$v);
            }
        }
        return false !== $wpdb->update($table, $safe, ['order_id' => sanitize_text_field($order_id)]);
    }

    public static function delete_order(string $order_id): bool {
        global $wpdb;
        $table = self::orders_table();
        return false !== $wpdb->delete($table, ['order_id' => sanitize_text_field($order_id)], ['%s']);
    }

    public static function insert_log(array $data): int {
        global $wpdb;
        $table = self::logs_table();
        $insert = [
            'order_id' => sanitize_text_field((string)$data['order_id']),
            'customer_name' => sanitize_text_field((string)$data['customer_name']),
            'customer_phone' => sanitize_text_field((string)$data['customer_phone']),
            'customer_address' => wp_kses_post((string)$data['customer_address']),
            'order_amount' => floatval($data['order_amount']),
            'order_items' => wp_json_encode($data['order_items']),
            'call_outcome' => sanitize_text_field((string)$data['call_outcome']),
            'call_reason' => wp_kses_post((string)($data['call_reason'] ?? '')),
            'call_duration_seconds' => isset($data['call_duration_seconds']) ? intval($data['call_duration_seconds']) : null,
            'agent_id' => intval($data['agent_id'] ?? get_current_user_id()),
            'attempt_number' => intval($data['attempt_number'] ?? 1),
            'next_callback_at' => !empty($data['next_callback_at']) ? sanitize_text_field((string)$data['next_callback_at']) : null,
            'called_at' => sanitize_text_field((string)($data['called_at'] ?? current_time('mysql'))),
        ];

        $wpdb->insert(
            $table,
            $insert,
            ['%s','%s','%s','%s','%f','%s','%s','%s','%d','%d','%d','%s','%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function get_logs(array $filters = [], int $limit = 25, int $offset = 0): array {
        global $wpdb;
        $table = self::logs_table();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['outcome'])) {
            $where[] = 'call_outcome = %s';
            $params[] = sanitize_text_field($filters['outcome']);
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'agent_id = %d';
            $params[] = intval($filters['agent_id']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where[] = '(order_id LIKE %s OR customer_phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(called_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(called_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        if (!current_user_can('manage_woocommerce') && current_user_can('read')) {
            $where[] = 'agent_id = %d';
            $params[] = get_current_user_id();
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY called_at DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    public static function get_last_log_for_order(string $order_id): ?array {
        global $wpdb;
        $table = self::logs_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s ORDER BY called_at DESC LIMIT 1", $order_id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_dashboard_metrics(string $date_from, string $date_to): array {
        global $wpdb;
        $orders = self::orders_table();
        $logs = self::logs_table();

        $scope = current_user_can('manage_woocommerce') ? '' : $wpdb->prepare(' AND agent_id = %d', get_current_user_id());

        $called = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE DATE(called_at) BETWEEN %s AND %s {$scope}", $date_from, $date_to));

        $metrics = [
            'total_orders_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders} WHERE DATE(created_at)=%s", current_time('Y-m-d'))),
            'confirmed_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE call_outcome='confirmed' AND DATE(called_at)=%s {$scope}", current_time('Y-m-d'))),
            'cancelled_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE call_outcome='cancelled' AND DATE(called_at)=%s {$scope}", current_time('Y-m-d'))),
            'no_answer_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE call_outcome IN ('no_answer','call_not_picked') AND DATE(called_at)=%s {$scope}", current_time('Y-m-d'))),
            'callbacks_scheduled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$orders} WHERE order_status='callback_scheduled'"),
            'prepaid_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE call_outcome='prepaid_converted' AND DATE(called_at)=%s {$scope}", current_time('Y-m-d'))),
            'rto_risk' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$logs} WHERE call_outcome IN ('no_answer','wrong_number','switched_off') AND DATE(called_at) BETWEEN %s AND %s {$scope}", $date_from, $date_to)),
            'overall_rate' => 0,
        ];
        $metrics['overall_rate'] = $called > 0 ? round(($metrics['confirmed_today'] / $called) * 100, 2) : 0;
        return $metrics;
    }

    public static function get_outcome_series(int $days = 7): array {
        global $wpdb;
        $table = self::logs_table();
        $since = gmdate('Y-m-d', strtotime("-" . ($days - 1) . " days", current_time('timestamp')));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(called_at) d, call_outcome o, COUNT(*) c FROM {$table} WHERE DATE(called_at) >= %s GROUP BY DATE(called_at), call_outcome", $since), ARRAY_A);
        return $rows;
    }

    public static function get_callbacks_due(int $hours = 2): array {
        global $wpdb;
        $logs = self::logs_table();
        $orders = self::orders_table();
        $now = current_time('mysql');
        $until = gmdate('Y-m-d H:i:s', strtotime("+{$hours} hours", current_time('timestamp')));
        $sql = "SELECT o.order_id,o.customer_name,o.customer_phone,l.next_callback_at,o.assigned_agent_id
                FROM {$logs} l
                INNER JOIN {$orders} o ON l.order_id=o.order_id
                WHERE l.next_callback_at BETWEEN %s AND %s
                ORDER BY l.next_callback_at ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $now, $until), ARRAY_A);
    }

    public static function get_agents(): array {
        return get_users(['role' => 'cod_agent', 'orderby' => 'display_name', 'order' => 'ASC']);
    }

    public static function get_reports_data(string $from, string $to): array {
        global $wpdb;
        $logs = self::logs_table();
        $orders = self::orders_table();

        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(called_at) d,
            COUNT(*) total,
            SUM(call_outcome='confirmed') confirmed,
            SUM(call_outcome='cancelled') cancelled,
            SUM(call_outcome='no_answer') no_answer,
            SUM(call_outcome='wrong_number') wrong_number,
            SUM(call_outcome='call_not_picked') not_picked,
            SUM(call_outcome='switched_off') switched_off,
            SUM(call_outcome='busy') busy,
            SUM(call_outcome='callback_requested') callback_requested,
            SUM(call_outcome='prepaid_converted') prepaid
            FROM {$logs}
            WHERE DATE(called_at) BETWEEN %s AND %s
            GROUP BY DATE(called_at)
            ORDER BY DATE(called_at) DESC",
            $from,
            $to
        ), ARRAY_A);

        $agent = $wpdb->get_results($wpdb->prepare(
            "SELECT l.agent_id,
            COUNT(*) total_calls,
            SUM(l.call_outcome='confirmed') confirmed,
            SUM(l.call_outcome='cancelled') cancelled,
            AVG(NULLIF(l.call_duration_seconds,0)) avg_duration,
            SUM(l.call_outcome='callback_requested') pending_callbacks
            FROM {$logs} l
            WHERE DATE(l.called_at) BETWEEN %s AND %s
            GROUP BY l.agent_id",
            $from,
            $to
        ), ARRAY_A);

        $dist = $wpdb->get_results($wpdb->prepare(
            "SELECT call_outcome, COUNT(*) cnt FROM {$logs} WHERE DATE(called_at) BETWEEN %s AND %s GROUP BY call_outcome",
            $from,
            $to
        ), ARRAY_A);

        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(attempt_number=1) a1, SUM(attempt_number=2) a2, SUM(attempt_number=3) a3
             FROM {$logs}
             WHERE call_outcome='confirmed' AND DATE(called_at) BETWEEN %s AND %s",
            $from,
            $to
        ), ARRAY_A);

        $rto = $wpdb->get_results(
            "SELECT o.order_id,o.customer_name,o.customer_phone,o.total_attempts,
            (SELECT l.call_outcome FROM {$logs} l WHERE l.order_id=o.order_id ORDER BY l.called_at DESC LIMIT 1) last_outcome,
            (SELECT l.called_at FROM {$logs} l WHERE l.order_id=o.order_id ORDER BY l.called_at DESC LIMIT 1) last_called
            FROM {$orders} o
            WHERE o.total_attempts >= 2 AND o.order_status IN ('pending_call','callback_scheduled','max_attempts_reached')",
            ARRAY_A
        );

        return [
            'daily' => $daily,
            'agent' => $agent,
            'distribution' => $dist,
            'attempt' => $attempt ?: ['a1' => 0, 'a2' => 0, 'a3' => 0],
            'rto' => $rto,
        ];
    }
}
