<?php

//GET BB Redirect ID

add_filter( 'bb_redirect_settings_plugin_filter', 'get_bb_redirect_settings_post_id' );

function get_bb_redirect_settings_post_id() {

	$bb_redirect_setting_id = "";

	$bb_redirect_setting_args = array(

		'numberposts' => 1,

		'post_title'  => 'Block Bots Redirect',

		'post_type'   => 'bb_redirect_burgeon',

		'orderby'     => 'date',

		'order'       => 'DESC'

	);

	$bb_redirect_setting_array = get_posts( $bb_redirect_setting_args );

	if ( ! empty( $bb_redirect_setting_array ) ) {

		foreach ( $bb_redirect_setting_array as $bb_rows ) {

			$bb_redirect_setting_id = $bb_rows->ID;

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

	$bb_redirect_setting_id = get_bb_redirect_settings_post_id();

	$bb_enabled_initialize = $bb_choice_val_initialize = $bb_enable_warning_initialize = $bb_url_val_initialize = "";



	if ( $bb_redirect_setting_id != "" ) {

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', true ) ) {

			$bb_enabled_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', true ) == 1 ? "checked" : "";

		}

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true ) ) {

			$bb_choice_val_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true );

		} else {

			$bb_choice_val_initialize = "Block Bots";

		}

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enable_warning_banner', true ) ) {

			$bb_enable_warning_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enable_warning_banner', true ) == 1 ? "checked" : "";

		}

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true ) ) {

			$bb_url_val_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true );

		}
		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_type', true ) ) {

			$bb_redirect_type_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_type', true );

		}

	}

	?>

	<div class="wrap">

		<h1 class="wp-heading-inline">
			<?php esc_html_e( 'Staging Bot Block', 'bot-bLockdomain' ); ?>
		</h1>

		<p></p>



	</div>

	<form method="post" id="bb_redirect_settings_mainform" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		enctype="multipart/form-data" onsubmit="return bb_redirect_settings_mainform_submit_validation();">

		<h1 class="screen-reader-text">Staging Bot Block</h1>

		<?php if ( isset( $_COOKIE['bb_submit_message'] ) && $_COOKIE['bb_submit_message'] == "Success" ) {

			setcookie( "bb_submit_message", "", time() - 3600 );

			?>

			<div id="message" class="updated">

				<p>Bots Block Redirect setting has been saved successfully.</p>

			</div>

		<?php } ?>

		<p>A plugin to use when developing a site or hosting a staging environment. It will help fix issues with staging
			sites getting indexed by Google, redirecting staging sites once they do get indexed, and forgetting about
			setting robots.txt to nofollow and accidentally deindexing the production site.</p>
		<p>Brought to you by the team at <a href='https://www.netprofitmarketing.com' target='_blank'>Net Profit
				Marketing</a>.
		</p>

		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="bb_redirect_enabled">Enable/Disable </label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Enable/Disable</span></legend>
							<label for="bb_redirect_enabled">
								<input class="" type="checkbox" name="bb_redirect_enabled" id="bb_redirect_enabled" <?php echo $bb_enabled_initialize; ?> style="" value="1"> Enable bot block
							</label><br>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="bb_redirect_title">Block/Redirect settings
							<span class="bb-redirect-tool" data-tip="<?php echo esc_attr( 'Options to block bots (i.e. Google, Bing) with a 404 error, redirect bots to a different URL or redirect both bots and users (except for wp-admin).' ) ?>" tabindex="2">
								<img class="bb-redirect-tool-icon-img" src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/info.svg'; ?>" alt="info" width="12" height="12">
							</span>
						</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Block/Redirect settings</span></legend>
							<label for="block_bots">
								<input type="radio" id="block_bots" name="bb_redirect_choice" <?php echo $bb_choice_val_initialize == "Block Bots" ? "checked" : "" ?> value="Block Bots">
								Block Bots
							</label>
							<label for="redirect_bots">
								<input type="radio" id="redirect_bots" name="bb_redirect_choice" <?php echo $bb_choice_val_initialize == "Redirect Bots" ? "checked" : "" ?> value="Redirect Bots">
								Redirect Bots
							</label>
							<label for="redirect_bots_user">
								<input type="radio" id="redirect_bots_user" name="bb_redirect_choice" <?php echo $bb_choice_val_initialize == "Redirect Bots & Users" ? "checked" : "" ?> value="Redirect Bots & Users">
								Redirect Bots & Users
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="bb_redirect_enable_warning_banner">Warning Banner </label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Enable Warning Banner</span></legend>
							<label for="bb_redirect_enable_warning_banner">
								<input class="" type="checkbox" name="bb_redirect_enable_warning_banner" <?php echo $bb_enable_warning_initialize; ?> id="bb_redirect_enable_warning_banner" style="" value="1"> Enable Warning Banner
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="bb_redirect_description">Redirect URL
							<span class="bb-redirect-tool" data-tip="Enter the URL, on which you want to redirect." tabindex="2">
								<img class="bb-redirect-tool-icon-img" src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/info.svg'; ?>" alt="info" width="12" height="12">
							</span>
						</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Redirect URL</span></legend>
							<input class="input-text regular-input" type="text" name="bb_redirect_url" id="bb_redirect_url" style="width: 100%;max-width: 400px;" placeholder="https://example.com/" value="<?php echo $bb_url_val_initialize; ?>">
							<p class='error bb_redirect_url_error_cls' id="bb_redirect_url_error"></p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="bb_redirect_description">Redirect Type
							<span class="bb-redirect-tool" data-tip="Set Redirect Type." tabindex="2">
								<img class="bb-redirect-tool-icon-img" src="<?php echo plugin_dir_url( __FILE__ ) . '../assets/images/info.svg'; ?>" alt="info" width="12" height="12">
							</span>
						</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Redirect Type</span></legend>
							<select name="bb_redirect_type" id="bb_redirect_type" style="width: 100%;max-width: 400px;">
								<option value="302" <?php echo $bb_redirect_type_initialize == "302" ? "selected" : ""; ?>>Temporary (302)</option>
								<option value="301" <?php echo $bb_redirect_type_initialize == "301" ? "selected" : ""; ?>>Permanent (301)</option>
							</select>
							<p class='error bb_redirect_type_error_cls' id="bb_redirect_type_error"></p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">

			<input type="hidden" name="action" value="bb_redirect_setting_form_submition">

			<button name="save" class="button-primary save-button" type="submit" value="Save">Save</button>

		</p>

	</form>

	<?php

}





