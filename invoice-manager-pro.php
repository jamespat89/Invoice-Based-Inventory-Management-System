<?php
/**
 * Plugin Name: Invoice Manager Pro
 * Description: WordPress invoice management using custom $wpdb tables, AJAX CRUD, repeatable line items, automatic totals, search, pagination, CSV/Excel-compatible export, and print/PDF support.
 * Version: 1.0.0
 * Author: James Patrick Jacob
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: invoice-manager-pro
 */

if (!defined('ABSPATH')) exit;

define('IMP_VERSION', '1.0.0');
define('IMP_FILE', __FILE__);
define('IMP_DIR', plugin_dir_path(__FILE__));
define('IMP_URL', plugin_dir_url(__FILE__));

require_once IMP_DIR . 'includes/class-imp-db.php';
require_once IMP_DIR . 'includes/class-imp-admin.php';
require_once IMP_DIR . 'includes/class-imp-ajax.php';
require_once IMP_DIR . 'includes/class-imp-shortcode.php';

register_activation_hook(__FILE__, array('IMP_DB', 'activate'));

function imp_boot() {
    new IMP_Admin();
    new IMP_AJAX();
    new IMP_Shortcode();
}
add_action('plugins_loaded', 'imp_boot');
