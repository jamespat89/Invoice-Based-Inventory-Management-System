<?php
if (!defined('ABSPATH')) exit;

class IMP_DB {
    public static function invoices_table() {
        global $wpdb;
        return $wpdb->prefix . 'imp_invoices';
    }

    public static function items_table() {
        global $wpdb;
        return $wpdb->prefix . 'imp_invoice_items';
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $invoices = self::invoices_table();
        $items = self::items_table();

        $sql1 = "CREATE TABLE $invoices (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            invoice_date date NOT NULL,
            invoice_time time NOT NULL,
            branch varchar(100) NOT NULL DEFAULT '',
            total_amount decimal(18,2) NOT NULL DEFAULT 0.00,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY invoice_date (invoice_date),
            KEY branch (branch)
        ) $charset;";

        $sql2 = "CREATE TABLE $items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            item_name varchar(255) NOT NULL,
            amount decimal(18,2) NOT NULL DEFAULT 0.00,
            total_quantity decimal(18,3) NOT NULL DEFAULT 0.000,
            consumed_quantity decimal(18,3) NOT NULL DEFAULT 0.000,
            remaining_quantity decimal(18,3) NOT NULL DEFAULT 0.000,
            unit varchar(50) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        update_option('imp_db_version', IMP_VERSION);
    }

    public static function get_invoice($id) {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::invoices_table() . " WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$invoice) return null;

        $invoice['items'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::items_table() . " WHERE invoice_id = %d ORDER BY id ASC",
            $id
        ), ARRAY_A);

        return $invoice;
    }

    public static function invoice_exists($invoice_number, $exclude_id = 0) {
        global $wpdb;
        $sql = "SELECT id FROM " . self::invoices_table() . " WHERE invoice_number = %s";
        $args = array($invoice_number);

        if ($exclude_id) {
            $sql .= " AND id != %d";
            $args[] = $exclude_id;
        }

        return (bool) $wpdb->get_var($wpdb->prepare($sql, $args));
    }

    public static function list_invoices($search = '', $page = 1, $per_page = 10) {
        global $wpdb;
        $table = self::invoices_table();
        $offset = max(0, ($page - 1) * $per_page);

        $where = 'WHERE 1=1';
        $args = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (invoice_number LIKE %s OR branch LIKE %s)";
            $args[] = $like;
            $args[] = $like;
        }

        $count_sql = "SELECT COUNT(*) FROM $table $where";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $args));

        $sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $query_args = array_merge($args, array($per_page, $offset));
        $rows = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);

        return array(
            'rows' => $rows,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
            'page' => $page,
            'per_page' => $per_page,
        );
    }
}
