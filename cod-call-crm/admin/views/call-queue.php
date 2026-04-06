<?php
$filters = [
    'date_from' => sanitize_text_field((string)($_GET['date_from'] ?? '')),
    'date_to' => sanitize_text_field((string)($_GET['date_to'] ?? '')),
    'agent_id' => intval($_GET['agent_id'] ?? 0),
    'status' => sanitize_text_field((string)($_GET['status'] ?? 'pending_call')),
    'search' => sanitize_text_field((string)($_GET['search'] ?? '')),
];
$orders = COD_CRM_DB::get_orders($filters, 200, 0);
?>
<div class="wrap cod-crm-wrap">
<h1><?php esc_html_e('Call Queue', 'cod-call-crm'); ?></h1>
<form method="get" class="cod-crm-filters">
    <input type="hidden" name="page" value="cod-crm-call-queue">
    <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
    <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
    <select name="agent_id"><option value="0">All Agents</option><?php foreach (COD_CRM_DB::get_agents() as $a): ?><option value="<?php echo esc_attr((string)$a->ID); ?>" <?php selected($filters['agent_id'], $a->ID); ?>><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select>
    <select name="status"><option value="all">All</option><option value="pending_call" <?php selected($filters['status'], 'pending_call'); ?>>Pending</option><option value="callback_scheduled" <?php selected($filters['status'], 'callback_scheduled'); ?>>Callback</option></select>
    <input type="text" name="search" placeholder="Name or phone" value="<?php echo esc_attr($filters['search']); ?>">
    <button class="button">Filter</button>
</form>
<table class="widefat striped cod-crm-queue-table">
<thead><tr><th>Order ID</th><th>Customer Name</th><th>Phone</th><th>Amount</th><th>Payment</th><th>Items</th><th>Attempts</th><th>Status</th><th>Last Called</th><th>Action</th></tr></thead>
<tbody>
<?php if (empty($orders)): ?>
<tr><td colspan="10">No orders found.</td></tr>
<?php else: foreach ($orders as $o): $last = COD_CRM_DB::get_last_log_for_order($o['order_id']); ?>
<tr data-order-row="<?php echo esc_attr($o['order_id']); ?>">
<td><?php echo esc_html($o['order_id']); ?></td>
<td><?php echo esc_html($o['customer_name']); ?></td>
<td><?php echo esc_html($o['customer_phone']); ?></td>
<td>₹<?php echo esc_html(number_format((float)$o['order_amount'], 2)); ?></td>
<td><?php echo esc_html($o['payment_method']); ?></td>
<td><?php $items = json_decode((string)$o['order_items'], true); echo esc_html(is_array($items) ? implode(', ', array_map(static fn($i)=>($i['name']??'').' x'.($i['qty']??1), $items)) : (string)$o['order_items']); ?></td>
<td><?php echo esc_html($o['total_attempts'] . '/' . $o['max_attempts']); ?></td>
<td><span class="cod-crm-status-badge cod-crm-status-<?php echo esc_attr($o['order_status']); ?>"><?php echo esc_html(ucwords(str_replace('_',' ', $o['order_status']))); ?></span></td>
<td><?php echo esc_html($last['called_at'] ?? '-'); ?></td>
<td><button class="button button-primary cod-crm-start-call" data-order-id="<?php echo esc_attr($o['order_id']); ?>">Start Call</button></td>
</tr>
<tr class="cod-crm-panel-row" id="panel-<?php echo esc_attr($o['order_id']); ?>" style="display:none;"><td colspan="10"></td></tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
