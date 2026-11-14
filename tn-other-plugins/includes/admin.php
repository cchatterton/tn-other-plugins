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
            'updateNonce' => wp_create_nonce('tnop_update_selected'),
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
    tnop_clean_ajax_output();
    wp_send_json_success(['updates' => $count]);
}

function tnop_clean_ajax_output(): void {
    if (!defined('TNOP_AJAX_BUFFER_LEVEL')) { return; }
    while (ob_get_level() >= TNOP_AJAX_BUFFER_LEVEL) { ob_end_clean(); }
}

function tnop_update_selected_ajax(): void {
    if (!current_user_can('update_plugins') || (is_multisite() && !current_user_can('manage_network_plugins'))) {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'You cannot update plugins.'], 403);
    }
    if (!check_ajax_referer('tnop_update_selected', 'nonce', false)) {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'Your session expired. Reload this page and try again.'], 403);
    }
    $file = isset($_POST['plugin']) && is_string($_POST['plugin']) ? sanitize_text_field(wp_unslash($_POST['plugin'])) : '';
    if ($file === '') {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'Select a plugin to update.'], 400);
    }
    $inventory = tnop_inventory();
    $updates = tnop_update_items();
    if (!isset($inventory[$file], $updates[$file]) || empty($updates[$file]->package)) {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'This plugin no longer has an eligible update. Check for updates again.'], 400);
    }
    $target = (string) ($updates[$file]->new_version ?? '');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    if (get_filesystem_method() !== 'direct') {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'This site needs filesystem credentials. Use the native Plugins screen to update these plugins.'], 400);
    }
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $results = $upgrader->bulk_upgrade([$file], ['clear_update_cache' => false]);
    if ($results === false) {
        tnop_clean_ajax_output();
        wp_send_json_error(['message' => 'WordPress could not access the plugin files. No update result was confirmed.'], 500);
    }
    wp_clean_plugins_cache(false);
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $installed = get_plugins();
    $result = $results[$file] ?? false;
    $current = (string) ($installed[$file]['Version'] ?? '');
    $success = $result && !is_wp_error($result) && $current !== '' && $target !== '' && version_compare($current, $target, '>=');
    if ($success) {
        $state = get_site_transient('update_plugins');
        if (is_object($state)) {
            if (is_array($state->response ?? null)) { unset($state->response[$file]); }
            if (is_array($state->checked ?? null)) { $state->checked[$file] = $current; }
            set_site_transient('update_plugins', $state);
        }
    }
    $item = [
        'name' => (string) ($inventory[$file]['Name'] ?? $file),
        'status' => $success ? 'success' : 'failed',
        'message' => $success ? 'Updated to ' . $current . '.' : (is_wp_error($result) ? $result->get_error_message() : 'WordPress could not verify the updated version.'),
    ];
    tnop_clean_ajax_output();
    wp_send_json_success(['item' => $item]);
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
    echo '<dialog id="tnop-dialog" class="tnop-dialog" aria-labelledby="tnop-dialog-title" aria-describedby="tnop-dialog-message"><h2 id="tnop-dialog-title" tabindex="-1">Checking for updates</h2><div class="tnop-activity"><span class="spinner is-active" aria-hidden="true"></span><p id="tnop-dialog-message" role="status" aria-live="polite">Checking WordPress and plugin sources…</p></div><div id="tnop-progress-area" hidden><progress id="tnop-progress" max="1" value="0" aria-label="Plugins processed"></progress></div><details id="tnop-failures" hidden><summary>Failed plugins</summary><ul></ul></details><div class="tnop-dialog-actions"><button type="button" class="button button-primary" id="tnop-close" disabled>Close</button></div></dialog>';
    echo '</div>';
}

function tnop_activation_url(string $file, bool $active): string {
    $action = $active ? 'deactivate' : 'activate';
    $url = add_query_arg(['action' => $action, 'plugin' => $file, 'networkwide' => is_multisite() ? 1 : 0], self_admin_url('plugins.php'));
    return wp_nonce_url($url, $action . '-plugin_' . $file);
}

function tnop_release_notes_url(string $file, array $plugin, array $updates): string {
    $slug = tnop_wporg_slug($file);
    if ($slug !== '') { return 'https://wordpress.org/plugins/' . rawurlencode($slug) . '/#developers'; }
    $update_uri = (string) ($plugin['UpdateURI'] ?? '');
    if (preg_match('~^https://github\.com/[a-zA-Z0-9-]+/[a-zA-Z0-9_.-]+/?$~D', $update_uri)) {
        return rtrim($update_uri, '/') . '/releases';
    }
    $update_url = (string) ($updates[$file]->url ?? '');
    if (preg_match('~^https?://~i', $update_url)) { return $update_url; }
    return '';
}

function tnop_render_updates(array $plugins, array $updates): void {
    echo '<form id="tnop-update-form" method="post" action="' . esc_url(self_admin_url('update.php?action=update-selected')) . '">';
    wp_nonce_field('bulk-update-plugins');
    echo '<div class="tnop-toolbar"><h2>Updates available</h2><button type="submit" class="button button-primary" id="tnop-update-selected" disabled>Update selected plugins</button></div>';
    if (!(int) get_site_option('tnop_checked_at', 0)) { echo '<p>Click Check for updates to load available updates.</p></form>'; return; }
    if (!$updates) { echo '<p>No updates available for other plugins in the latest WordPress check.</p></form>'; return; }
    echo '<div class="tnop-table-scroll"><table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="tnop-select-all" aria-label="Select all eligible updates"></td><th scope="col">Plugin</th><th scope="col">Installed</th><th scope="col">Available</th></tr></thead><tbody>';
    foreach ($updates as $file => $item) {
        $name = $plugins[$file]['Name'] ?? $file;
        $allowed = current_user_can('update_plugins');
        echo '<tr><th class="check-column"><input type="checkbox" name="checked[]" value="' . esc_attr($file) . '" data-plugin-name="' . esc_attr($name) . '" aria-label="Select ' . esc_attr($name) . '"' . ($allowed ? '' : ' disabled') . '></th><td><strong>' . esc_html($name) . '</strong></td><td>' . esc_html($plugins[$file]['Version'] ?? '') . '</td><td>' . esc_html($item->new_version ?? '') . '</td></tr>';
    }
    echo '</tbody></table></div></form>';
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
            $active = $group === 'active';
            $latest = (string) ($updates[$file]->new_version ?? $plugin['Version'] ?? '');
            $requirements = [];
            if (!empty($plugin['RequiresWP'])) { $requirements[] = 'WordPress ' . $plugin['RequiresWP'] . '+'; }
            if (!empty($plugin['RequiresPHP'])) { $requirements[] = 'PHP ' . $plugin['RequiresPHP'] . '+'; }
            $version = 'Installed ' . ($plugin['Version'] ?? '') . ' · Latest ' . $latest;
            if ($requirements) { $version .= ' · ' . implode(' · ', $requirements); }
            echo '<article class="tnop-card"><div class="tnop-card-content"><h3>' . esc_html($plugin['Name'] ?? $file) . '</h3><p class="tnop-card-description">' . esc_html(wp_strip_all_tags($plugin['Description'] ?? '')) . '</p>';
            echo '<p class="tnop-detail">' . esc_html($version) . '</p>';
            if ($info) {
                if ($info['last_updated'] !== '') { echo '<p class="tnop-detail">Last updated ' . esc_html($info['last_updated']) . '</p>'; }
                echo '<p class="tnop-detail">' . esc_html(number_format_i18n($info['downloaded'])) . ' downloads · ' . esc_html(number_format_i18n($info['num_ratings'])) . ' ratings</p>';
            }
            echo '<div class="tnop-card-actions">';
            if (current_user_can($active ? 'deactivate_plugin' : 'activate_plugin', $file)) {
                echo '<a class="button" href="' . esc_url(tnop_activation_url($file, $active)) . '">' . esc_html((is_multisite() ? 'Network ' : '') . ($active ? 'Deactivate' : 'Activate')) . '</a>';
            }
            if (!$active && current_user_can('delete_plugins')) {
                echo '<form method="post" action="' . esc_url(self_admin_url('plugins.php')) . '"><input type="hidden" name="action" value="delete-selected"><input type="hidden" name="checked[]" value="' . esc_attr($file) . '">';
                wp_nonce_field('bulk-plugins');
                echo '<button type="submit" class="button-link-delete">Delete</button></form>';
            }
            $release_notes = tnop_release_notes_url($file, $plugin, $updates);
            if ($release_notes !== '') {
                echo '<a href="' . esc_url($release_notes) . '" target="_blank" rel="noopener noreferrer">Release notes<span class="screen-reader-text"> (opens in a new tab)</span></a>';
            } else { echo '<span class="description">Release notes unavailable</span>'; }
            echo '</div></div></article>';
        }
        echo '</div></section>';
    }
}
