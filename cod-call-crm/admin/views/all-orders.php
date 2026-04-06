<?php
$per_page = 25;
$paged = max(1, intval($_GET['paged'] ?? 1));
$filters = [
    'status' => sanitize_text_field((string)($_GET['status'] ?? 'all')),
    'payment_method' => sanitize_text_field((string)($_GET['payment_method'] ?? '')),
    'agent_id' => intval($_GET['agent_id'] ?? 0),
    'date_from' => sanitize_text_field((string)($_GET['date_from'] ?? '')),
    'date_to' => sanitize_text_field((string)($_GET['date_to'] ?? '')),
    'source' => sanitize_text_field((string)($_GET['source'] ?? '')),
    'search' => sanitize_text_field((string)($_GET['search'] ?? '')),
];
if (!empty($_GET['delete_order']) && check_admin_referer('cod_crm_delete_order')) {
    COD_CRM_DB::delete_order(sanitize_text_field((string)$_GET['delete_order']));
}
$orders = COD_CRM_DB::get_orders($filters, $per_page, ($paged - 1) * $per_page);
$total = COD_CRM_DB::count_orders($filters);
$pages = (int) ceil($total / $per_page);
?>
<div class="wrap cod-crm-wrap">
<h1>All Orders</h1>
<form method="get" class="cod-crm-filters">
<input type="hidden" name="page" value="cod-crm-all-orders">
<select name="status"><option value="all">All Status</option><?php foreach (['pending_call','confirmed','cancelled','callback_scheduled','prepaid_converted','max_attempts_reached'] as $st): ?><option value="<?php echo esc_attr($st); ?>" <?php selected($filters['status'],$st); ?>><?php echo esc_html($st); ?></option><?php endforeach; ?></select>
<input type="text" name="payment_method" placeholder="Payment" value="<?php echo esc_attr($filters['payment_method']); ?>">
<select name="agent_id"><option value="0">All Agents</option><?php foreach (COD_CRM_DB::get_agents() as $a): ?><option value="<?php echo esc_attr((string)$a->ID); ?>" <?php selected($filters['agent_id'],$a->ID); ?>><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select>
<input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"><input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
<select name="source"><option value="">All Source</option><option value="woocommerce" <?php selected($filters['source'],'woocommerce'); ?>>WooCommerce</option><option value="manual" <?php selected($filters['source'],'manual'); ?>>Manual</option><option value="csv_import" <?php selected($filters['source'],'csv_import'); ?>>CSV</option></select>
<input type="text" name="search" placeholder="Search" value="<?php echo esc_attr($filters['search']); ?>">
<button class="button">Filter</button>
</form>
<table class="widefat striped"><thead><tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Payment</th><th>Source</th><th>Status</th><th>Attempts</th><th>Agent</th><th>Synced At</th><th>Actions</th></tr></thead><tbody>
<?php if (empty($orders)): ?><tr><td colspan="11">No orders found.</td></tr><?php else: foreach ($orders as $o): $agent=get_user_by('id',intval($o['assigned_agent_id'])); ?>
<tr>
<td><?php echo esc_html($o['order_id']); ?></td><td><?php echo esc_html($o['customer_name']); ?></td><td><?php echo esc_html($o['customer_phone']); ?></td><td><?php echo esc_html(number_format((float)$o['order_amount'],2)); ?></td><td><?php echo esc_html($o['payment_method']); ?></td><td><?php echo esc_html($o['source']); ?></td><td><?php echo esc_html($o['order_status']); ?></td><td><?php echo esc_html($o['total_attempts']); ?></td><td><?php echo esc_html($agent?$agent->display_name:'-'); ?></td><td><?php echo esc_html($o['crm_synced_at']); ?></td>
<td><a href="<?php echo esc_url(admin_url('admin.php?page=cod-crm-call-logs&search=' . rawurlencode($o['order_id']))); ?>">View Logs</a> | <a href="<?php echo esc_url(admin_url('admin.php?page=cod-crm-all-orders&edit=' . rawurlencode($o['order_id']))); ?>">Edit</a> | <?php wp_nonce_field('cod_crm_delete_order'); ?><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cod-crm-all-orders&delete_order=' . rawurlencode($o['order_id'])), 'cod_crm_delete_order')); ?>">Delete</a></td>
</tr>
<?php endforeach; endif; ?></tbody></table>
<?php if ($pages>1): ?><div class="tablenav"><div class="tablenav-pages"><?php for($i=1;$i<=$pages;$i++): ?><a class="button <?php echo $i===$paged?'button-primary':''; ?>" href="<?php echo esc_url(add_query_arg(['paged'=>$i])); ?>"><?php echo esc_html((string)$i); ?></a><?php endfor; ?></div></div><?php endif; ?>
</div>
