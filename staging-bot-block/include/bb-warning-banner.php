<?php

/**
 * Displays a warning banner.
 *
 * This function is responsible for showing a warning banner when certain conditions are met.
 *
 * @return void
 */
function bb_enable_warning_banner_function() {

        $options = staging_bot_block_get_options();

        if ( empty( $options['enabled'] ) || empty( $options['warning_banner'] ) ) {
                return;
        }

        $bb_warning_message = '';
        $settings_url       = esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) );
        $redirect_url       = isset( $options['redirect_url'] ) ? $options['redirect_url'] : '';

        if ( 'block' === $options['mode'] ) {
                $bb_warning_message = 'Staging site is currently blocking search engine bots. <a href="' . $settings_url . '">Edit Settings</a>.';
        } elseif ( 'redirect_bots' === $options['mode'] ) {
                $bb_warning_message = 'Staging site is currently redirecting bots to ' . esc_html( $redirect_url ) . '. <a href="' . $settings_url . '">Edit Settings</a>.';
        } elseif ( 'redirect_all' === $options['mode'] ) {
                $bb_warning_message = 'Staging site is currently redirecting users and bots to ' . esc_html( $redirect_url ) . '. <a href="' . $settings_url . '">Edit Settings</a>.';
        }

        if ( $bb_warning_message ) {
                $class = 'notice notice-warning is-dismissible';
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $bb_warning_message ) );
        }

}

add_action( 'admin_notices', 'bb_enable_warning_banner_function' );