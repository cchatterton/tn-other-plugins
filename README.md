# TN Other Plugins

A Techn WordPress plugin for reviewing installed plugins outside the Techn, AlphaSys and current Fusion catalogues.

The **Plugins → Other Plugins** screen has **Updates available** and **Catalogue** tabs. An explicit check refreshes WordPress's plugin update data and public WordPress.org statistics. Individual and selected updates use the native WordPress updater. Ordinary page loads make no metadata requests.

The plugin is managed by TN Update Controller. It does not supply updates for other plugins or change their existing update providers.

On multisite, network activate it and use Network Admin. The three controllers should be active so their current catalogues can identify managed plugins.

## Build

Run `./scripts/build-plugin-zip.sh` to create `tn-other-plugins.zip` from the distributable `tn-other-plugins` directory.
