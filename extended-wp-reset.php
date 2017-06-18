<?php
/*
Plugin Name: Extended WP Reset
Plugin URI:
Description: This plugin is based on WP Dev's <a href="https://wordpress.org/plugins/wp-reset/" target="_blank">WP Reset</a> plugin. In comparison to the original version, this plugin offers support for MultiSite installations and also correctly deletes tables that have foreign key constraints. This plugin does not delete any other plugins, files or themes that exist on your WordPress installation, it only deletes/empties the tables from the database thus reverting WordPress to its default state. The current user will also be recreated using the same user name and password.
Version: 1.0
Author: wp-kitten
Author URI: https://github.com/wp-kitten/
Text Domain: extended-wp-reset
*/
//#! Exit if this file is accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {

	/**
	 * Class ExtendedWpReset
	 */
	class ExtendedWpReset
	{
		const BASE_SLUG = 'ext_wp_reset';
		const ADMIN_CAP = 'manage_options';
		const NETWORK_ADMIN_CAP = 'manage_network';

		const NONCE_ACTION = 'ext_wp_reset_action';
		const NONCE_NAME = 'ext_wp_reset_nonce';

		/**
		 * Plugins' registered pages
		 * @var array
		 */
		private $_pages = array();

		//#! Utility vars to pass data through requests
		private $_transName = 'ext_wp_reset_notice';
		//#! @see ExtendedWpReset::adminNotice()
		private $_noticeClass = '';
		private $_noticeMessage = '';

		/**
		 * Holds the reference to the instance of this class
		 * @var ExtendedWpReset
		 */
		private static $_instance = null;

		/**
		 * Retrieve the reference to the instance of this class
		 * @return ExtendedWpReset
		 */
		public static function getInstance()
		{
			if ( empty( self::$_instance ) || ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self;
			}
			return self::$_instance;
		}

		/**
		 * Class constructor
		 */
		public function __construct()
		{
			$network = ( is_network_admin() ? 'network_' : '' );
			add_action( "{$network}admin_menu", array( $this, 'addPluginPage' ), 0 );
			add_action( 'admin_init', array( $this, 'resetWP' ), 0 );
			add_action( 'init', array( $this, 'loadTextDomain' ) );
		}

		/**
		 * Utility method to detect whether or not this is a MultiSite installation
		 * @return bool
		 */
		public function isWPMU()
		{
			return ( function_exists( 'is_multisite' ) && is_multisite() );
		}

		/**
		 * Add the plugin's options page
		 */
		public function addPluginPage()
		{
			$name = esc_html( __('Extended WP Reset', 'extended-wp-reset' ) );
			$page = add_menu_page( $name, $name, $this->isWPMU() ? self::NETWORK_ADMIN_CAP : self::ADMIN_CAP, self::BASE_SLUG, array( $this, 'render_plugin_options_page' ), 'dashicons-admin-generic' );
			array_push( $this->_pages, $page );
		}

		/**
		 * Render the plugin's options page
		 */
		public function render_plugin_options_page()
		{
			$filePath = plugin_dir_path( __FILE__ ) . 'inc/index.php';
			if ( is_file( $filePath ) && is_readable( $filePath ) ) {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'wp-reset-js', plugin_dir_url( __FILE__ ) . 'res/admin.js', array( 'jquery' ) );
				require( $filePath );
			}
		}

		/**
		 * This is the plugin's main function. It will reset the WordPress installation
		 */
		public function resetWP()
		{
			//#! Refuse these requests
			$doingAjax = ( defined( 'DOING_AJAX' ) && DOING_AJAX );
			$doingCron = ( defined( 'DOING_CRON' ) && DOING_CRON );
			$ajaxRequest = ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' );
			if ( $doingAjax || $doingCron || $ajaxRequest ) {
				return;
			}

			//#! Make sure this request is coming from our page
			if( ! isset($_POST['ext_wp_reset_field']) || empty($_POST['ext_wp_reset_field']) || ( $_POST['ext_wp_reset_field'] != self::NONCE_NAME ) ) {
				return;
			}


			//#! If the request is coming from the network page
			$networkReset = ( is_network_admin() || ( $this->isWPMU() && is_main_site() ) );
			$noticeHook = ( $networkReset ? 'network_' : '' );

			$rm = strtoupper( $_SERVER['REQUEST_METHOD'] );
			if ( 'POST' == $rm ) {
				//#! Validate request
				$wp_reset = ( isset( $_POST['ext_wp_reset'] ) && $_POST['ext_wp_reset'] == 'true' );
				$wp_reset_confirm = ( isset( $_POST['ext_wp_reset_confirm'] ) && $_POST['ext_wp_reset_confirm'] == 'ext-wp-reset' );
				$valid_nonce = ( isset( $_POST[self::NONCE_NAME] ) && wp_verify_nonce( $_POST[self::NONCE_NAME], self::NONCE_ACTION ) );

				if ( $wp_reset && $wp_reset_confirm && $valid_nonce ) {
					//#! Get the WP path
					$path = ABSPATH;
					if ( $path != '/' ) {
						$path = trailingslashit( $path );
					}
					if ( ! function_exists( 'wp_install' ) ) {
						require_once( $path . 'wp-admin/includes/upgrade.php' );
					}
					if ( ! function_exists( 'deactivate_plugins' ) ) {
						require_once( $path . 'wp-admin/includes/plugin.php' );
					}

					/*
					 * Prepare data
					 */
					$blogAdmin = wp_get_current_user();
					$blogName = get_option( 'blogname' );
					$blogPublic = get_option( 'blog_public' );
					$adminID = $blogAdmin->ID;
					$adminLogin = $blogAdmin->user_login;
					$adminEmail = $blogAdmin->user_email;
					$adminPass = $blogAdmin->user_pass;


					//#! WPMU instance or single site reset (in both WPMU and single installations)

					// [1] Delete special tables in order to avoid foreign key constraints violation errors
					$this->__deleteSpecialTables();

					// [2] Delete all the other tables normally
					$result = $this->__deleteTables( $networkReset );
					if ( true !== $result ) {
						$this->_noticeClass = 'error';
						$this->_noticeMessage = sprintf(
							__( '[%s] One or more errors occurred while trying to reset your WordPress instance: %s', 'extended-wp-reset' ),
							$this->getName(),
							'<pre>' . var_export( $result, 1 ) . '</pre>'
						);
						add_action( "{$noticeHook}admin_notices", array( $this, 'adminNotice' ) );
						return;
					}

					/*
					 * Reinstall the blog
					 */
					$result = $this->__installBlog( $blogName, $adminLogin, $adminEmail, $blogPublic );
					if ( false === $result ) {
						$this->_noticeClass = 'error';
						$this->_noticeMessage = sprintf(
							__( '[%s] An error occurred while trying to reset your WordPress instance', 'extended-wp-reset' ),
							$this->getName()
						);
						add_action( "{$noticeHook}admin_notices", array( $this, 'adminNotice' ) );
						return;
					}

					/*
					 * Update blog admin
					 */
					$this->__updateUsers( array(
						$adminID => array(
							'login' => $adminLogin,
							'pass' => $adminPass,
						)
					) );

					/*
					 * Reactivate our plugin
					 */
					$this->reactivatePlugin();

					//#! Re-authenticate the current user
					wp_clear_auth_cookie();
					wp_set_auth_cookie( get_current_user_id() );
					$url = add_query_arg( array( 'ext-wp-reset' => 'ext-wp-reset' ), $networkReset ? network_admin_url() : admin_url() );

					//#! Save the result for 15 seconds (the redirect to the admin dashboard should never take that long) so we can show it to the admin after redirecting to the blog's dashboard
					if( $networkReset ){
						$m = sprintf( __( '[%s] Your WordPress installation has been successfully reset to its defaults.', 'extended-wp-reset' ), $this->getName() );
					}
					else {
						if( $this->isWPMU() ) {
							$m = sprintf( __( '[%s] Your blog <strong>%s</strong> has been successfully reset to its defaults.', 'extended-wp-reset' ), $this->getName(), $blogName );
						}
						else {
							$m = sprintf( __( '[%s] Your WordPress installation has been successfully reset to its defaults.', 'extended-wp-reset' ), $this->getName() );
						}
					}
					set_site_transient( $this->_transName, $m, 15 );

					//#! Redirect to blog's dashboard
					wp_redirect( $url );
					exit;
				}
				else {
					$this->_noticeClass = 'error';
					$this->_noticeMessage = sprintf( __( '[%s] An error occurred', 'extended-wp-reset' ), $this->getName() );
					add_action( "{$noticeHook}admin_notices", array( $this, 'adminNotice' ) );
				}

			}
			//#! GET request
			else {
				if ( isset( $_GET['ext-wp-reset'] ) && ( 'ext-wp-reset' == $_GET['ext-wp-reset'] ) ) {
					$this->_noticeClass = 'error';
					$user = wp_get_current_user();
					$m = get_site_transient( $this->_transName );
					$this->_noticeMessage = ( empty( $m ) ? sprintf(
						__( '<strong>[%s] WordPress has been reset back to defaults. The user "%s" was recreated with their previous password.</strong>', 'extended-wp-reset' ),
						$this->getName(),
						$user->user_login
					) : $m );
					add_action( "{$noticeHook}admin_notices", array( $this, 'adminNotice' ) );
					delete_site_transient( $this->_transName );
				}
			}
		}

		/**
		 * Reactivate our plugin
		 */
		public function reactivatePlugin()
		{
			//#! Deactivate all plugins and reactivate ours
			$wpResetPluginFile = plugin_basename( __FILE__ );
			$plugins = null;

			if ( $this->isWPMU() ) {
				$plugins = wp_get_active_network_plugins();
				if( ! empty($plugins) ){
					foreach($plugins as $pluginFile => $time ){
						if( $pluginFile == $wpResetPluginFile ){
							continue;
						}
						deactivate_plugins( $pluginFile, false, true );
					}
				}
				if( is_network_admin() ) {
					update_site_option( 'active_sitewide_plugins', array( "$wpResetPluginFile" => time() ) );
				}
				else {
					update_blog_option( get_current_blog_id(), 'active_plugins', array( $wpResetPluginFile ) );
				}
			}
			else {
				update_option( 'active_plugins', array( $wpResetPluginFile ) );
			}
		}

		/**
		 * Retrieve the friendly name of the plugin
		 * @return string|void
		 */
		public function getName()
		{
			return __( 'Extended WordPress Reset', 'extended-wp-reset' );
		}

		/**
		 * Utility method to display an admin notice
		 * @uses $_noticeClass
		 * @uses $_noticeMessage
		 */
		public function adminNotice()
		{
			if( empty($this->_noticeMessage) || empty($this->_noticeClass) ){
				return;
			}
			?>
			<div class="notice notice-<?php echo $this->_noticeClass; ?>">
				<p><?php echo $this->_noticeMessage; ?></p>
			</div>
			<?php
			$this->_noticeClass = $this->_noticeMessage = '';
		}

		/**
		 * Enable plugin to be translated
		 */
		public function loadTextDomain()
		{
			load_plugin_textdomain( 'extended-wp-reset', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}


		/**
		 * Delete tables
		 * @param bool $networkReset Whether or not the request is coming from the network page
		 * @return array|bool
		 */
		private function __deleteTables( $networkReset )
		{
			global $wpdb;
			$prefix = $wpdb->prefix;
			$errors = array();

			//#! Delete everything but the main blog's tables. These would be truncated
			if ( $networkReset ) {
				$mainBlogID = get_current_blog_id();
				//#! Exclude the current blog
				$blogs = get_sites( array( 'site__not_in' => $mainBlogID ) );
				$deletedBlogs = array();
				foreach ( $blogs as $blog ) {
					wpmu_delete_blog( $blog->blog_id, true );
					array_push( $deletedBlogs, $blog->blog_id );
				}

				//#! Clear main blog's tables
				global $wpdb;
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->posts );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->postmeta );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->comments );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->commentmeta );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->links );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->terms );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->termmeta );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->term_relationships );
				$wpdb->query( "TRUNCATE TABLE " . $wpdb->term_taxonomy );

				$wpdb->query( "DELETE FROM " . $wpdb->registration_log . ' WHERE `ID` > 1' );
				if ( ! empty( $deletedBlogs ) ) {
					$options = '';
					foreach ( $deletedBlogs as $blogID ) {
						$options .= "'" . $wpdb->prefix . $blogID . "_capabilities',";
						$options .= "'" . $wpdb->prefix . $blogID . "_user_level',";
					}
					$options = rtrim( $options, ',' );
					$wpdb->query( "DELETE FROM " . $wpdb->usermeta . " WHERE `meta_key` IN ({$options})" );
					$wpdb->query( "DELETE FROM " . $wpdb->users . " WHERE `ID` != " . get_current_user_id() );
				}

				//#! Delete all other tables but WordPress' if this is the main site
				if( is_main_site() || is_network_admin() )
				{
					$ignoreTables = array(
						$prefix.'blogs',
						$prefix.'blog_versions',
						$prefix.'registration_log',
						$prefix.'signups',
						$prefix.'site',
						$prefix.'sitemeta',
						$prefix.'users',
						$prefix.'usermeta',
						$prefix.'commentmeta',
						$prefix.'comments',
						$prefix.'links',
						$prefix.'options',
						$prefix.'postmeta',
						$prefix.'posts',
						$prefix.'term_relationships',
						$prefix.'term_taxonomy',
						$prefix.'termmeta',
						$prefix.'terms',
					);

					$query = "SHOW TABLES LIKE '{$prefix}%'";
					$tables = $wpdb->get_col( $query );
					foreach ( $tables as $table ) {
						if( in_array( $table, $ignoreTables ) ){
							continue;
						}
						$result = $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
						if ( false === $result ) {
							array_push( $errors, "Could not delete table {$table}: " . $wpdb->last_error );
						}
					}
				}

				return true;
			}

			//#! Single instance
			$query = "SHOW TABLES LIKE '{$prefix}%'";
			$tables = $wpdb->get_col( $query );
			foreach ( $tables as $table ) {
				$result = $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
				if ( false === $result ) {
					array_push( $errors, "Could not delete table {$table}: " . $wpdb->last_error );
				}
			}
			return ( empty( $errors ) ? true : $errors );
		}

		/**
		 * Restore the blog
		 * @param string $blogName
		 * @param string $userLogin
		 * @param string $userEmail
		 * @param bool|false $publicBlog
		 * @return bool
		 */
		private function __installBlog( $blogName, $userLogin, $userEmail, $publicBlog = false )
		{
			$result = wp_install( $blogName, $userLogin, $userEmail, $publicBlog );
			if ( ! isset( $result['user_id'] ) || empty( $result['user_id'] ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Update (current) user(s)
		 * @param array $users
		 */
		private function __updateUsers( $users = array() )
		{
			if ( ! empty( $users ) ) {
				global $wpdb;
				$currentBlog = get_current_blog_id();
				foreach ( $users as $userID => $userInfo ) {
					//#! Add user to blog
					if( $this->isWPMU() ) {
						add_user_to_blog( $currentBlog, $userID, 'administrator' );
					}

					//#! Update user's password
					$wpdb->update(
						$wpdb->users,
						$data = array( 'user_pass' => $userInfo['pass'] ),
						$where = array( 'ID' => $userID ),
						$dataFormat = array( '%s' ),
						$whereFormat = array( '%d' )
					);

					//#! Remove the password change notice
					update_user_meta( $userID, 'default_password_nag', false );
				}
			}
		}

		/**
		 * Delete special tables in order to avoid foreign key constraints violation errors
		 */
		private function __deleteSpecialTables()
		{
			global $wpdb;
			$prefix = $wpdb->prefix;
			$query = "
SELECT i.TABLE_NAME
	FROM information_schema.TABLE_CONSTRAINTS AS i
	WHERE
		i.TABLE_SCHEMA = '".DB_NAME."' AND
		i.CONSTRAINT_TYPE = 'FOREIGN KEY' AND
		i.TABLE_NAME LIKE '{$prefix}%';";
			$result = $wpdb->get_results( trim( $query ) );
			if( ! empty($result) ){
				foreach( $result as $k => $v ) {
					$wpdb->query( "DROP TABLE " . $v->TABLE_NAME );
				}
			}
		}
	}

	ExtendedWpReset::getInstance();
}
