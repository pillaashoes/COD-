<?php
$tab = sanitize_text_field((string)($_GET['tab'] ?? 'general'));
if (!empty($_POST['cod_crm_save_settings']) && check_admin_referer('cod_crm_save_settings')) {
    $fields = [
        'cod_crm_brand_name' => sanitize_text_field((string)($_POST['cod_crm_brand_name'] ?? '')),
        'cod_crm_whatsapp_support_number' => sanitize_text_field((string)($_POST['cod_crm_whatsapp_support_number'] ?? '')),
        'cod_crm_max_attempts' => intval($_POST['cod_crm_max_attempts'] ?? 3),
        'cod_crm_default_assignment' => sanitize_text_field((string)($_POST['cod_crm_default_assignment'] ?? 'round_robin')),
        'cod_crm_script' => wp_kses_post((string)($_POST['cod_crm_script'] ?? '')),
        'cod_crm_notify_admin_confirmed' => isset($_POST['cod_crm_notify_admin_confirmed']) ? 1 : 0,
        'cod_crm_notify_admin_cancelled' => isset($_POST['cod_crm_notify_admin_cancelled']) ? 1 : 0,
        'cod_crm_notify_admin_max_attempts' => isset($_POST['cod_crm_notify_admin_max_attempts']) ? 1 : 0,
        'cod_crm_notify_agent_new_order' => isset($_POST['cod_crm_notify_agent_new_order']) ? 1 : 0,
        'cod_crm_whatsapp_webhook_url' => esc_url_raw((string)($_POST['cod_crm_whatsapp_webhook_url'] ?? '')),
        'cod_crm_realtime_sync_enabled' => isset($_POST['cod_crm_realtime_sync_enabled']) ? 1 : 0,
        'cod_crm_sync_cod' => isset($_POST['cod_crm_sync_cod']) ? 1 : 0,
        'cod_crm_sync_prepaid' => isset($_POST['cod_crm_sync_prepaid']) ? 1 : 0,
        'cod_crm_sync_other' => isset($_POST['cod_crm_sync_other']) ? 1 : 0,
        'cod_crm_auto_round_robin' => isset($_POST['cod_crm_auto_round_robin']) ? 1 : 0,
        'cod_crm_email_on_assignment' => isset($_POST['cod_crm_email_on_assignment']) ? 1 : 0,
        'cod_crm_whatsapp_on_assignment' => isset($_POST['cod_crm_whatsapp_on_assignment']) ? 1 : 0,
    ];
    foreach ($fields as $k => $v) {
        update_option($k, $v);
    }
    echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
}

if (!empty($_POST['cod_crm_add_agent']) && check_admin_referer('cod_crm_manage_agents')) {
    $existing = intval($_POST['existing_user_id'] ?? 0);
    if ($existing) {
        $u = get_user_by('id', $existing);
        if ($u) {
            $u->add_role('cod_agent');
        }
    } else {
        $user_id = wp_create_user(
            sanitize_user((string)($_POST['new_agent_email'] ?? '')),
            sanitize_text_field((string)($_POST['new_agent_password'] ?? '')),
            sanitize_email((string)($_POST['new_agent_email'] ?? ''))
        );
        if (!is_wp_error($user_id)) {
            wp_update_user(['ID' => $user_id, 'display_name' => sanitize_text_field((string)($_POST['new_agent_name'] ?? ''))]);
            $u = get_user_by('id', $user_id);
            if ($u) {
                $u->set_role('cod_agent');
            }
        }
    }
}

if (!empty($_GET['remove_agent']) && check_admin_referer('cod_crm_remove_agent')) {
    $u = get_user_by('id', intval($_GET['remove_agent']));
    if ($u) {
        $u->remove_role('cod_agent');
    }
}

$agents = COD_CRM_DB::get_agents();
$tabs = ['general'=>'General','script'=>'Call Script Editor','agents'=>'Agent Management','notifications'=>'Notifications','sync'=>'Sync Settings','sync-log'=>'Sync Log'];
?>
<div class="wrap cod-crm-wrap"><h1>Settings</h1>
<h2 class="nav-tab-wrapper"><?php foreach($tabs as $k=>$label): ?><a class="nav-tab <?php echo $tab===$k?'nav-tab-active':''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=cod-crm-settings&tab='.$k)); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></h2>
<form method="post"><?php wp_nonce_field('cod_crm_save_settings'); ?><input type="hidden" name="cod_crm_save_settings" value="1">
<?php if($tab==='general'): ?>
<table class="form-table"><tr><th>Brand Name</th><td><input name="cod_crm_brand_name" value="<?php echo esc_attr((string)get_option('cod_crm_brand_name')); ?>"></td></tr><tr><th>WhatsApp Support Number</th><td><input name="cod_crm_whatsapp_support_number" value="<?php echo esc_attr((string)get_option('cod_crm_whatsapp_support_number')); ?>"></td></tr><tr><th>Max Call Attempts</th><td><input type="number" name="cod_crm_max_attempts" value="<?php echo esc_attr((string)get_option('cod_crm_max_attempts',3)); ?>"></td></tr><tr><th>Default Agent Assignment</th><td><label><input type="radio" name="cod_crm_default_assignment" value="round_robin" <?php checked(get_option('cod_crm_default_assignment'),'round_robin'); ?>> Round-Robin</label> <label><input type="radio" name="cod_crm_default_assignment" value="manual" <?php checked(get_option('cod_crm_default_assignment'),'manual'); ?>> Manual</label></td></tr></table>
<?php elseif($tab==='script'): ?>
<table class="form-table"><tr><th>Call Script</th><td><textarea name="cod_crm_script" rows="14" class="large-text"><?php echo esc_textarea((string)get_option('cod_crm_script')); ?></textarea><p>Shortcodes: {customer_name}, {product_name}, {amount}, {delivery_address}, {order_id}</p></td></tr></table>
<?php elseif($tab==='agents'): ?>
<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Assigned Orders</th><th>Calls Made</th><th>Actions</th></tr></thead><tbody><?php global $wpdb; $ot=COD_CRM_DB::orders_table(); $lt=COD_CRM_DB::logs_table(); foreach($agents as $a): $assigned=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ot} WHERE assigned_agent_id=%d",$a->ID)); $calls=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lt} WHERE agent_id=%d",$a->ID)); ?><tr><td><?php echo esc_html($a->display_name); ?></td><td><?php echo esc_html($a->user_email); ?></td><td><?php echo esc_html((string)$assigned); ?></td><td><?php echo esc_html((string)$calls); ?></td><td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cod-crm-settings&tab=agents&remove_agent=' . $a->ID),'cod_crm_remove_agent')); ?>">Remove Agent</a></td></tr><?php endforeach; ?></tbody></table>
<h3>Add New Agent</h3><?php wp_nonce_field('cod_crm_manage_agents'); ?><input type="hidden" name="cod_crm_add_agent" value="1"><p><select name="existing_user_id"><option value="0">Select existing user</option><?php foreach(get_users(['role__not_in'=>['cod_agent']]) as $u): ?><option value="<?php echo esc_attr((string)$u->ID); ?>"><?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?></option><?php endforeach; ?></select></p><p>Or create new:</p><p><input name="new_agent_name" placeholder="Name"> <input name="new_agent_email" placeholder="Email"> <input name="new_agent_password" placeholder="Password"></p>
<?php elseif($tab==='notifications'): ?>
<p><label><input type="checkbox" name="cod_crm_notify_admin_confirmed" <?php checked(get_option('cod_crm_notify_admin_confirmed'),1); ?>> Email to admin on order confirmed</label></p>
<p><label><input type="checkbox" name="cod_crm_notify_admin_cancelled" <?php checked(get_option('cod_crm_notify_admin_cancelled'),1); ?>> Email to admin on order cancelled</label></p>
<p><label><input type="checkbox" name="cod_crm_notify_admin_max_attempts" <?php checked(get_option('cod_crm_notify_admin_max_attempts'),1); ?>> Email to admin on max attempts reached</label></p>
<p><label><input type="checkbox" name="cod_crm_notify_agent_new_order" <?php checked(get_option('cod_crm_notify_agent_new_order'),1); ?>> Email to agent on new assignment</label></p>
<p><input class="regular-text" name="cod_crm_whatsapp_webhook_url" value="<?php echo esc_attr((string)get_option('cod_crm_whatsapp_webhook_url')); ?>" placeholder="WhatsApp webhook URL"></p>
<?php elseif($tab==='sync'): ?>
<p><label><input type="checkbox" name="cod_crm_realtime_sync_enabled" <?php checked(get_option('cod_crm_realtime_sync_enabled'),1); ?>> Enable real-time auto sync</label></p>
<p><label><input type="checkbox" name="cod_crm_sync_cod" <?php checked(get_option('cod_crm_sync_cod'),1); ?>> Sync COD orders</label></p>
<p><label><input type="checkbox" name="cod_crm_sync_prepaid" <?php checked(get_option('cod_crm_sync_prepaid'),1); ?>> Sync prepaid orders</label></p>
<p><label><input type="checkbox" name="cod_crm_sync_other" <?php checked(get_option('cod_crm_sync_other'),1); ?>> Sync other payment methods</label></p>
<p><label><input type="checkbox" name="cod_crm_auto_round_robin" <?php checked(get_option('cod_crm_auto_round_robin'),1); ?>> Auto round-robin assignment</label></p>
<p><label><input type="checkbox" name="cod_crm_email_on_assignment" <?php checked(get_option('cod_crm_email_on_assignment'),1); ?>> Send email on assignment</label></p>
<p><label><input type="checkbox" name="cod_crm_whatsapp_on_assignment" <?php checked(get_option('cod_crm_whatsapp_on_assignment'),1); ?>> Send WhatsApp on assignment</label></p>
<p>Sync Past Orders: <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cod-crm-import')); ?>">Go to Import page</a></p><p>Last sync: <strong><?php echo esc_html((string)get_option('cod_crm_last_bulk_sync','Never')); ?></strong></p>
<?php elseif($tab==='sync-log'): include COD_CRM_PATH . 'admin/views/sync-log.php'; endif; ?>
<?php if($tab!=='sync-log'): ?><p><button class="button button-primary">Save Settings</button></p><?php endif; ?>
</form></div>
