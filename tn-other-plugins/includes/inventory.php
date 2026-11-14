<?php
if (!defined('ABSPATH')) { exit; }

function tnop_managed_files(): array {
    $files = [plugin_basename(TNOP_FILE) => true];
    foreach (['tnuc_registry', 'asuc_registry'] as $registry) {
        if (!function_exists($registry)) { continue; }
        foreach ($registry() as $entry) {
            if (is_array($entry) && is_string($entry['file'] ?? null)) { $files[$entry['file']] = true; }
        }
    }
    if (function_exists('fpl_catalog') && function_exists('fpl_selected_year')) {
        foreach (fpl_catalog(fpl_selected_year())['plugins'] ?? [] as $entry) {
            if (!is_array($entry)) { continue; }
            if (!empty($entry['slug'])) { $files['slug:' . $entry['slug']] = true; }
            elseif (!empty($entry['name'])) { $files['name:' . strtolower($entry['name'])] = true; }
        }
    }
    return $files;
}

function tnop_inventory(): array {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $managed = tnop_managed_files();
    $plugins = [];
    foreach (get_plugins() as $file => $plugin) {
        if (isset($managed[$file]) || isset($managed['slug:' . dirname($file)]) || isset($managed['name:' . strtolower($plugin['Name'] ?? '')])) { continue; }
        $plugins[$file] = $plugin;
    }
    uasort($plugins, static function ($left, $right) { return strcasecmp($left['Name'] ?? '', $right['Name'] ?? ''); });
    return $plugins;
}

function tnop_update_items(): array {
    $state = get_site_transient('update_plugins');
    return is_object($state) && is_array($state->response ?? null) ? $state->response : [];
}

function tnop_is_active(string $file): bool {
    return is_plugin_active($file) || (is_multisite() && is_plugin_active_for_network($file));
}

function tnop_wporg_slug(string $file): string {
    $state = get_site_transient('update_plugins');
    foreach (['response', 'no_update'] as $group) {
        $item = is_object($state) && is_array($state->$group ?? null) ? ($state->{$group}[$file] ?? null) : null;
        if (!is_object($item)) { continue; }
        $id = (string) ($item->id ?? '');
        if (strpos($id, 'w.org/plugins/') === 0 || strpos($id, 'https://wordpress.org/plugins/') === 0) {
            return sanitize_title((string) ($item->slug ?? basename(dirname($file))));
        }
    }
    return '';
}

function tnop_details(): array {
    $details = get_site_option('tnop_details', []);
    return is_array($details) ? $details : [];
}
