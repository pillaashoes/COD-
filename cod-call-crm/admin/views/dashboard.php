<?php
$date_from = sanitize_text_field((string)($_GET['date_from'] ?? current_time('Y-m-d')));
$date_to = sanitize_text_field((string)($_GET['date_to'] ?? current_time('Y-m-d')));
$metrics = COD_CRM_DB::get_dashboard_metrics($date_from, $date_to);
$series = COD_CRM_DB::get_outcome_series(7);
$callbacks = COD_CRM_DB::get_callbacks_due(2);
?>
<div class="wrap cod-crm-wrap">
    <h1><?php esc_html_e('COD CRM Dashboard', 'cod-call-crm'); ?></h1>
    <div class="cod-crm-metrics-grid">
        <?php foreach ([
            'Total Orders Today' => $metrics['total_orders_today'],
            'Confirmed Today' => $metrics['confirmed_today'],
            'Cancelled Today' => $metrics['cancelled_today'],
            'No Answer / Not Picked Today' => $metrics['no_answer_today'],
            'Callbacks Scheduled' => $metrics['callbacks_scheduled'],
            'Prepaid Converted Today' => $metrics['prepaid_today'],
            'Overall Confirmation Rate %' => $metrics['overall_rate'] . '%',
            'RTO Risk Orders' => $metrics['rto_risk'],
        ] as $label => $value): ?>
        <div class="cod-crm-card">
            <h3><?php echo esc_html($label); ?></h3>
            <p class="cod-crm-card-value"><?php echo esc_html((string)$value); ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="cod-crm-card">
        <h2><?php esc_html_e('Last 7 Days Outcomes', 'cod-call-crm'); ?></h2>
        <canvas id="codCrmStackedChart" height="120"></canvas>
    </div>

    <div class="cod-crm-card">
        <h2><?php esc_html_e("Today's Callback Reminders", 'cod-call-crm'); ?></h2>
        <table class="widefat striped">
            <thead><tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Scheduled At</th><th>Agent</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($callbacks)): ?>
                <tr><td colspan="6"><?php esc_html_e('No callbacks in next 2 hours.', 'cod-call-crm'); ?></td></tr>
            <?php else: foreach ($callbacks as $cb): $agent = get_user_by('id', intval($cb['assigned_agent_id'])); ?>
                <tr>
                    <td><?php echo esc_html($cb['order_id']); ?></td>
                    <td><?php echo esc_html($cb['customer_name']); ?></td>
                    <td><?php echo esc_html($cb['customer_phone']); ?></td>
                    <td><?php echo esc_html($cb['next_callback_at']); ?></td>
                    <td><?php echo esc_html($agent ? $agent->display_name : '-'); ?></td>
                    <td><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=cod-crm-call-queue&search=' . rawurlencode($cb['order_id']))); ?>">Start Call</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
window.codCrmOutcomeSeries = <?php echo wp_json_encode($series); ?>;
</script>
