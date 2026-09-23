INVOICE MANAGER PRO
====================

Version: 1.0.0

WHAT IT DOES
------------
- Custom WordPress $wpdb tables for invoices and invoice line items.
- Invoice number, date, time and branch.
- Repeatable invoice items.
- Item amount, total quantity, consumed quantity, remaining quantity and unit.
- Remaining quantity is calculated as total quantity - consumed quantity.
- Grand total is calculated as amount x total quantity for each line.
- AJAX create, read, update and delete without page reload.
- Server-side nonce and capability checks.
- Duplicate invoice-number protection.
- Database transaction for invoice + line items.
- Search and pagination.
- Bootstrap 5 UI and modal editing.
- Excel-compatible CSV export.
- Print invoice / browser Save as PDF.
- Frontend shortcode: [invoice_manager]

INSTALLATION
------------
1. Upload invoice-manager-pro.zip in WordPress:
   Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open WordPress Dashboard > Invoices.
4. Or create a page and place:
   [invoice_manager]

CUSTOMIZE BRANCHES / UNITS
--------------------------
Use WordPress filters:

add_filter('imp_branches', function($branches) {
    return array('Main Branch', 'Angeles Branch', 'Manila Branch');
});

add_filter('imp_units', function($units) {
    return array('pcs', 'box', 'kg', 'liter');
});

CURRENCY
--------
add_filter('imp_currency_symbol', function() {
    return '₱';
});

NOTES
-----
The "Excel/CSV" button creates a CSV file that opens directly in Microsoft Excel,
LibreOffice Calc, Google Sheets, etc.

The PDF button opens a print-ready invoice. In the browser print dialog, select
"Save as PDF" to create a PDF without requiring a third-party PHP PDF library.

SECURITY
--------
The AJAX endpoints require the manage_options capability and a WordPress nonce.
Invoice numbers are checked server-side for duplicates.

PRODUCTION HARDENING
--------------------
For a public-facing multi-role deployment, replace manage_options with a custom
capability and add role-specific permissions. You may also add audit logs,
customer/vendor fields, tax/discount fields, payment status, invoice templates,
and true XLSX/PDF libraries as needed.
