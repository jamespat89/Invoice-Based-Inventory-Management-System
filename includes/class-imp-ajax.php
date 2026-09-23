<?php
if (!defined('ABSPATH')) exit;

class IMP_AJAX {
    public function __construct() {
        add_action('wp_ajax_imp_list_invoices', array($this, 'list_invoices'));
        add_action('wp_ajax_imp_get_invoice', array($this, 'get_invoice'));
        add_action('wp_ajax_imp_save_invoice', array($this, 'save_invoice'));
        add_action('wp_ajax_imp_delete_invoice', array($this, 'delete_invoice'));
        add_action('wp_ajax_imp_export_csv', array($this, 'export_csv'));
        add_action('wp_ajax_imp_print_invoice', array($this, 'print_invoice'));
    }

    private function guard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'), 403);
        }
        check_ajax_referer('imp_nonce', 'nonce');
    }

    private function clean_items($items) {
        $out = array();

        if (!is_array($items)) return $out;

        foreach ($items as $item) {
            $name = isset($item['item_name']) ? sanitize_text_field(wp_unslash($item['item_name'])) : '';
            if ($name === '') continue;

            $amount = isset($item['amount']) ? (float) $item['amount'] : 0;
            $total_qty = isset($item['total_quantity']) ? (float) $item['total_quantity'] : 0;
            $consumed = isset($item['consumed_quantity']) ? (float) $item['consumed_quantity'] : 0;
            $remaining = $total_qty - $consumed;

            $out[] = array(
                'item_name' => $name,
                'amount' => max(0, $amount),
                'total_quantity' => max(0, $total_qty),
                'consumed_quantity' => max(0, $consumed),
                'remaining_quantity' => $remaining,
                'unit' => isset($item['unit']) ? sanitize_text_field(wp_unslash($item['unit'])) : '',
            );
        }

        return $out;
    }

    public function list_invoices() {
        $this->guard();

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, max(1, absint($_POST['per_page']))) : 10;

        wp_send_json_success(IMP_DB::list_invoices($search, $page, $per_page));
    }

    public function get_invoice() {
        $this->guard();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $invoice = IMP_DB::get_invoice($id);

        if (!$invoice) {
            wp_send_json_error(array('message' => 'Invoice not found.'), 404);
        }

        wp_send_json_success($invoice);
    }

    public function save_invoice() {
        $this->guard();

        global $wpdb;
        $invoices = IMP_DB::invoices_table();
        $items_table = IMP_DB::items_table();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $invoice_number = isset($_POST['invoice_number']) ? sanitize_text_field(wp_unslash($_POST['invoice_number'])) : '';
        $invoice_date = isset($_POST['invoice_date']) ? sanitize_text_field(wp_unslash($_POST['invoice_date'])) : '';
        $invoice_time = isset($_POST['invoice_time']) ? sanitize_text_field(wp_unslash($_POST['invoice_time'])) : '';
        $branch = isset($_POST['branch']) ? sanitize_text_field(wp_unslash($_POST['branch'])) : '';
        $items = $this->clean_items(isset($_POST['items']) ? $_POST['items'] : array());

        if ($invoice_number === '' || $invoice_date === '' || $invoice_time === '') {
            wp_send_json_error(array('message' => 'Invoice number, date and time are required.'), 422);
        }

        if (!$items) {
            wp_send_json_error(array('message' => 'Add at least one invoice item.'), 422);
        }

        if (IMP_DB::invoice_exists($invoice_number, $id)) {
            wp_send_json_error(array('message' => 'That invoice number already exists.'), 409);
        }

        $total = 0;
        foreach ($items as $item) {
            $total += $item['amount'] * $item['total_quantity'];
        }

        $now = current_time('mysql');
        $data = array(
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'invoice_time' => $invoice_time,
            'branch' => $branch,
            'total_amount' => $total,
            'updated_at' => $now,
        );

        $wpdb->query('START TRANSACTION');

        try {
            if ($id) {
                $existing = IMP_DB::get_invoice($id);
                if (!$existing) throw new Exception('Invoice not found.');

                $updated = $wpdb->update($invoices, $data, array('id' => $id), array('%s','%s','%s','%s','%f','%s'), array('%d'));
                if ($updated === false) throw new Exception('Could not update invoice.');

                $wpdb->delete($items_table, array('invoice_id' => $id), array('%d'));
                $invoice_id = $id;
            } else {
                $data['created_by'] = get_current_user_id();
                $data['created_at'] = $now;

                $inserted = $wpdb->insert(
                    $invoices,
                    $data,
                    array('%s','%s','%s','%s','%f','%s','%d','%s')
                );
                if (!$inserted) throw new Exception('Could not save invoice.');
                $invoice_id = (int) $wpdb->insert_id;
            }

            foreach ($items as $item) {
                $ok = $wpdb->insert(
                    $items_table,
                    array(
                        'invoice_id' => $invoice_id,
                        'item_name' => $item['item_name'],
                        'amount' => $item['amount'],
                        'total_quantity' => $item['total_quantity'],
                        'consumed_quantity' => $item['consumed_quantity'],
                        'remaining_quantity' => $item['remaining_quantity'],
                        'unit' => $item['unit'],
                    ),
                    array('%d','%s','%f','%f','%f','%f','%s')
                );
                if (!$ok) throw new Exception('Could not save an invoice item.');
            }

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()), 500);
        }

        wp_send_json_success(array(
            'message' => $id ? 'Invoice updated successfully.' : 'Invoice saved successfully.',
            'invoice' => IMP_DB::get_invoice($invoice_id),
        ));
    }

    public function delete_invoice() {
        $this->guard();

        global $wpdb;
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id || !IMP_DB::get_invoice($id)) {
            wp_send_json_error(array('message' => 'Invoice not found.'), 404);
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->delete(IMP_DB::items_table(), array('invoice_id' => $id), array('%d'));
        $deleted = $wpdb->delete(IMP_DB::invoices_table(), array('id' => $id), array('%d'));

        if ($deleted) {
            $wpdb->query('COMMIT');
            wp_send_json_success(array('message' => 'Invoice deleted successfully.'));
        }

        $wpdb->query('ROLLBACK');
        wp_send_json_error(array('message' => 'Could not delete invoice.'), 500);
    }

    public function export_csv() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized', '', array('response' => 403));
        check_admin_referer('imp_export_csv', 'nonce');

        global $wpdb;
        $rows = IMP_DB::list_invoices('', 1, 100000)['rows'];

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoices-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('Invoice Number','Date','Time','Branch','Total Amount'));

        foreach ($rows as $row) {
            fputcsv($out, array(
                $row['invoice_number'],
                $row['invoice_date'],
                $row['invoice_time'],
                $row['branch'],
                $row['total_amount'],
            ));
        }
        fclose($out);
        exit;
    }

    public function print_invoice() {
        $this->guard();
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $invoice = IMP_DB::get_invoice($id);
        if (!$invoice) wp_die('Invoice not found.');

        $currency = apply_filters('imp_currency_symbol', '₱');
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice <?php echo esc_html($invoice['invoice_number']); ?></title>
            <style>
                body{font-family:Arial,sans-serif;margin:40px;color:#222}
                h1{margin-bottom:5px}.meta{margin-bottom:25px}
                table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:left}
                th{background:#f3f3f3}.num{text-align:right}
                .total{font-size:20px;font-weight:700;text-align:right;margin-top:20px}
                @media print{button{display:none}}
            </style>
        </head>
        <body>
            <button onclick="window.print()">Print / Save as PDF</button>
            <h1>Invoice</h1>
            <div class="meta">
                <strong>Invoice #:</strong> <?php echo esc_html($invoice['invoice_number']); ?><br>
                <strong>Date:</strong> <?php echo esc_html($invoice['invoice_date']); ?><br>
                <strong>Time:</strong> <?php echo esc_html($invoice['invoice_time']); ?><br>
                <strong>Branch:</strong> <?php echo esc_html($invoice['branch']); ?>
            </div>
            <table>
                <thead><tr><th>Item</th><th>Amount</th><th>Total Qty</th><th>Consumed</th><th>Remaining</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['item_name']); ?></td>
                        <td class="num"><?php echo esc_html(number_format((float)$item['amount'],2)); ?></td>
                        <td class="num"><?php echo esc_html($item['total_quantity']); ?></td>
                        <td class="num"><?php echo esc_html($item['consumed_quantity']); ?></td>
                        <td class="num"><?php echo esc_html($item['remaining_quantity']); ?></td>
                        <td><?php echo esc_html($item['unit']); ?></td>
                        <td class="num"><?php echo esc_html($currency . number_format((float)$item['amount'] * (float)$item['total_quantity'],2)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Grand Total: <?php echo esc_html($currency . number_format((float)$invoice['total_amount'],2)); ?></div>
            <script>window.onload=function(){setTimeout(function(){window.print()},300)}</script>
        </body>
        </html>
        <?php
        exit;
    }
}
