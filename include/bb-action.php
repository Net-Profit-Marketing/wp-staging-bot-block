<?php

function staging_bot_block_get_default_options() {
return array(
'enabled'           => 0,
'mode'              => 'block',
'redirect_url'      => '',
'redirect_type'     => 302,
'warning_banner'    => 1,
                'extra_user_agents' => '',
        );
}

function staging_bot_block_register_settings() {
        register_setting( 'staging_bot_block_options_group', 'staging_bot_block_options', 'staging_bot_block_sanitize_options' );
}
add_action( 'admin_init', 'staging_bot_block_register_settings' );

function staging_bot_block_sanitize_options( $input ) {
        $input   = is_array( $input ) ? $input : array();
        $output  = staging_bot_block_get_default_options();
        $allowed = array( 'block', 'redirect_bots', 'redirect_all' );

        $output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

        $mode = isset( $input['mode'] ) ? sanitize_key( $input['mode'] ) : 'block';
        $output['mode'] = in_array( $mode, $allowed, true ) ? $mode : 'block';

        $redirect_url             = isset( $input['redirect_url'] ) ? trim( wp_unslash( $input['redirect_url'] ) ) : '';
        $output['redirect_url']   = esc_url_raw( $redirect_url );
$redirect_type            = isset( $input['redirect_type'] ) ? (int) $input['redirect_type'] : 302;
$output['redirect_type']  = 301 === $redirect_type ? 301 : 302;
$output['warning_banner'] = ! empty( $input['warning_banner'] ) ? 1 : 0;
$extra_user_agents        = isset( $input['extra_user_agents'] ) ? wp_unslash( $input['extra_user_agents'] ) : '';
$output['extra_user_agents'] = trim( $extra_user_agents );

if ( in_array( $output['mode'], array( 'redirect_bots', 'redirect_all' ), true ) && empty( $output['redirect_url'] ) ) {
$output['mode'] = 'block';

if ( is_admin() ) {
add_settings_error(
'staging_bot_block_options',
'staging_bot_block_redirect_url_missing',
__( 'Redirect mode is enabled but no Redirect URL is set. Blocking bots instead until a URL is configured.', 'staging-bot-block' ),
'error'
);
}
}

return $output;
}

function staging_bot_block_get_options() {
$options = get_option( 'staging_bot_block_options', null );

        if ( null === $options ) {
                $options = staging_bot_block_migrate_legacy_settings();
        }

        if ( ! is_array( $options ) ) {
                $options = array();
        }

return wp_parse_args( $options, staging_bot_block_get_default_options() );
}

function staging_bot_block_get_effective_mode( $options ) {
$mode = isset( $options['mode'] ) ? $options['mode'] : 'block';
$redirect_url = isset( $options['redirect_url'] ) ? trim( $options['redirect_url'] ) : '';

if ( in_array( $mode, array( 'redirect_bots', 'redirect_all' ), true ) && '' === $redirect_url ) {
return 'block';
}

return $mode;
}

function staging_bot_block_migrate_legacy_settings() {
        $defaults       = staging_bot_block_get_default_options();
        $legacy_options = array();
        $post_id        = staging_bot_block_get_redirect_settings_post_id();

        if ( $post_id ) {
                $legacy_enabled = get_post_meta( $post_id, 'bb_redirect_enabled', true );
                if ( '' !== $legacy_enabled ) {
                        $legacy_options['enabled'] = (int) ( 1 === (int) $legacy_enabled );
                }

                $legacy_choice = get_post_meta( $post_id, 'bb_redirect_choice', true );
                if ( $legacy_choice ) {
                        $legacy_options['mode'] = staging_bot_block_map_legacy_mode( $legacy_choice );
                }

                $legacy_url = get_post_meta( $post_id, 'bb_redirect_url', true );
                if ( $legacy_url ) {
                        $legacy_options['redirect_url'] = $legacy_url;
                }

                $legacy_type = (int) get_post_meta( $post_id, 'bb_redirect_type', true );
                if ( in_array( $legacy_type, array( 301, 302 ), true ) ) {
                        $legacy_options['redirect_type'] = $legacy_type;
                }

                $legacy_banner = get_post_meta( $post_id, 'bb_redirect_enable_warning_banner', true );
                if ( '' !== $legacy_banner ) {
                        $legacy_options['warning_banner'] = (int) ( 1 === (int) $legacy_banner );
                }
        }

        $options = wp_parse_args( $legacy_options, $defaults );
        $options = staging_bot_block_sanitize_options( $options );
        update_option( 'staging_bot_block_options', $options );

        return $options;
}

function staging_bot_block_map_legacy_mode( $legacy_choice ) {
        $map = array(
                'Block Bots'            => 'block',
                'Redirect Bots'         => 'redirect_bots',
                'Redirect Bots & Users' => 'redirect_all',
        );

        return isset( $map[ $legacy_choice ] ) ? $map[ $legacy_choice ] : 'block';
}

function staging_bot_block_activate() {
$stored = get_option( 'staging_bot_block_options', null );

if ( null === $stored ) {
$options = staging_bot_block_get_default_options();
} else {
$options = staging_bot_block_sanitize_options( (array) $stored );
}

update_option( 'staging_bot_block_options', $options );
update_option( 'staging_bot_block_show_activation_notice', 1 );
}

function staging_bot_block_activation_notice() {
if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
return;
}

$show_notice = get_option( 'staging_bot_block_show_activation_notice' );

if ( ! $show_notice ) {
return;
}

$options = staging_bot_block_get_options();

if ( ! empty( $options['enabled'] ) ) {
delete_option( 'staging_bot_block_show_activation_notice' );
return;
}

$settings_url = esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) );
$message      = sprintf(
/* translators: %s: settings page URL */
__( 'Staging Bot Block is installed. <a href="%s">Visit the settings page</a> to enable bot blocking for this site.', 'staging-bot-block' ),
$settings_url
);

printf( '<div class="notice notice-info"><p>%s</p></div>', wp_kses_post( $message ) );
delete_option( 'staging_bot_block_show_activation_notice' );
}

add_action( 'admin_notices', 'staging_bot_block_activation_notice' );
