<?php

/**
 * Handles the submission of the bb redirect settings form.
 *
 * This function is responsible for sanitizing, validating and saving the settings from the bb redirect settings form.
 *
 * @return void
 */
function bb_redirect_setting_form_submition_save_update_function() {

	$bb_redirect_setting_id = get_bb_redirect_settings_post_id();

	if ( $bb_redirect_setting_id == "" ) {

		$bb_redirect_setting_post_arr = array(

			'post_title'   => 'Block Bots Redirect',
			'post_content' => 'A plugin to use when developing a site or hosting a staging environment. It will help fix issues with staging sites getting indexed by Google, redirecting staging sites once they do get indexed, and forgetting about setting robots.',
			'post_status'  => 'publish',

			'post_author'  => get_current_user_id(),

			'post_type'    => 'bb_redirect_burgeon'

		);

		$bb_redirect_setting_id = wp_insert_post( $bb_redirect_setting_post_arr, true );
	}

	if ( isset( $bb_redirect_setting_id ) && $bb_redirect_setting_id != "" && ! empty( $bb_redirect_setting_id ) ) {

		if ( ! add_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', sanitize_text_field( $_POST['bb_redirect_enabled'] ), true ) ) {

			update_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', sanitize_text_field( $_POST['bb_redirect_enabled'] ) );
		}

		if ( ! add_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', sanitize_text_field( $_POST['bb_redirect_choice'] ), true ) ) {

			update_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', sanitize_text_field( $_POST['bb_redirect_choice'] ) );
		}

		if ( ! add_post_meta( $bb_redirect_setting_id, 'bb_redirect_enable_warning_banner', sanitize_text_field( $_POST['bb_redirect_enable_warning_banner'] ), true ) ) {

			update_post_meta( $bb_redirect_setting_id, 'bb_redirect_enable_warning_banner', sanitize_text_field( $_POST['bb_redirect_enable_warning_banner'] ) );
		}

		if ( ! add_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', sanitize_text_field( $_POST['bb_redirect_url'] ), true ) ) {

			update_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', sanitize_text_field( $_POST['bb_redirect_url'] ) );
		}
	}

	setcookie( "bb_submit_message", "Success", time() + ( 10 ), "/" );

	wp_safe_redirect( esc_url( site_url( '/wp-admin/admin.php?page=block-bot-redirect-setting-page' ) ) );

	exit();
}

add_action( 'admin_post_bb_redirect_setting_form_submition', 'bb_redirect_setting_form_submition_save_update_function' );
