<?php

function staging_bot_block_get_redirect_settings_post_id() {

	$bb_redirect_setting_id = '';
	$queries                = array(
		array(
			'numberposts' => 1,
			'post_type'   => 'bb_redirect_npm',
			'post_status' => 'any',
			'orderby'     => 'date',
			'order'       => 'DESC',
		),
		array(
			'numberposts' => 1,
			'post_type'   => 'any',
			'post_status' => 'any',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_key'    => 'bb_redirect_enabled',
		),
	);

	foreach ( $queries as $bb_redirect_setting_args ) {
		$bb_redirect_setting_array = get_posts( $bb_redirect_setting_args );

		if ( empty( $bb_redirect_setting_array ) ) {
			continue;
		}

		foreach ( $bb_redirect_setting_array as $bb_rows ) {
			$bb_redirect_setting_id = $bb_rows->ID;
		}

		if ( $bb_redirect_setting_id ) {
			break;
		}
	}

	return $bb_redirect_setting_id;

}
/**
 * Adds a new menu item for the bb plugin.
 *
 * This function is responsible for creating a new menu item in the WordPress admin sidebar for the bb plugin.
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

'dashicons-schedule',

3

);

}

add_action( 'admin_menu', 'staging_bot_block_admin_menu' );



/**
 * Renders the contents of the admin settings page for the bb plugin.
 *
 * This function is responsible for displaying the settings form in the WordPress admin area.
 *
 * @return void
 */
function staging_bot_block_admin_page_contents() {

        $options = staging_bot_block_get_options();
        ?>
<div class="wrap">
<h1 class="wp-heading-inline">
<?php esc_html_e( 'Staging Bot Block', 'staging-bot-block' ); ?>
</h1>
<p></p>
</div>
<?php
$settings_updated = filter_input( INPUT_GET, 'settings-updated', FILTER_SANITIZE_STRING );

if ( $settings_updated ) {
// options.php validates the submission nonce via the Settings API.
add_settings_error( 'staging_bot_block_options', 'staging_bot_block_options', __( 'Settings saved.', 'staging-bot-block' ), 'updated' );
}
settings_errors( 'staging_bot_block_options' );
?>
<form method="post" id="bb_redirect_settings_mainform" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
<?php settings_fields( 'staging_bot_block_options_group' ); ?>
                <h1 class="screen-reader-text">Staging Bot Block</h1>

<style>
        .sbb-intro {
                max-width: 760px;
                line-height: 1.6;
        }
</style>

<p class="sbb-intro"><?php esc_html_e( 'A plugin to use when developing a site or hosting a staging environment. It will help fix issues with staging sites getting indexed by Google, redirecting staging sites once they do get indexed, and forgetting about setting robots.txt to nofollow and accidentally deindexing the production site.', 'staging-bot-block' ); ?></p>
<p>
	<?php
	printf(
		wp_kses_post(
			sprintf(
				/* translators: %s: Net Profit Marketing link */
				__( 'Brought to you by the team at %s.', 'staging-bot-block' ),
				'<a href="' . esc_url( 'https://www.netprofitmarketing.com' ) . '" target="_blank" rel="noopener noreferrer">Net Profit Marketing</a>'
			)
		)
	);
	?>
</p>

                <table class="form-table" role="presentation">
                        <tbody>
<tr>
<th scope="row">
<label for="staging_bot_block_enabled"><?php esc_html_e( 'Enable Staging Bot Block', 'staging-bot-block' ); ?></label>
</th>
<td>
<label for="staging_bot_block_enabled">
<input type="checkbox" name="staging_bot_block_options[enabled]" id="staging_bot_block_enabled" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>>
<?php esc_html_e( 'Enable Staging Bot Block', 'staging-bot-block' ); ?>
</label>
<p class="description"><?php esc_html_e( 'Turn the plugin on for this site.', 'staging-bot-block' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Bot handling mode', 'staging-bot-block' ); ?></th>
<td>
<fieldset>
<legend class="screen-reader-text">
<span><?php esc_html_e( 'Bot handling mode', 'staging-bot-block' ); ?></span>
</legend>
<label for="staging_bot_block_mode_block">
<input type="radio" id="staging_bot_block_mode_block" name="staging_bot_block_options[mode]" value="block" <?php checked( $options['mode'], 'block' ); ?>>
<?php esc_html_e( 'Block search engine bots (recommended for staging)', 'staging-bot-block' ); ?>
</label><br>
<label for="staging_bot_block_mode_redirect_bots">
<input type="radio" id="staging_bot_block_mode_redirect_bots" name="staging_bot_block_options[mode]" value="redirect_bots" <?php checked( $options['mode'], 'redirect_bots' ); ?>>
<?php esc_html_e( 'Redirect search engine bots to the live site', 'staging-bot-block' ); ?>
</label><br>
<label for="staging_bot_block_mode_redirect_all">
<input type="radio" id="staging_bot_block_mode_redirect_all" name="staging_bot_block_options[mode]" value="redirect_all" <?php checked( $options['mode'], 'redirect_all' ); ?>>
<?php esc_html_e( 'Redirect everyone (bots and users) to the live site', 'staging-bot-block' ); ?>
</label>
</fieldset>
</td>
</tr>
<tr>
<th scope="row">
<label for="staging_bot_block_warning_banner"><?php esc_html_e( 'Show admin warning banner', 'staging-bot-block' ); ?></label>
</th>
<td>
<label for="staging_bot_block_warning_banner">
<input type="checkbox" name="staging_bot_block_options[warning_banner]" id="staging_bot_block_warning_banner" value="1" <?php checked( ! empty( $options['warning_banner'] ) ); ?>>
<?php esc_html_e( 'Show admin warning banner', 'staging-bot-block' ); ?>
</label>
<p class="description"><?php esc_html_e( 'Show a persistent notice in the WordPress admin while this plugin is enabled.', 'staging-bot-block' ); ?></p>
</td>
</tr>
<tr>
<th scope="row">
<label for="staging_bot_block_redirect_url"><?php esc_html_e( 'Redirect URL', 'staging-bot-block' ); ?></label>
</th>
<td>
<input class="regular-text" type="text" name="staging_bot_block_options[redirect_url]" id="staging_bot_block_redirect_url" value="<?php echo esc_attr( $options['redirect_url'] ); ?>" placeholder="https://example.com/">
<p class="description"><?php esc_html_e( 'Used only in redirect modes. Enter the URL of your live site, for example https://example.com.', 'staging-bot-block' ); ?></p>
</td>
</tr>
<tr>
<th scope="row">
<label for="staging_bot_block_redirect_type"><?php esc_html_e( 'Redirect type', 'staging-bot-block' ); ?></label>
</th>
<td>
<select name="staging_bot_block_options[redirect_type]" id="staging_bot_block_redirect_type">
<option value="302" <?php selected( (int) $options['redirect_type'], 302 ); ?>><?php esc_html_e( 'Temporary (302)', 'staging-bot-block' ); ?></option>
<option value="301" <?php selected( (int) $options['redirect_type'], 301 ); ?>><?php esc_html_e( 'Permanent (301)', 'staging-bot-block' ); ?></option>
</select>
<p class="description"><?php esc_html_e( 'Use 302 while testing. Use 301 only when retiring this staging domain.', 'staging-bot-block' ); ?></p>
</td>
</tr>
<tr>
<th scope="row">
<label for="staging_bot_block_extra_user_agents"><?php esc_html_e( 'Additional user agents to block', 'staging-bot-block' ); ?></label>
</th>
<td>
<textarea name="staging_bot_block_options[extra_user_agents]" id="staging_bot_block_extra_user_agents" rows="5" class="large-text code"><?php echo esc_textarea( $options['extra_user_agents'] ); ?></textarea>
<p class="description"><?php esc_html_e( 'Optional. One user agent or substring per line. These are added to the default search engine list.', 'staging-bot-block' ); ?></p>
</td>
</tr>
                        </tbody>
                </table>

                <?php submit_button(); ?>

        </form>

        <?php

}
