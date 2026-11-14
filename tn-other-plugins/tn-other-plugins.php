<?php
/**
 * Plugin Name: TN Other Plugins
 * Description: Review and update installed plugins outside the Techn, AlphaSys and Fusion catalogues.
 * Version: 0.1.5
 * Author: Techn
 * Author URI: https://techn.com.au
 * Update URI: https://github.com/cchatterton/tn-other-plugins
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tn-other-plugins
 * Techn Controller API: 1
 */
if (!defined('ABSPATH')) { exit; }

define('TNOP_VERSION', '0.1.5');
define('TNOP_FILE', __FILE__);
if (wp_doing_ajax() && isset($_REQUEST['action']) && 'tnop_check' === $_REQUEST['action']) {
    ob_start();
    define('TNOP_AJAX_BUFFER_LEVEL', ob_get_level());
}
require_once __DIR__ . '/includes/controller-client.php';
tnuc_client_register(__FILE__, 'tn-other-plugins');
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/admin.php';

add_action('admin_menu', 'tnop_register_menu', PHP_INT_MAX);
add_action('network_admin_menu', 'tnop_register_menu', PHP_INT_MAX);
add_action('admin_enqueue_scripts', 'tnop_enqueue_assets');
add_action('admin_post_tnop_check', 'tnop_check_updates');
add_action('wp_ajax_tnop_check', 'tnop_check_updates_ajax');
add_filter('plugin_action_links_' . plugin_basename(TNOP_FILE), 'tnop_action_links');
add_filter('network_admin_plugin_action_links_' . plugin_basename(TNOP_FILE), 'tnop_action_links');
