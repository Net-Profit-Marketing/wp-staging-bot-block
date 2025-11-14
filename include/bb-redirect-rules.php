<?php

function staging_bot_block_send_robots_header() {
        if ( ! headers_sent() ) {
                header( 'X-Robots-Tag: noindex, nofollow', true );
        }
}

function staging_bot_block_should_bypass_request() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

        return ( false !== stripos( $uri, '.well-known/acme-challenge' ) );
}

function staging_bot_block_maybe_intercept() {
        if ( staging_bot_block_should_bypass_request() ) {
                return;
        }

        $options = staging_bot_block_get_options();

        if ( empty( $options['enabled'] ) ) {
                return;
        }

$mode          = staging_bot_block_get_effective_mode( $options );
$redirect_type = (int) ( isset( $options['redirect_type'] ) ? $options['redirect_type'] : 302 );
$redirect_url  = isset( $options['redirect_url'] ) ? trim( $options['redirect_url'] ) : '';
$user_agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';

if ( 'redirect_all' === $mode ) {
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
staging_bot_block_send_robots_header();
wp_redirect( esc_url_raw( $redirect_url ), $redirect_type );
exit;
}

                return;
        }

        if ( ! sbb_is_blocked_bot( $user_agent ) ) {
                return;
        }

        if ( 'block' === $mode ) {
                staging_bot_block_send_robots_header();
                status_header( 403 );
                exit;
        }

        if ( 'redirect_bots' === $mode && ! empty( $redirect_url ) ) {
                staging_bot_block_send_robots_header();
                wp_redirect( esc_url_raw( $redirect_url ), $redirect_type );
                exit;
        }
}

add_action( 'template_redirect', 'staging_bot_block_maybe_intercept', 0 );
