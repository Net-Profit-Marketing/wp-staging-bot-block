<?php

/**
 * Creates a redirecting URL for the bb plugin.
 *
 * This function is responsible for generating a URL that will be used for redirecting users or bots.
 *
 * @return string Returns the generated URL.
 */
function bb_redirect_setting_create_redirecting_url() {

        $bb_redirect_setting_get_redirect_url = '';

        $options = staging_bot_block_get_options();
        $redirect_url_setting = isset( $options['redirect_url'] ) ? rtrim( $options['redirect_url'], '/' ) : '';

        if ( $redirect_url_setting ) {

                $bb_current_url_val   = get_site_url() . $_SERVER['REQUEST_URI'];
                $bb_get_old_url_array = parse_url( $bb_current_url_val );
                $bb_get_new_url_array = parse_url( $redirect_url_setting );

                if ( isset( $bb_get_old_url_array['host'] ) && ! empty( $bb_get_old_url_array['host'] ) && isset( $bb_get_new_url_array['host'] ) && ! empty( $bb_get_new_url_array['host'] ) ) {



                        $bb_get_old_url   = $bb_get_old_url_array['host'];
                        $bb_get_new_url   = $bb_get_new_url_array['host'];
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

        $options = staging_bot_block_get_options();

        if ( empty( $options['enabled'] ) ) {
                return;
        }

        $mode          = isset( $options['mode'] ) ? $options['mode'] : 'block';
        $redirect_type = (int) ( isset( $options['redirect_type'] ) ? $options['redirect_type'] : 302 );
        $redirect_url  = bb_redirect_setting_create_redirecting_url();

        if ( bb_redirect_settings_isBotDetected_preg_match_function() ) {

                if ( 'block' === $mode ) {
                        http_response_code( 404 );
                        exit();
                }

                if ( $redirect_url ) {
                        wp_redirect( esc_url( $redirect_url ), $redirect_type );
                        exit();
                }
        } else {

                if ( 'redirect_all' === $mode && $redirect_url ) {

                        $bb_settingExplodeUrl = explode( "?", get_site_url() . $_SERVER['REQUEST_URI'] );

                        if ( ! is_admin() && isset( $bb_settingExplodeUrl[0] ) && wp_login_url() != $bb_settingExplodeUrl[0] ) {
                                wp_redirect( esc_url( $redirect_url ), $redirect_type );
                                exit();
                        }

                }

        }

}

//add_action( 'bb_redirect_setting_set_redirecting_rules_action', 'bb_redirect_setting_set_redirecting_rules' );

add_action( 'init', 'bb_redirect_setting_set_redirecting_rules' );