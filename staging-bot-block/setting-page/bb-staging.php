<?php

function get_bb_redirect_settings_post_id() {

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
function bb_page_menu() {

        add_menu_page(

                __( 'Bot BLock', 'bot-bLockdomain' ),

                __( 'Bot BLock', 'bot-bLockdomain' ),

                'manage_options',

                'block-bot-redirect-setting-page',

                'bb_admin_page_contents',

                'dashicons-schedule',

                3

        );

}

add_action( 'admin_menu', 'bb_page_menu' );



/**
 * Renders the contents of the admin settings page for the bb plugin.
 *
 * This function is responsible for displaying the settings form in the WordPress admin area.
 *
 * @return void
 */
function bb_admin_page_contents() {

        $options = staging_bot_block_get_options();
        ?>
        <div class="wrap">
                <h1 class="wp-heading-inline">
                        <?php esc_html_e( 'Staging Bot Block', 'bot-bLockdomain' ); ?>
                </h1>
                <p></p>
        </div>
        <?php
        if ( isset( $_GET['settings-updated'] ) ) {
                add_settings_error( 'staging_bot_block_options', 'staging_bot_block_options', __( 'Settings saved.', 'bot-bLockdomain' ), 'updated' );
        }
        settings_errors( 'staging_bot_block_options' );
        ?>
        <form method="post" id="bb_redirect_settings_mainform" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( 'staging_bot_block_options_group' ); ?>
                <h1 class="screen-reader-text">Staging Bot Block</h1>

                <p><?php esc_html_e( 'A plugin to use when developing a site or hosting a staging environment. It will help fix issues with staging sites getting indexed by Google, redirecting staging sites once they do get indexed, and forgetting about setting robots.txt to nofollow and accidentally deindexing the production site.', 'bot-bLockdomain' ); ?></p>
                <p><?php printf( wp_kses_post( __( 'Brought to you by the team at %s.', 'bot-bLockdomain' ) ), '<a href="' . esc_url( 'https://www.netprofitmarketing.com' ) . '" target="_blank" rel="noopener noreferrer">Net Profit Marketing</a>' ); ?></p>

                <table class="form-table" role="presentation">
                        <tbody>
                                <tr>
                                        <th scope="row">
                                                <label for="staging_bot_block_enabled"><?php esc_html_e( 'Enable Staging Bot Block', 'bot-bLockdomain' ); ?></label>
                                        </th>
                                        <td>
                                                <label for="staging_bot_block_enabled">
                                                        <input type="checkbox" name="staging_bot_block_options[enabled]" id="staging_bot_block_enabled" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>>
                                                        <?php esc_html_e( 'Enable Staging Bot Block', 'bot-bLockdomain' ); ?>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Turn the plugin on or off for this site.', 'bot-bLockdomain' ); ?></p>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Bot handling mode', 'bot-bLockdomain' ); ?></th>
                                        <td>
                                                <fieldset>
                                                        <legend class="screen-reader-text">
                                                                <span><?php esc_html_e( 'Bot handling mode', 'bot-bLockdomain' ); ?></span>
                                                        </legend>
                                                        <label for="staging_bot_block_mode_block">
                                                                <input type="radio" id="staging_bot_block_mode_block" name="staging_bot_block_options[mode]" value="block" <?php checked( $options['mode'], 'block' ); ?>>
                                                                <?php esc_html_e( 'Block search engine bots (recommended for staging)', 'bot-bLockdomain' ); ?>
                                                        </label><br>
                                                        <label for="staging_bot_block_mode_redirect_bots">
                                                                <input type="radio" id="staging_bot_block_mode_redirect_bots" name="staging_bot_block_options[mode]" value="redirect_bots" <?php checked( $options['mode'], 'redirect_bots' ); ?>>
                                                                <?php esc_html_e( 'Redirect search engine bots to live site', 'bot-bLockdomain' ); ?>
                                                        </label><br>
                                                        <label for="staging_bot_block_mode_redirect_all">
                                                                <input type="radio" id="staging_bot_block_mode_redirect_all" name="staging_bot_block_options[mode]" value="redirect_all" <?php checked( $options['mode'], 'redirect_all' ); ?>>
                                                                <?php esc_html_e( 'Redirect everyone (bots and users) to live site', 'bot-bLockdomain' ); ?>
                                                        </label>
                                                </fieldset>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row">
                                                <label for="staging_bot_block_warning_banner"><?php esc_html_e( 'Show admin warning banner', 'bot-bLockdomain' ); ?></label>
                                        </th>
                                        <td>
                                                <label for="staging_bot_block_warning_banner">
                                                        <input type="checkbox" name="staging_bot_block_options[warning_banner]" id="staging_bot_block_warning_banner" value="1" <?php checked( ! empty( $options['warning_banner'] ) ); ?>>
                                                        <?php esc_html_e( 'Show admin warning banner', 'bot-bLockdomain' ); ?>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Show a persistent notice in the WordPress admin when Staging Bot Block is enabled.', 'bot-bLockdomain' ); ?></p>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row">
                                                <label for="staging_bot_block_redirect_url"><?php esc_html_e( 'Redirect URL', 'bot-bLockdomain' ); ?></label>
                                        </th>
                                        <td>
                                                <input class="regular-text" type="text" name="staging_bot_block_options[redirect_url]" id="staging_bot_block_redirect_url" value="<?php echo esc_attr( $options['redirect_url'] ); ?>" placeholder="https://example.com/">
                                                <p class="description"><?php esc_html_e( 'Target URL for redirects in “Redirect” modes, for example the production site homepage.', 'bot-bLockdomain' ); ?></p>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row">
                                                <label for="staging_bot_block_redirect_type"><?php esc_html_e( 'Redirect type', 'bot-bLockdomain' ); ?></label>
                                        </th>
                                        <td>
                                                <select name="staging_bot_block_options[redirect_type]" id="staging_bot_block_redirect_type">
                                                        <option value="302" <?php selected( (int) $options['redirect_type'], 302 ); ?>><?php esc_html_e( 'Temporary (302)', 'bot-bLockdomain' ); ?></option>
                                                        <option value="301" <?php selected( (int) $options['redirect_type'], 301 ); ?>><?php esc_html_e( 'Permanent (301)', 'bot-bLockdomain' ); ?></option>
                                                </select>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row">
                                                <label for="staging_bot_block_extra_user_agents"><?php esc_html_e( 'Additional user agents to block', 'bot-bLockdomain' ); ?></label>
                                        </th>
                                        <td>
                                                <textarea name="staging_bot_block_options[extra_user_agents]" id="staging_bot_block_extra_user_agents" rows="5" class="large-text code"><?php echo esc_textarea( $options['extra_user_agents'] ); ?></textarea>
                                                <p class="description"><?php esc_html_e( 'Optional. One user agent or substring per line. These will be added to the default bot list.', 'bot-bLockdomain' ); ?></p>
                                        </td>
                                </tr>
                        </tbody>
                </table>

                <?php submit_button(); ?>

        </form>

        <?php

}
