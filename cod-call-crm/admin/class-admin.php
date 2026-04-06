<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Admin {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    private static function capability_for(string $page): string {
        $agent_pages = ['dashboard', 'call-queue', 'call-logs'];
        return in_array($page, $agent_pages, true) ? 'read' : 'manage_woocommerce';
    }

    public static function register_menu(): void {
        add_menu_page(__('COD CRM', 'cod-call-crm'), __('COD CRM', 'cod-call-crm'), 'read', 'cod-crm-dashboard', [__CLASS__, 'render_dashboard'], 'dashicons-phone', 56);
        add_submenu_page('cod-crm-dashboard', __('Dashboard', 'cod-call-crm'), __('Dashboard', 'cod-call-crm'), 'read', 'cod-crm-dashboard', [__CLASS__, 'render_dashboard']);
        add_submenu_page('cod-crm-dashboard', __('Call Queue', 'cod-call-crm'), __('Call Queue', 'cod-call-crm'), 'read', 'cod-crm-call-queue', [__CLASS__, 'render_call_queue']);
        add_submenu_page('cod-crm-dashboard', __('All Orders', 'cod-call-crm'), __('All Orders', 'cod-call-crm'), 'manage_woocommerce', 'cod-crm-all-orders', [__CLASS__, 'render_all_orders']);
        add_submenu_page('cod-crm-dashboard', __('Call Logs', 'cod-call-crm'), __('Call Logs', 'cod-call-crm'), 'read', 'cod-crm-call-logs', [__CLASS__, 'render_call_logs']);
        add_submenu_page('cod-crm-dashboard', __('Reports & Analytics', 'cod-call-crm'), __('Reports & Analytics', 'cod-call-crm'), 'manage_woocommerce', 'cod-crm-reports', [__CLASS__, 'render_reports']);
        add_submenu_page('cod-crm-dashboard', __('Import Orders', 'cod-call-crm'), __('Import Orders', 'cod-call-crm'), 'manage_woocommerce', 'cod-crm-import', [__CLASS__, 'render_import']);
        add_submenu_page('cod-crm-dashboard', __('Settings', 'cod-call-crm'), __('Settings', 'cod-call-crm'), 'manage_woocommerce', 'cod-crm-settings', [__CLASS__, 'render_settings']);
    }

    public static function enqueue(string $hook): void {
        if (strpos($hook, 'cod-crm') === false && strpos($hook, 'woocommerce_page_wc-orders') === false && strpos($hook, 'shop_order') === false) {
            return;
        }
        wp_enqueue_style('cod-crm-admin', COD_CRM_URL . 'admin/css/admin.css', [], COD_CRM_VERSION);
        wp_enqueue_script('cod-crm-admin', COD_CRM_URL . 'admin/js/admin.js', ['jquery'], COD_CRM_VERSION, true);
        wp_enqueue_script('cod-crm-bulk-sync', COD_CRM_URL . 'admin/js/bulk-sync.js', ['jquery'], COD_CRM_VERSION, true);
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', [], '4.4.1', true);

        wp_localize_script('cod-crm-admin', 'codCRM', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cod_crm_nonce'),
            'currentUser' => get_current_user_id(),
        ]);
    }

    private static function gate(string $page): void {
        if (!current_user_can(self::capability_for($page))) {
            wp_die(esc_html__('You do not have permission.', 'cod-call-crm'));
        }
    }

    public static function render_dashboard(): void { self::gate('dashboard'); include COD_CRM_PATH . 'admin/views/dashboard.php'; }
    public static function render_call_queue(): void { self::gate('call-queue'); include COD_CRM_PATH . 'admin/views/call-queue.php'; }
    public static function render_all_orders(): void { self::gate('all-orders'); include COD_CRM_PATH . 'admin/views/all-orders.php'; }
    public static function render_call_logs(): void { self::gate('call-logs'); include COD_CRM_PATH . 'admin/views/call-logs.php'; }
    public static function render_reports(): void { self::gate('reports'); include COD_CRM_PATH . 'admin/views/reports.php'; }
    public static function render_import(): void { self::gate('import'); include COD_CRM_PATH . 'admin/views/import.php'; }
    public static function render_settings(): void { self::gate('settings'); include COD_CRM_PATH . 'admin/views/settings.php'; }
}
