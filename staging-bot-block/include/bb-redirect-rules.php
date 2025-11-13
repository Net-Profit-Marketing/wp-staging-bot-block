<?php

/**
 * Creates a redirecting URL for the bb plugin.
 *
 * This function is responsible for generating a URL that will be used for redirecting users or bots.
 *
 * @return string Returns the generated URL.
 */
function bb_redirect_setting_create_redirecting_url() {

	$bb_redirect_setting_get_redirect_url = "";

	$bb_redirect_setting_id = get_bb_redirect_settings_post_id();

	if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true ) ) {

		//Get redirect url option

		$bb_url_val_initialize = rtrim( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_url', true ), "/" );

		//Get current url address

		$bb_current_url_val = get_site_url() . $_SERVER['REQUEST_URI'];

		$bb_get_old_url_array = parse_url( $bb_current_url_val );

		$bb_get_new_url_array = parse_url( $bb_url_val_initialize );

		if ( isset( $bb_get_old_url_array['host'] ) && ! empty( $bb_get_old_url_array['host'] ) && isset( $bb_get_new_url_array['host'] ) && ! empty( $bb_get_new_url_array['host'] ) ) {



			$bb_get_old_url = $bb_get_old_url_array['host'];

			$bb_get_new_url = $bb_get_new_url_array['host'];

			$bb_get_old_scheme = $bb_get_old_url_array['scheme'];

			$bb_get_new_scheme = $bb_get_new_url_array['scheme'];

			if ( $bb_get_old_url != $bb_get_new_url ) {

				$bb_redirect_setting_get_redirect_url = str_replace( $bb_get_old_url, $bb_get_new_url, $bb_current_url_val );

				$bb_redirect_setting_get_redirect_url = str_replace( $bb_get_old_scheme, $bb_get_new_scheme, $bb_redirect_setting_get_redirect_url );



			}

		}

	}

	return $bb_redirect_setting_get_redirect_url;

}

add_filter( 'bb_redirect_settings_plugin_filter', 'bb_redirect_setting_create_redirecting_url' );



/**
 * Sets the redirecting rules for the bb plugin.
 *
 * This function is responsible for defining the rules that determine when and where users or bots should be redirected.
 *
 * @return void
 */
function bb_redirect_setting_set_redirecting_rules() {


	# LetsEncrypt bypass
	if ( stristr( $_SERVER['REQUEST_URI'], 'well-known/acme-challenge' ) ) {
		return;
	} 
	if ( get_bb_redirect_settings_post_id() ) {

		$bb_redirect_setting_id = get_bb_redirect_settings_post_id();

		if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_enabled', true ) == 1 ) {

			if ( get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true ) ) {

				$bb_redirect_choice_check = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_choice', true );

				if ( bb_redirect_settings_isBotDetected_preg_match_function() ) {

					if ( $bb_redirect_choice_check != "Block Bots" ) {

						if ( bb_redirect_setting_create_redirecting_url() ) {

							$bb_redirect_type = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_type', true );
							if ( ! empty( $bb_redirect_type ) ) {
								wp_redirect( esc_url( bb_redirect_setting_create_redirecting_url() ), $bb_redirect_type );
							} else {
								wp_redirect( esc_url( bb_redirect_setting_create_redirecting_url() ) );
							}

							exit();

						}



					} elseif ( $bb_redirect_choice_check == "Block Bots" ) {

						http_response_code( 404 );

						exit();

					}



				} else {

					$bb_settingExplodeUrl = explode( "?", get_site_url() . $_SERVER['REQUEST_URI'] );

					if ( $bb_redirect_choice_check == "Redirect Bots & Users" ) {

						if ( bb_redirect_setting_create_redirecting_url() ) {

							if ( ! is_admin() ) {

								if ( wp_login_url() != $bb_settingExplodeUrl[0] ) {
									$bb_redirect_type = get_post_meta( $bb_redirect_setting_id, 'bb_redirect_type', true );
									if ( ! empty( $bb_redirect_type ) ) {
										wp_redirect( esc_url( bb_redirect_setting_create_redirecting_url() ), $bb_redirect_type );
									} else {
										wp_redirect( esc_url( bb_redirect_setting_create_redirecting_url() ) );
									}

									exit();

								}

							}

						}

					}

				}

			}

		}

	}

}

//add_action( 'bb_redirect_setting_set_redirecting_rules_action', 'bb_redirect_setting_set_redirecting_rules' );

add_action( 'init', 'bb_redirect_setting_set_redirecting_rules' );