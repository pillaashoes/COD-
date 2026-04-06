<?php $entries = COD_CRM_Sync_Logger::get_entries(); ?>
<div class="cod-crm-card">
    <h2>Sync Log</h2>
    <p><button class="button" id="cod-crm-clear-sync-log">Clear Log</button></p>
    <table class="widefat striped">
        <thead><tr><th>Order ID</th><th>Type</th><th>Status</th><th>Message</th><th>Timestamp</th></tr></thead>
        <tbody>
        <?php if (empty($entries)): ?>
            <tr><td colspan="5">No sync events found.</td></tr>
        <?php else: foreach ($entries as $e): ?>
            <tr>
                <td><?php echo esc_html($e['order_id']); ?></td>
                <td><?php echo esc_html($e['sync_type']); ?></td>
                <td><?php echo esc_html($e['status']); ?></td>
                <td><?php echo esc_html($e['message']); ?></td>
                <td><?php echo esc_html($e['timestamp']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
