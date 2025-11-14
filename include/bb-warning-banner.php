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

$mode          = staging_bot_block_get_effective_mode( $options );
$bb_warning_message = '';
$settings_url       = esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) );
$link_text          = esc_html__( 'Edit Settings', 'staging-bot-block' );
$link               = sprintf( ' <a href="%1$s">%2$s</a>.', $settings_url, $link_text );

if ( 'block' === $mode ) {
$bb_warning_message = esc_html__( 'Bots are being blocked on this staging site.', 'staging-bot-block' );
} elseif ( 'redirect_bots' === $mode ) {
$bb_warning_message = esc_html__( 'Search engine bots are being redirected to the live site.', 'staging-bot-block' );
} elseif ( 'redirect_all' === $mode ) {
$bb_warning_message = esc_html__( 'All visitors are being redirected from this staging domain.', 'staging-bot-block' );
}

if ( $bb_warning_message ) {
$class = 'notice notice-error';
printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $bb_warning_message . $link ) );
}

}

add_action( 'admin_notices', 'bb_enable_warning_banner_function' );
