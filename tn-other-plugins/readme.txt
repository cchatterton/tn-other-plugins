=== TN Other Plugins ===
Contributors:
Tags: plugins, updates, catalogue, techn
Requires at least: 6.5
Stable tag: 0.1.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Review and update installed plugins outside the Techn, AlphaSys and Fusion catalogues.

== Description ==

Other Plugins adds a final item under Plugins. Updates available shows WordPress-reported updates for plugins outside the three catalogues. Catalogue shows those installed plugins in Active and Installed groups. An explicit Check for updates asks WordPress to refresh its plugin update information and fetches available WordPress.org statistics for recognised plugins. Ordinary page loads use cached information and make no metadata requests.

Updates use WordPress's native upgrader. TN Update Controller manages updates for TN Other Plugins itself.

On multisite, network activate this plugin and use Network Admin.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate it, or network activate on multisite.
3. Open Plugins > Other Plugins and click Check for updates.

== External services ==

An explicit check sends the installed plugin list to the WordPress.org update API, as WordPress normally does, and requests public plugin statistics for recognised WordPress.org plugins. The server IP and normal connection metadata are visible to WordPress.org. Vendor plugins continue using their existing update providers. No site check runs when the Other Plugins page opens.

Terms: https://wordpress.org/about/privacy/

== Changelog ==

= 0.1.5 =

* Place Update selected plugins beside the Updates available heading and enable it when a plugin is selected. Use WordPress's update_plugins capability for update actions.

= 0.1.4 =

* Show update checks in a progress modal and refresh the list when the check completes.

= 0.1.3 =

* Prompt for the first manual check before listing available updates.

= 0.1.2 =

* Correct the Techn controller API declaration for catalogue publication.

= 0.1.1 =

* Declare the Techn controller API and add native activation controls to catalogue cards.

= 0.1.0 =

* First release.
