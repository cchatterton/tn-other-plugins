<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/fixtures/');
$_POST = ['plugin' => 'first/main.php'];
function current_user_can($cap) { return $cap === 'update_plugins'; }
function is_multisite() { return false; }
function check_ajax_referer($action, $key, $die) { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return (string) $value; }
function tnop_inventory() { return [
    'first/main.php' => ['Name' => 'First'],
    'second/main.php' => ['Name' => 'Second'],
]; }
function tnop_update_items() { return [
    'first/main.php' => (object) ['package' => 'https://example.test/first.zip', 'new_version' => '2.0'],
    'second/main.php' => (object) ['package' => 'https://example.test/second.zip', 'new_version' => '2.0'],
]; }
function get_filesystem_method() { return 'direct'; }
function wp_clean_plugins_cache($clear) { if ($clear !== false) { throw new RuntimeException('Update cache should not be cleared twice.'); } }
function get_site_transient($key) { return (object) ['response' => tnop_update_items(), 'checked' => ['first/main.php' => '1.0']]; }
function set_site_transient($key, $value) { if (isset($value->response['first/main.php']) || $value->checked['first/main.php'] !== '2.0') { throw new RuntimeException('Successful update not removed from cache.'); } }
function get_plugins() { return [
    'first/main.php' => ['Version' => '2.0'],
    'second/main.php' => ['Version' => '1.0'],
]; }
class WP_Error { public function get_error_message() { return 'Download failed.'; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
class JsonResponse extends RuntimeException { public $data; public function __construct($data) { $this->data = $data; } }
function wp_send_json_success($data) { throw new JsonResponse($data); }
function wp_send_json_error($data, $code) { throw new RuntimeException($data['message']); }
require dirname(__DIR__) . '/tn-other-plugins/includes/admin.php';
try { tnop_update_selected_ajax(); }
catch (JsonResponse $response) {
    $item = $response->data['item'];
    if ($item['status'] !== 'success' || $item['message'] !== 'Updated to 2.0.') {
        throw new RuntimeException('Incorrect bulk update result.');
    }
    $_POST['plugin'] = 'second/main.php';
    try { tnop_update_selected_ajax(); }
    catch (JsonResponse $failure) {
        $item = $failure->data['item'];
        if ($item['status'] !== 'failed' || $item['message'] !== 'Download failed.') {
            throw new RuntimeException('Incorrect failure result.');
        }
        echo "Bulk update reporting passed.\n";
        exit;
    }
}
throw new RuntimeException('Expected a JSON response.');
