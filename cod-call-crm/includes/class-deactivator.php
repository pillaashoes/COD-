<?php
if (!defined('ABSPATH')) {
    exit;
}

class COD_CRM_Deactivator {
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
