<?php
$filters = [
    'outcome' => sanitize_text_field((string)($_GET['outcome'] ?? '')),
    'agent_id' => intval($_GET['agent_id'] ?? 0),
    'date_from' => sanitize_text_field((string)($_GET['date_from'] ?? '')),
    'date_to' => sanitize_text_field((string)($_GET['date_to'] ?? '')),
    'search' => sanitize_text_field((string)($_GET['search'] ?? '')),
];
$logs = COD_CRM_DB::get_logs($filters, 500, 0);
$colors = ['confirmed'=>'#00a32a','cancelled'=>'#d63638','no_answer'=>'#dba617','call_not_picked'=>'#dba617','busy'=>'#dba617','switched_off'=>'#dba617','wrong_number'=>'#8c1a1a','callback_requested'=>'#0073aa','prepaid_converted'=>'#9b59b6'];
$export_url = wp_nonce_url(admin_url('admin-ajax.php?action=cod_crm_export_logs&' . http_build_query($filters)), 'cod_crm_nonce', 'nonce');
?>
<div class="wrap cod-crm-wrap"><h1>Call Logs</h1>
<form method="get" class="cod-crm-filters"><input type="hidden" name="page" value="cod-crm-call-logs">
<select name="outcome"><option value="">All Outcomes</option><?php foreach(array_keys($colors) as $o): ?><option value="<?php echo esc_attr($o); ?>" <?php selected($filters['outcome'],$o); ?>><?php echo esc_html($o); ?></option><?php endforeach; ?></select>
<select name="agent_id"><option value="0">All Agents</option><?php foreach (COD_CRM_DB::get_agents() as $a): ?><option value="<?php echo esc_attr((string)$a->ID); ?>" <?php selected($filters['agent_id'],$a->ID); ?>><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select>
<input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"><input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>"><input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Search order or phone"><button class="button">Filter</button>
<a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">Export CSV</a></form>
<table class="widefat striped"><thead><tr><th>Log ID</th><th>Order ID</th><th>Customer</th><th>Phone</th><th>Outcome</th><th>Reason</th><th>Duration</th><th>Agent</th><th>Attempt #</th><th>Called At</th></tr></thead><tbody>
<?php if (empty($logs)): ?><tr><td colspan="10">No logs found.</td></tr><?php else: foreach($logs as $l): $agent=get_user_by('id',intval($l['agent_id'])); ?>
<tr><td><?php echo esc_html((string)$l['id']); ?></td><td><?php echo esc_html($l['order_id']); ?></td><td><?php echo esc_html($l['customer_name']); ?></td><td><?php echo esc_html($l['customer_phone']); ?></td><td><span class="cod-crm-status-badge" style="background:<?php echo esc_attr($colors[$l['call_outcome']] ?? '#666'); ?>"><?php echo esc_html($l['call_outcome']); ?></span></td><td><?php echo esc_html($l['call_reason']); ?></td><td><?php echo esc_html((string)$l['call_duration_seconds']); ?></td><td><?php echo esc_html($agent?$agent->display_name:'System'); ?></td><td><?php echo esc_html((string)$l['attempt_number']); ?></td><td><?php echo esc_html($l['called_at']); ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div>
