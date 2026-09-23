<?php
if (!defined('ABSPATH')) exit;

class IMP_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
    }

    public function menu() {
        add_menu_page(
            'Invoice Manager',
            'Invoices',
            'manage_options',
            'imp-invoices',
            array($this, 'page'),
            'dashicons-media-spreadsheet',
            26
        );
    }

    public function assets($hook) {
        if ($hook !== 'toplevel_page_imp-invoices') return;
        $this->enqueue_common();
    }

    public static function enqueue_common_assets() {
        wp_enqueue_style('imp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3');
        wp_enqueue_script('imp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.3', true);
        wp_enqueue_style('imp-css', IMP_URL . 'assets/css/invoice-manager.css', array(), IMP_VERSION);
        wp_enqueue_script('imp-js', IMP_URL . 'assets/js/invoice-manager.js', array('jquery'), IMP_VERSION, true);
        wp_localize_script('imp-js', 'IMP', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('imp_nonce'),
            'export_url' => wp_nonce_url(admin_url('admin-ajax.php?action=imp_export_csv'), 'imp_export_csv', 'nonce'),
            'currency' => apply_filters('imp_currency_symbol', '₱'),
            'branches' => apply_filters('imp_branches', array('Main Branch','Branch 2','Branch 3')),
            'units' => apply_filters('imp_units', array('pcs','box','kg','g','L','mL','set')),
            'i18n' => array(
                'confirmDelete' => 'Delete this invoice?',
                'error' => 'Something went wrong.',
            ),
        ));
    }

    private function enqueue_common() {
        self::enqueue_common_assets();
    }

    public function page() {
        ?>
        <div class="wrap">
            <div id="imp-app" class="imp-app">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h1 class="mb-1">Invoice Manager Pro</h1>
                        <p class="text-muted mb-0">Manage invoices, line items, totals, and exports.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-success" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=imp_export_csv'), 'imp_export_csv', 'nonce')); ?>">Export Excel/CSV</a>
                        <button class="btn btn-primary" id="imp-new-invoice">+ New Invoice</button>
                    </div>
                </div>
                <?php echo IMP_Shortcode::render_table(false); ?>
                <?php echo IMP_Shortcode::render_modal(); ?>
            </div>
        </div>
        <?php
    }
}
