<?php
if (!defined('ABSPATH')) { exit; }

function tnop_url(string $tab = 'installed'): string {
    return add_query_arg(['page' => 'tn-other-plugins', 'tab' => $tab], is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php'));
}

function tnop_register_menu(): void {
    if (is_multisite() && !is_network_admin()) { return; }
    add_submenu_page('plugins.php', 'Other Plugins', 'Other Plugins', 'update_plugins', 'tn-other-plugins', 'tnop_render_admin', 999);
}

function tnop_action_links(array $links): array {
    array_unshift($links, '<a href="' . esc_url(tnop_url()) . '">Other Plugins</a>');
    return $links;
}

function tnop_enqueue_assets(string $hook): void {
    if ($hook === 'plugins_page_tn-other-plugins') {
        wp_enqueue_style('tnop-admin', plugins_url('assets/admin.css', TNOP_FILE), [], TNOP_VERSION);
        wp_enqueue_script('tnop-admin', plugins_url('assets/admin.js', TNOP_FILE), [], TNOP_VERSION, true);
        wp_localize_script('tnop-admin', 'tnopAdmin', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tnop_check'),
        ]);
    }
}

function tnop_run_check(): int {
    wp_update_plugins(['manual_check' => 'tn-other-plugins']);
    $details = tnop_details();
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    foreach (tnop_inventory() as $file => $plugin) {
        $slug = tnop_wporg_slug($file);
        if ($slug === '') { continue; }
        $info = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['downloaded' => true, 'last_updated' => true, 'num_ratings' => true, 'sections' => false]]);
        if (is_wp_error($info) || !is_object($info) || ($info->slug ?? '') !== $slug) { continue; }
        $details[$file] = [
            'last_updated' => (string) ($info->last_updated ?? ''),
            'downloaded' => (int) ($info->downloaded ?? 0),
            'num_ratings' => (int) ($info->num_ratings ?? 0),
        ];
    }
    update_site_option('tnop_details', $details);
    update_site_option('tnop_checked_at', time());
    return count(array_intersect_key(tnop_update_items(), tnop_inventory()));
}

function tnop_check_updates(): void {
    if (!current_user_can('update_plugins') || (is_multisite() && !current_user_can('manage_network_plugins'))) { wp_die('You cannot check plugin updates.'); }
    check_admin_referer('tnop_check');
    tnop_run_check();
    wp_safe_redirect(tnop_url());
    exit;
}

function tnop_check_updates_ajax(): void {
    if (!current_user_can('update_plugins') || (is_multisite() && !current_user_can('manage_network_plugins'))) {
        wp_send_json_error(['message' => 'You cannot check plugin updates.'], 403);
    }
    check_ajax_referer('tnop_check', 'nonce');
    $count = tnop_run_check();
    if (defined('TNOP_AJAX_BUFFER_LEVEL')) {
        while (ob_get_level() >= TNOP_AJAX_BUFFER_LEVEL) { ob_end_clean(); }
    }
    wp_send_json_success(['updates' => $count]);
}

function tnop_render_admin(): void {
    if (!current_user_can('update_plugins') || (is_multisite() && !current_user_can('manage_network_plugins'))) { wp_die('You cannot manage plugin updates.'); }
    $tab = isset($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'catalogue' ? 'catalogue' : 'installed';
    $plugins = tnop_inventory();
    $checked = (int) get_site_option('tnop_checked_at', 0);
    $updates = $checked ? array_intersect_key(tnop_update_items(), $plugins) : [];
    echo '<div class="wrap tnop-wrap"><h1>Other Plugins</h1><div class="tnop-panel">';
    echo '<header class="tnop-header"><span class="tnop-version">v' . esc_html(TNOP_VERSION) . '</span><p class="tnop-eyebrow">Techn / Plugin library</p><h2>Your other plugins. One place.</h2><p>Installed plugins outside the Techn, AlphaSys and current Fusion catalogues.</p><div class="tnop-header-bottom"><span>' . count($plugins) . ' installed · ' . count($updates) . ' updates available</span>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tnop_check">';
    wp_nonce_field('tnop_check');
    echo '<button class="button tnop-primary" type="submit" data-tnop-check>Check for updates</button></form></div></header>';
    echo '<div class="tnop-status"><span>' . ($checked ? 'Last checked ' . esc_html(wp_date('j M Y, H:i', $checked)) : 'Never checked') . '</span><span>Manual checks only</span></div>';
    echo '<nav class="nav-tab-wrapper" aria-label="Other Plugins">';
    foreach (['installed' => 'Updates available', 'catalogue' => 'Catalogue'] as $key => $label) {
        echo '<a class="nav-tab ' . ($tab === $key ? 'nav-tab-active' : '') . '" href="' . esc_url(tnop_url($key)) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav><main class="tnop-main">';
    if ($tab === 'catalogue') { tnop_render_catalogue($plugins, $updates); }
    else { tnop_render_updates($plugins, $updates); }
    echo '</main></div>';
    echo '<dialog id="tnop-dialog" class="tnop-dialog" aria-labelledby="tnop-dialog-title" aria-describedby="tnop-dialog-message"><h2 id="tnop-dialog-title" tabindex="-1">Checking for updates</h2><div class="tnop-activity"><span class="spinner is-active" aria-hidden="true"></span><p id="tnop-dialog-message" role="status" aria-live="polite">Checking WordPress and plugin sources…</p></div><div class="tnop-dialog-actions"><button type="button" class="button button-primary" id="tnop-close" disabled>Close</button></div></dialog>';
    echo '</div>';
}

function tnop_update_url(string $file): string {
    return wp_nonce_url(add_query_arg(['action' => 'upgrade-plugin', 'plugin' => $file], self_admin_url('update.php')), 'upgrade-plugin_' . $file);
}

function tnop_activation_url(string $file, bool $active): string {
    $action = $active ? 'deactivate' : 'activate';
    $url = add_query_arg(['action' => $action, 'plugin' => $file, 'networkwide' => is_multisite() ? 1 : 0], self_admin_url('plugins.php'));
    return wp_nonce_url($url, $action . '-plugin_' . $file);
}

function tnop_render_updates(array $plugins, array $updates): void {
    echo '<div class="tnop-toolbar"><h2>Updates available</h2></div>';
    if (!(int) get_site_option('tnop_checked_at', 0)) { echo '<p>Click Check for updates to load available updates.</p>'; return; }
    if (!$updates) { echo '<p>No updates available for other plugins in the latest WordPress check.</p>'; return; }
    echo '<form method="post" action="' . esc_url(self_admin_url('update.php?action=update-selected')) . '">';
    wp_nonce_field('bulk-update-plugins');
    echo '<div class="tnop-table-scroll"><table class="widefat striped"><thead><tr><th scope="col">Select</th><th scope="col">Plugin</th><th scope="col">Installed</th><th scope="col">Available</th><th scope="col">Action</th></tr></thead><tbody>';
    foreach ($updates as $file => $item) {
        $name = $plugins[$file]['Name'] ?? $file;
        $allowed = current_user_can('update_plugin', $file) && wp_is_file_mod_allowed('plugin_update');
        echo '<tr><td><input type="checkbox" name="checked[]" value="' . esc_attr($file) . '" aria-label="Select ' . esc_attr($name) . '"' . ($allowed ? '' : ' disabled') . '></td><td><strong>' . esc_html($name) . '</strong></td><td>' . esc_html($plugins[$file]['Version'] ?? '') . '</td><td>' . esc_html($item->new_version ?? '') . '</td><td>';
        if ($allowed) { echo '<a class="button" href="' . esc_url(tnop_update_url($file)) . '">Update</a>'; }
        else { echo 'Updates are disabled on this site.'; }
        echo '</td></tr>';
    }
    echo '</tbody></table></div><p><button type="submit" class="button button-primary">Update selected plugins</button></p></form>';
}

function tnop_render_catalogue(array $plugins, array $updates): void {
    $groups = ['active' => [], 'installed' => []];
    foreach ($plugins as $file => $plugin) { $groups[tnop_is_active($file) ? 'active' : 'installed'][$file] = $plugin; }
    $details = tnop_details();
    foreach (['active' => 'Active', 'installed' => 'Installed'] as $group => $label) {
        echo '<section class="tnop-group"><h2>' . esc_html($label) . '</h2>';
        if (!$groups[$group]) { echo '<p>No ' . strtolower(esc_html($label)) . ' other plugins.</p></section>'; continue; }
        echo '<div class="tnop-grid">';
        foreach ($groups[$group] as $file => $plugin) {
            $info = $details[$file] ?? [];
            echo '<article class="tnop-card"><h3>' . esc_html($plugin['Name'] ?? $file) . '</h3><p class="tnop-description">' . esc_html(wp_strip_all_tags($plugin['Description'] ?? '')) . '</p>';
            echo '<p class="tnop-detail">Installed ' . esc_html($plugin['Version'] ?? '') . (isset($updates[$file]) ? ' · Update ' . esc_html($updates[$file]->new_version ?? '') . ' available' : '') . '</p>';
            if ($info) {
                if ($info['last_updated'] !== '') { echo '<p class="tnop-detail">Last updated ' . esc_html($info['last_updated']) . '</p>'; }
                echo '<p class="tnop-detail">' . esc_html(number_format_i18n($info['downloaded'])) . ' downloads · ' . esc_html(number_format_i18n($info['num_ratings'])) . ' ratings</p>';
            }
            if (isset($updates[$file]) && current_user_can('update_plugin', $file) && wp_is_file_mod_allowed('plugin_update')) { echo '<a class="button button-primary" href="' . esc_url(tnop_update_url($file)) . '">Update</a>'; }
            $active = $group === 'active';
            if (current_user_can($active ? 'deactivate_plugin' : 'activate_plugin', $file)) {
                echo '<a class="button" href="' . esc_url(tnop_activation_url($file, $active)) . '">' . esc_html((is_multisite() ? 'Network ' : '') . ($active ? 'Deactivate' : 'Activate')) . '</a>';
            }
            echo '</article>';
        }
        echo '</div></section>';
    }
}
