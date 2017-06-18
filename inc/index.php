<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's main options page
 */
$rm = strtoupper( $_SERVER[ 'REQUEST_METHOD' ] );
if( 'POST' == $rm ) {
	if ( isset( $_POST[ 'ext_wp_reset_confirm' ] ) && $_POST[ 'ext_wp_reset_confirm' ] != 'ext-wp-reset' ) {
		echo '<div class="notice notice-error"><p><strong>'.esc_html( __( "Invalid confirmation. Please type 'ext-wp-reset' in the confirmation field.", 'extended-wp-reset' ) ).'</strong></p></div>';
	}
	elseif ( isset( $_POST[ ExtendedWpReset::NONCE_NAME ] ) ) {
		echo '<div class="notice notice-error"><p><strong>'.esc_html( __(  'Invalid nonce. Please try again.', 'extended-wp-reset' ) ).'</strong></p></div>';
	}
}

$user = wp_get_current_user();
$wpReset = ExtendedWpReset::getInstance();
?>
<div class="wrap wp-reset-wrap">
	<h2><?php esc_html_e( $wpReset->getName(), 'extended-wp-reset' );?></h2>

	<div class="notice notice-warning">
		<p><?php echo sprintf( __( "Your user '<strong>%s</strong>' will be recreated using your <strong>current password</strong>.", 'extended-wp-reset' ), $user->user_login );?></p>
		<p><?php _e( 'This plugin <strong>will be automatically reactivated</strong> after the reset operation.', 'extended-wp-reset' );?></p>
		<p>
			<strong>
				<?php
				$what = ( is_network_admin() ? __( 'network', 'extended-wp-reset' ) : __( "blog's", 'extended-wp-reset' ) );
				echo sprintf(__("After completing this action you will be taken to your %s dashboard.", 'extended-wp-reset' ), $what );?>
			</strong>
		</p>
	</div>

	<br/>
	<h3><?php _e( 'Reset your WordPress installation', 'extended-wp-reset' );?></h3>

	<p><?php _e( 'Type <strong>ext-wp-reset</strong> in the field to confirm the action and then click the Reset button:', 'extended-wp-reset' );?></p>

	<form id="wp_reset_form" action="" method="post" autocomplete="off">
		<label for="ext_wp_reset_confirm" class="screen-reader-text"><?php _e( 'Type <strong>ext-wp-reset</strong> in the field to confirm the action and then click the Reset button:', 'extended-wp-reset' );?></label>
		<input id="ext_wp_reset_confirm" type="text" name="ext_wp_reset_confirm" value="" maxlength="12"/>
		<input id="wp_reset_submit" type="submit" name="ext_wp_reset_submit" class="button button-primary button-large" value="<?php esc_html_e( 'Reset', 'extended-wp-reset' );?>"/>

		<input id="ext_wp_reset" type="hidden" name="ext_wp_reset" value="true"/>
		<input type="hidden" name="ext_wp_reset_field" value="<?php echo ExtendedWpReset::NONCE_NAME;?>"/>
		<?php wp_nonce_field( ExtendedWpReset::NONCE_ACTION, ExtendedWpReset::NONCE_NAME ); ?>
	</form>
</div>
