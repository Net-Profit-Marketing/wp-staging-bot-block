<?php

/**
 * Displays a warning banner.
 *
 * This function is responsible for showing a warning banner when certain conditions are met.
 *
 * @return void
 */
function bb_enable_warning_banner_function() {

	$bb_redirect_setting_id = get_bb_redirect_settings_post_id();

	if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', true ) == 1 ) {

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enable_warning_banner', true ) ) {

			if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true ) ) {

				$bb_url_val_initialize = "";

				if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true ) ) {

					$bb_url_val_initialize = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true );

				}

				$bb_warning_message = "";

				$bb_choice_val = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true );

				if ( $bb_choice_val == "Block Bots" ) {

					$bb_warning_message = 'Staging site is currently blocking search engine bots. <a href="' . esc_url( site_url( '/wp-admin/admin.php?page=block-bot-redirect-setting-page' ) ) . '">Edit Settings</a>.';

				} elseif ( $bb_choice_val == "Redirect Bots" ) {

					$bb_warning_message = 'Staging site is currently redirecting bots to ' . $bb_url_val_initialize . '. <a href="' . esc_url( site_url( '/wp-admin/admin.php?page=block-bot-redirect-setting-page' ) ) . '">Edit Settings</a>.';

				} elseif ( $bb_choice_val == "Redirect Bots & Users" ) {

					$bb_warning_message = 'Staging site is currently redirecting users and bots to ' . $bb_url_val_initialize . '. <a href="' . esc_url( site_url( '/wp-admin/admin.php?page=block-bot-redirect-setting-page' ) ) . '">Edit Settings</a>..';

				}

				$class = 'notice notice-warning is-dismissible';

				if ( $bb_warning_message != "" ) {

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $bb_warning_message );

				}



			}

		}

	}

}

add_action( 'admin_notices', 'bb_enable_warning_banner_function' );