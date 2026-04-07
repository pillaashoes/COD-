# COD Order CRM for WooCommerce

Production-ready WordPress plugin for COD footwear ecommerce call-center workflows.

## Features

- Dedicated **Order CRM** admin screen with spreadsheet-like rows.
- Inline AJAX editing for:
  - Call status
  - Confirmation status
  - Cancellation reason
  - Follow-up datetime
  - Priority
  - Notes
- Call action button (`tel:`) that auto-increments call attempts and updates last call time.
- Filters/search:
  - Call status
  - Confirmation status
  - Date range
  - City
  - High-value orders (> ₹2000)
  - Due follow-ups (today)
  - Search by order ID or phone
- Row highlighting for due follow-ups today.
- Color-coded confirmation status (green/yellow/red).
- Duplicate phone risk flag.
- Bulk update actions.
- CSV export including WooCommerce + CRM metadata.
- Performance-conscious data access via custom SQL joins + paginated AJAX fetch.
- Security: nonce verification + role capability (`administrator`, `shop_manager`).

## Installation

1. Copy plugin folder into your WordPress site:
   - `wp-content/plugins/cod-order-crm/`
2. Ensure WooCommerce is active.
3. Activate **COD Order CRM for WooCommerce** from WordPress plugins screen.
4. Open **WordPress Admin → Order CRM**.

## Plugin Structure

```text
cod-order-crm.php
includes/
  class-cod-order-crm-capabilities.php
  class-cod-order-crm-meta.php
  class-cod-order-crm-repository.php
  class-cod-order-crm-exporter.php
admin/
  class-cod-order-crm-admin.php
assets/js/
  admin.js
assets/css/
  admin.css
samples/
  sample-export.csv
```

## Extendability

The codebase is modular for future integrations:
- Add WhatsApp automation hooks after update actions.
- Add IVR callbacks by extending `ajax_call_now` or creating REST endpoints.
- Add scheduled reminder jobs with WP-Cron using `follow_up_date`.

## Notes for Production

- For very large stores, add DB indexes on frequently queried meta keys:
  - `_billing_phone`, `_billing_city`, `_order_total`, `call_status`, `confirmation_status`, `follow_up_date`.
- Test on staging before rolling out to live call teams.
