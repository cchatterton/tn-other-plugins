<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/fixtures/');
define('TNOP_FILE', '/plugins/tn-other-plugins/tn-other-plugins.php');
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function tnuc_registry() { return [['file' => 'tn-update-controller/tn-update-controller.php']]; }
function asuc_registry() { return [['file' => 'as-update-controller/as-update-controller.php']]; }
function fpl_selected_year() { return '26'; }
function fpl_catalog($year) { return ['plugins' => [['slug' => 'fusion-plugins'], ['name' => 'Vendor Pro']]]; }
function get_plugins() { return [
    'tn-other-plugins/tn-other-plugins.php' => ['Name' => 'TN Other Plugins'],
    'tn-update-controller/tn-update-controller.php' => ['Name' => 'TN Update Controller'],
    'as-update-controller/as-update-controller.php' => ['Name' => 'AS Update Controller'],
    'fusion-plugins/fusion-plugins.php' => ['Name' => 'Fusion Plugins'],
    'vendor-pro/main.php' => ['Name' => 'Vendor Pro'],
    'another-plugin/main.php' => ['Name' => 'Another Plugin'],
]; }
require dirname(__DIR__) . '/tn-other-plugins/includes/inventory.php';
$actual = array_keys(tnop_inventory());
if ($actual !== ['another-plugin/main.php']) { throw new RuntimeException('Unexpected inventory: ' . implode(', ', $actual)); }
echo "Inventory classification passed.\n";
