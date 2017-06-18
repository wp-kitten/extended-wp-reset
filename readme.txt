=== Extended WP Reset ===
Contributors: wp-kitten
Tags: wp reset, wordpress reset, reset wordpress database, clean wp, default wp
License: GPLv3
Requires at least: 3.2
Tested up to: 4.8
Stable tag: trunk


This plugin will reset your WordPress installation to its default state. It will not delete any files, themes or plugins. WPMU is supported.

== Description ==

This plugin is based on <a href="https://profiles.wordpress.org/wp-dev-1" target="_blank">WP Dev's</a> <a href="https://wordpress.org/plugins/wp-reset/" target="_blank">WP Reset</a> plugin. In comparison to the WP Dev's version, this plugin offers support for MultiSite installations and also correctly deletes tables that have foreign key constraints. This plugin does not delete any other plugins, files or themes that exist on your WordPress installation, it only deletes/empties the tables from the database thus reverting WordPress to its default state. The current user will also be recreated using the same user name and password.

Usage :

1. Download and extract this plugin to `wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to plugin's page and reset your WordPress installation

== Installation ==

1. Download and extract this plugin to `wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to plugin's page and reset your WordPress installation


== Changelog ==

= 1.0 =
* Initial release.

== Frequently Asked Questions ==

You can open an issue on the plugin's page on GitHub: https://github.com/wp-kitten/extended-wp-reset
