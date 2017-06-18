# Extended WP Reset

This plugin is based on [WP Dev's](https://profiles.wordpress.org/wp-dev-1) [WP Reset](https://wordpress.org/plugins/wp-reset/) plugin. In comparison to the WP Dev's version, this plugin offers support for MultiSite installations and also correctly deletes tables that have foreign key constraints. This plugin does not delete any other plugins, files or themes that exist on your WordPress installation, it only deletes/empties the tables from the database thus reverting WordPress to its default state. The current user will also be recreated using the same user name and password. 