<?php
/**
 * Administrator protection status.
 *
 * @package StagingBotBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describe a mode consistently on the settings page and in notices.
 *
 * @param string $mode Effective protection mode.
 * @return string
 */
function staging_bot_block_get_mode_label( $mode ) {
	$labels = array(
		'block'         => __( 'Block recognized bots', 'staging-bot-block' ),
		'redirect_bots' => __( 'Redirect recognized bots', 'staging-bot-block' ),
		'redirect_all'  => __( 'Redirect all non-administrator visitors', 'staging-bot-block' ),
	);
	return isset( $labels[ $mode ] ) ? $labels[ $mode ] : $labels['block'];
}

/**
 * Show the persistent, administrator-only status notice when requested.
 *
 * @return void
 */
function staging_bot_block_enable_warning_banner() {
	if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
		return;
	}
	$options = staging_bot_block_get_options();
	if ( empty( $options['enabled'] ) || empty( $options['warning_banner'] ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Staging Bot Block: protection active.', 'staging-bot-block' ); ?></strong>
			<?php echo esc_html( staging_bot_block_get_mode_label( staging_bot_block_get_effective_mode( $options ) ) ); ?>.
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) ); ?>"><?php esc_html_e( 'Review staging protection settings', 'staging-bot-block' ); ?></a>
		</p>
		<?php if ( 'production' === wp_get_environment_type() ) : ?>
			<p><?php esc_html_e( 'WordPress reports this environment as production. Confirm this site should have staging protection enabled.', 'staging-bot-block' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'admin_notices', 'staging_bot_block_enable_warning_banner' );

/**
 * Remind the activating administrator that a fresh install starts disabled.
 *
 * @return void
 */
function staging_bot_block_activation_notice() {
	if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ! get_option( 'staging_bot_block_show_activation_notice' ) ) {
		return;
	}
	$options = staging_bot_block_get_options();
	if ( empty( $options['enabled'] ) ) {
		?>
		<div class="notice notice-info"><p>
			<?php esc_html_e( 'Staging Bot Block is installed with protection disabled.', 'staging-bot-block' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) ); ?>"><?php esc_html_e( 'Configure staging protection', 'staging-bot-block' ); ?></a>
		</p></div>
		<?php
	}
	delete_option( 'staging_bot_block_show_activation_notice' );
}
add_action( 'admin_notices', 'staging_bot_block_activation_notice' );
