<?php
if (!defined('ABSPATH')) exit;

class IMP_Shortcode {
    public function __construct() {
        add_shortcode('invoice_manager', array($this, 'shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_assets'));
    }

    public function maybe_assets() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'invoice_manager')) {
            IMP_Admin::enqueue_common_assets();
        }
    }

    public function shortcode() {
        if (!current_user_can('manage_options')) {
            return '<div class="alert alert-warning">You do not have permission to access the invoice manager.</div>';
        }

        ob_start();
        ?>
        <div id="imp-app" class="imp-app">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div><h2 class="mb-1">Invoice Manager</h2><p class="text-muted mb-0">Create and manage invoices.</p></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-success" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=imp_export_csv'), 'imp_export_csv', 'nonce')); ?>">Export Excel/CSV</a>
                    <button class="btn btn-primary" id="imp-new-invoice">+ New Invoice</button>
                </div>
            </div>
            <?php echo self::render_table(true); ?>
            <?php echo self::render_modal(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_table($frontend = true) {
        ob_start(); ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <input type="search" id="imp-search" class="form-control" placeholder="Search invoice number or branch...">
                    </div>
                    <div class="col-md-2">
                        <select id="imp-per-page" class="form-select">
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="imp-table">
                        <thead>
                            <tr><th>Invoice #</th><th>Date</th><th>Time</th><th>Branch</th><th class="text-end">Total</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody><tr><td colspan="6" class="text-center py-4">Loading...</td></tr></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="imp-summary" class="text-muted"></small>
                    <nav><ul class="pagination mb-0" id="imp-pagination"></ul></nav>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public static function render_modal() {
        ob_start(); ?>
        <div class="modal fade" id="imp-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imp-modal-title">New Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="imp-form">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="imp-id" value="">
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Invoice Number *</label>
                                    <input type="text" class="form-control" name="invoice_number" id="imp-invoice-number" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date *</label>
                                    <input type="date" class="form-control" name="invoice_date" id="imp-invoice-date" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Time *</label>
                                    <input type="time" class="form-control" name="invoice_time" id="imp-invoice-time" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Branch</label>
                                    <select class="form-select" name="branch" id="imp-branch"></select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Invoice Items</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="imp-add-item">+ Add Item</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="imp-items-table">
                                    <thead>
                                        <tr>
                                            <th>Item Name *</th><th>Amount *</th><th>Total Qty *</th><th>Consumed Qty</th><th>Remaining</th><th>Unit</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div class="row justify-content-end mt-3">
                                <div class="col-md-4">
                                    <div class="imp-grand-total">
                                        <span>Grand Total</span>
                                        <strong id="imp-grand-total">₱0.00</strong>
                                    </div>
                                </div>
                            </div>
                            <div id="imp-form-message" class="mt-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="imp-save">Save Invoice</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
