<?php
if (!empty($_POST['cod_crm_manual_submit']) && check_admin_referer('cod_crm_manual_import')) {
    $ok = COD_CRM_DB::insert_order([
        'order_id' => sanitize_text_field((string)$_POST['order_id']),
        'customer_name' => sanitize_text_field((string)$_POST['customer_name']),
        'customer_phone' => sanitize_text_field((string)$_POST['customer_phone']),
        'customer_address' => wp_kses_post((string)$_POST['customer_address']),
        'order_amount' => floatval($_POST['order_amount']),
        'order_items' => sanitize_text_field((string)$_POST['order_items']),
        'payment_method' => sanitize_text_field((string)$_POST['payment_method']),
        'assigned_agent_id' => intval($_POST['assigned_agent_id']),
        'source' => 'manual',
        'order_status' => 'pending_call',
    ]);
    if ($ok) {
        COD_CRM_Sync_Logger::log(sanitize_text_field((string)$_POST['order_id']), 'manual', 'success', 'Manual order inserted');
        echo '<div class="notice notice-success"><p>Order added.</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p>Duplicate/failed insert.</p></div>';
    }
}
?>
<div class="wrap cod-crm-wrap"><h1>Import Orders</h1>
<div class="cod-crm-card"><h2>Method 1 — Manual Entry</h2>
<form method="post">
<?php wp_nonce_field('cod_crm_manual_import'); ?>
<table class="form-table"><tbody>
<tr><th>Order ID</th><td><input type="text" name="order_id" required></td></tr>
<tr><th>Customer Name</th><td><input type="text" name="customer_name" required></td></tr>
<tr><th>Phone</th><td><input type="text" name="customer_phone" required></td></tr>
<tr><th>Address</th><td><textarea name="customer_address" required></textarea></td></tr>
<tr><th>Amount</th><td><input type="number" step="0.01" name="order_amount" required></td></tr>
<tr><th>Items</th><td><textarea name="order_items" required></textarea></td></tr>
<tr><th>Payment</th><td><select name="payment_method"><option value="cod">COD</option><option value="prepaid">Prepaid</option><option value="other">Other</option></select></td></tr>
<tr><th>Assign Agent</th><td><select name="assigned_agent_id"><option value="0">None</option><?php foreach(COD_CRM_DB::get_agents() as $a): ?><option value="<?php echo esc_attr((string)$a->ID); ?>"><?php echo esc_html($a->display_name); ?></option><?php endforeach; ?></select></td></tr>
</tbody></table>
<p><button class="button button-primary" name="cod_crm_manual_submit" value="1">Save Manual Order</button></p>
</form></div>
<div class="cod-crm-card"><h2>Method 2 — CSV Import</h2>
<p>Required columns: order_id, customer_name, customer_phone, customer_address, order_amount, order_items, payment_method</p>
<form id="cod-crm-csv-form" enctype="multipart/form-data">
<input type="file" name="csv_file" accept=".csv" required>
<button class="button" id="cod-crm-preview-csv" type="button">Preview CSV</button>
<button class="button button-primary" id="cod-crm-import-csv" type="button">Import CSV</button>
</form>
<div id="cod-crm-csv-preview"></div>
</div>
<div class="cod-crm-card"><h2>Method 3 — Bulk Past WooCommerce Orders Sync</h2>
<button class="button button-primary" id="cod-crm-start-bulk-sync">Sync Past WC Orders</button>
<div class="cod-crm-progress"><div class="cod-crm-progress-bar" id="cod-crm-progress-bar"></div></div>
<p id="cod-crm-progress-text"></p>
<p>Last sync: <strong><?php echo esc_html((string)get_option('cod_crm_last_bulk_sync', 'Never')); ?></strong></p>
</div></div>
