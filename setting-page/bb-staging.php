<?php
/**
 * Native WordPress settings screen.
 *
 * @package StagingBotBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep staging protection visible in the top-level admin menu.
 *
 * @return void
 */
function staging_bot_block_admin_menu() {
	add_menu_page(
		__( 'Staging Bot Block', 'staging-bot-block' ),
		__( 'Staging Bot Block', 'staging-bot-block' ),
		'manage_options',
		'block-bot-redirect-setting-page',
		'staging_bot_block_admin_page_contents',
		'dashicons-shield',
		3
	);
}
add_action( 'admin_menu', 'staging_bot_block_admin_menu' );

/**
 * Render stored settings and their effective protection status.
 *
 * @return void
 */
function staging_bot_block_admin_page_contents() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$options   = staging_bot_block_get_options();
	$mode      = staging_bot_block_get_effective_mode( $options );
	$is_active = ! empty( $options['enabled'] );
	?>
	<div class="wrap sbb-settings">
		<h1><?php esc_html_e( 'Staging Bot Block', 'staging-bot-block' ); ?></h1>
		<?php settings_errors(); ?>
		<section class="sbb-status notice <?php echo $is_active ? 'notice-warning' : 'notice-info'; ?>" aria-labelledby="sbb-status-heading">
			<h2 id="sbb-status-heading"><?php echo $is_active ? esc_html__( 'Protection active', 'staging-bot-block' ) : esc_html__( 'Protection inactive', 'staging-bot-block' ); ?></h2>
			<dl>
				<dt><?php esc_html_e( 'Configured mode', 'staging-bot-block' ); ?></dt>
				<dd><?php echo esc_html( staging_bot_block_get_mode_label( $options['mode'] ) ); ?></dd>
				<dt><?php esc_html_e( 'WordPress environment', 'staging-bot-block' ); ?></dt>
				<dd><?php echo esc_html( wp_get_environment_type() ); ?></dd>
				<?php if ( 'block' !== $options['mode'] ) : ?>
					<dt><?php esc_html_e( 'Redirect destination', 'staging-bot-block' ); ?></dt>
					<dd><?php echo '' !== $options['redirect_url'] ? esc_html( $options['redirect_url'] ) : esc_html__( 'Not configured', 'staging-bot-block' ); ?></dd>
					<dt><?php esc_html_e( 'Redirect status', 'staging-bot-block' ); ?></dt>
					<dd><?php echo 301 === $options['redirect_type'] ? esc_html__( '301 Permanent', 'staging-bot-block' ) : esc_html__( '302 Temporary', 'staging-bot-block' ); ?></dd>
				<?php endif; ?>
			</dl>
			<?php if ( $mode !== $options['mode'] ) : ?>
				<p><strong><?php esc_html_e( 'The redirect destination is invalid or points back into this site. When protection is active, recognized bots are blocked instead.', 'staging-bot-block' ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $is_active ) : ?>
				<p><strong><?php esc_html_e( 'Staging protection is enabled. Review this setting before making the site public.', 'staging-bot-block' ); ?></strong></p>
				<?php if ( 'production' === wp_get_environment_type() ) : ?>
					<p><?php esc_html_e( 'WordPress reports this environment as production. Verify that staging protection is intentional; the environment label does not disable protection.', 'staging-bot-block' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'This plugin is not changing visitor responses. Enable protection below when this site is ready for staging use.', 'staging-bot-block' ); ?></p>
			<?php endif; ?>
		</section>
		<p class="sbb-intro"><?php esc_html_e( 'Reduce accidental indexing of staging and development sites by blocking or redirecting recognized crawlers. User-agent matching is not authentication and does not recognize every bot.', 'staging-bot-block' ); ?></p>
		<form method="post" id="bb_redirect_settings_mainform" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php settings_fields( 'staging_bot_block_options_group' ); ?>
			<h2><?php esc_html_e( 'Protection settings', 'staging-bot-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Protection', 'staging-bot-block' ); ?></th>
						<td><label for="staging_bot_block_enabled"><input type="checkbox" name="staging_bot_block_options[enabled]" id="staging_bot_block_enabled" value="1" <?php checked( $is_active ); ?>> <?php esc_html_e( 'Enable staging protection', 'staging-bot-block' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Protection mode', 'staging-bot-block' ); ?></th>
						<td>
							<fieldset class="sbb-modes" aria-describedby="sbb-mode-help">
								<legend class="screen-reader-text"><?php esc_html_e( 'Protection mode', 'staging-bot-block' ); ?></legend>
								<label><input type="radio" name="staging_bot_block_options[mode]" value="block" <?php checked( $options['mode'], 'block' ); ?>> <?php esc_html_e( 'Block recognized bots with a 403 response (recommended)', 'staging-bot-block' ); ?></label>
								<label><input type="radio" name="staging_bot_block_options[mode]" value="redirect_bots" <?php checked( $options['mode'], 'redirect_bots' ); ?>> <?php esc_html_e( 'Redirect recognized bots to the live site', 'staging-bot-block' ); ?></label>
								<label><input type="radio" name="staging_bot_block_options[mode]" value="redirect_all" <?php checked( $options['mode'], 'redirect_all' ); ?>> <?php esc_html_e( 'Redirect all non-administrator visitors to the live site', 'staging-bot-block' ); ?></label>
							</fieldset>
							<p class="description" id="sbb-mode-help"><?php esc_html_e( 'Redirect-all includes logged-in subscribers and editors. Users with permission to manage site options retain frontend access in this mode. Login and password reset remain available.', 'staging-bot-block' ); ?></p>
						</td>
					</tr>
					<tr data-sbb-redirect>
						<th scope="row"><label for="staging_bot_block_redirect_url"><?php esc_html_e( 'Live-site URL', 'staging-bot-block' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" inputmode="url" name="staging_bot_block_options[redirect_url]" id="staging_bot_block_redirect_url" value="<?php echo esc_attr( $options['redirect_url'] ); ?>" placeholder="https://example.com/" aria-describedby="sbb-url-help">
							<p class="description" id="sbb-url-help"><?php esc_html_e( 'Redirect modes only. Enter a complete HTTP or HTTPS destination outside this staging site. Every redirected request goes to this exact URL; the visitor path and query are not appended.', 'staging-bot-block' ); ?></p>
						</td>
					</tr>
					<tr data-sbb-redirect>
						<th scope="row"><label for="staging_bot_block_redirect_type"><?php esc_html_e( 'Redirect status', 'staging-bot-block' ); ?></label></th>
						<td>
							<select name="staging_bot_block_options[redirect_type]" id="staging_bot_block_redirect_type" aria-describedby="sbb-redirect-warning">
								<option value="302" <?php selected( $options['redirect_type'], 302 ); ?>><?php esc_html_e( '302 Temporary (recommended)', 'staging-bot-block' ); ?></option>
								<option value="301" <?php selected( $options['redirect_type'], 301 ); ?>><?php esc_html_e( '301 Permanent (advanced)', 'staging-bot-block' ); ?></option>
							</select>
							<p class="description" id="sbb-redirect-warning"><strong><?php esc_html_e( 'Warning: 301 signals a permanent move. Browsers and search engines can retain it after you disable protection. Use 302 for staging; use 301 only when intentionally retiring the staging address.', 'staging-bot-block' ); ?></strong></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="staging_bot_block_extra_user_agents"><?php esc_html_e( 'Additional recognized user agents', 'staging-bot-block' ); ?></label></th>
						<td>
							<textarea name="staging_bot_block_options[extra_user_agents]" id="staging_bot_block_extra_user_agents" rows="5" class="large-text code" aria-describedby="sbb-ua-help"><?php echo esc_textarea( $options['extra_user_agents'] ); ?></textarea>
							<p class="description" id="sbb-ua-help"><?php esc_html_e( 'Optional. One user-agent substring per line, matched without regard to case. These supplement the built-in search, social, and AI crawlers in both bot modes. Broad terms may also match browsers.', 'staging-bot-block' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Administrator reminder', 'staging-bot-block' ); ?></th>
						<td>
							<label for="staging_bot_block_warning_banner"><input type="checkbox" name="staging_bot_block_options[warning_banner]" id="staging_bot_block_warning_banner" value="1" <?php checked( ! empty( $options['warning_banner'] ) ); ?>> <?php esc_html_e( 'Show a persistent protection notice to administrators', 'staging-bot-block' ); ?></label>
							<p class="description"><?php esc_html_e( 'Recommended. The settings page always shows the current status, even when this reminder is turned off.', 'staging-bot-block' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Staging infrastructure', 'staging-bot-block' ); ?></h2>
		<p class="sbb-intro"><?php esc_html_e( 'Bypass or disable page caching and CDN caching for this staging site, then purge existing cached responses. Requests served before WordPress, static files, and endpoints outside the frontend template flow are not protected. ACME certificate challenges remain accessible. Use host-level authentication when the site must be private.', 'staging-bot-block' ); ?></p>
		<p><a href="https://www.netprofitmarketing.com"><?php esc_html_e( 'By Net Profit Marketing', 'staging-bot-block' ); ?></a></p>
	</div>
	<?php
}
