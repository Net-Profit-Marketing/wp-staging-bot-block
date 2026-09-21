<?php
/**
 * Option validation and lifecycle operations.
 *
 * @package Staging_Bot_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the complete, disabled-by-default option schema.
 *
 * @return array
 */
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

/**
 * Normalize options without writes or Settings API errors.
 *
 * @param mixed $input Option value.
 * @return array
 */
function staging_bot_block_normalize_options( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$output = staging_bot_block_get_default_options();
	foreach ( array( 'enabled', 'warning_banner' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$output[ $key ] = in_array( $input[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
	}
	if ( isset( $input['mode'] ) && in_array( $input['mode'], array( 'block', 'redirect_bots', 'redirect_all' ), true ) ) {
		$output['mode'] = $input['mode'];
	}
	if ( isset( $input['redirect_type'] ) && in_array( $input['redirect_type'], array( 301, '301' ), true ) ) {
		$output['redirect_type'] = 301;
	}
	if ( isset( $input['redirect_url'] ) ) {
		$output['redirect_url'] = staging_bot_block_validate_redirect_url( $input['redirect_url'] );
	}
	if ( isset( $input['extra_user_agents'] ) && is_string( $input['extra_user_agents'] ) ) {
		$output['extra_user_agents'] = trim( sanitize_textarea_field( $input['extra_user_agents'] ) );
	}
	return $output;
}

/**
 * Register the Settings API option; options.php checks capabilities and nonces.
 */
function staging_bot_block_register_settings() {
	register_setting(
		'staging_bot_block_options_group',
		'staging_bot_block_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'staging_bot_block_sanitize_options',
			'default'           => staging_bot_block_get_default_options(),
		)
	);
}
add_action( 'admin_init', 'staging_bot_block_register_settings' );

/**
 * Sanitize a full form submission, including unchecked checkboxes.
 *
 * Settings API input is already unslashed by options.php.
 *
 * @param mixed $input Submitted settings.
 * @return array
 */
function staging_bot_block_sanitize_options( $input ) {
	$input                   = is_array( $input ) ? $input : array();
	$input['enabled']        = isset( $input['enabled'] ) ? $input['enabled'] : 0;
	$input['warning_banner'] = isset( $input['warning_banner'] ) ? $input['warning_banner'] : 0;
	$output                  = staging_bot_block_normalize_options( $input );
	$has_invalid_url         = ! empty( $input['redirect_url'] ) && '' === $output['redirect_url'];
	$needs_redirect          = in_array( $output['mode'], array( 'redirect_bots', 'redirect_all' ), true );
	if ( $has_invalid_url || ( $needs_redirect && '' === $output['redirect_url'] ) ) {
		add_settings_error(
			'staging_bot_block_options',
			'staging_bot_block_redirect_url_invalid',
			__( 'Enter a valid HTTP or HTTPS destination outside this staging site. Credentials, malformed URLs, and redirect loops are not allowed. Recognized bots will be blocked until a valid destination is saved.', 'staging-bot-block' ),
			'error'
		);
	}
	return $output;
}

/**
 * Read normalized settings without legacy post queries or option writes.
 *
 * @return array
 */
function staging_bot_block_get_options() {
	return staging_bot_block_normalize_options( get_option( 'staging_bot_block_options', array() ) );
}

/**
 * Fail safely to recognized-bot blocking when a redirect becomes invalid.
 *
 * @param array $options Options to inspect.
 * @return string
 */
function staging_bot_block_get_effective_mode( $options ) {
	$options = staging_bot_block_normalize_options( $options );
	if ( 'block' !== $options['mode'] && '' === $options['redirect_url'] ) {
		return 'block';
	}
	return $options['mode'];
}

/**
 * Find the latest legacy settings post only during one-time migration.
 *
 * @return int
 */
function staging_bot_block_get_redirect_settings_post_id() {
	$args = array(
		'numberposts' => 1,
		'post_type'   => 'bb_redirect_npm',
		'post_status' => 'any',
		'orderby'     => 'date',
		'order'       => 'DESC',
		'fields'      => 'ids',
	);
	$ids  = get_posts( $args );
	if ( empty( $ids ) ) {
		$args['post_type'] = 'any';
		// This compatibility query runs once in admin/activation, never on the frontend.
		$args['meta_key'] = 'bb_redirect_enabled'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Legacy data had no reliable post type.
		$ids              = get_posts( $args );
	}
	return empty( $ids ) ? 0 : (int) $ids[0];
}

/**
 * Map legacy labels to the stable option values.
 *
 * @param string $legacy_choice Legacy label.
 * @return string
 */
function staging_bot_block_map_legacy_mode( $legacy_choice ) {
	$map = array(
		'Block Bots'            => 'block',
		'Redirect Bots'         => 'redirect_bots',
		'Redirect Bots & Users' => 'redirect_all',
	);
	return is_string( $legacy_choice ) && isset( $map[ $legacy_choice ] ) ? $map[ $legacy_choice ] : 'block';
}

/**
 * Import legacy options without deleting posts or replacing existing settings.
 *
 * @return array
 */
function staging_bot_block_migrate_legacy_settings() {
	$stored = get_option( 'staging_bot_block_options', null );
	if ( null !== $stored ) {
		return staging_bot_block_normalize_options( $stored );
	}
	$options = staging_bot_block_get_default_options();
	$post_id = staging_bot_block_get_redirect_settings_post_id();
	if ( $post_id ) {
		$fields = array(
			'enabled'        => 'bb_redirect_enabled',
			'redirect_url'   => 'bb_redirect_url',
			'redirect_type'  => 'bb_redirect_type',
			'warning_banner' => 'bb_redirect_enable_warning_banner',
		);
		foreach ( $fields as $key => $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $value ) {
				$options[ $key ] = $value;
			}
		}
		$options['mode'] = staging_bot_block_map_legacy_mode( get_post_meta( $post_id, 'bb_redirect_choice', true ) );
	}
	$options = staging_bot_block_normalize_options( $options );
	add_option( 'staging_bot_block_options', $options );
	return $options;
}

/**
 * Initialize upgrades only on an authorized administration request.
 */
function staging_bot_block_maybe_migrate_settings() {
	if ( current_user_can( 'manage_options' ) && ! is_network_admin() ) {
		staging_bot_block_migrate_legacy_settings();
	}
}
add_action( 'admin_init', 'staging_bot_block_maybe_migrate_settings', 5 );

/**
 * Initialize only missing options on per-site activation.
 *
 * @param bool $network_wide Whether network activation was requested.
 */
function staging_bot_block_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		wp_die(
			esc_html__( 'Staging Bot Block supports activation on individual sites only. Activate it from the Plugins screen for each site that needs protection.', 'staging-bot-block' ),
			esc_html__( 'Per-site activation required', 'staging-bot-block' ),
			array( 'back_link' => true )
		);
	}
	staging_bot_block_migrate_legacy_settings();
	update_option( 'staging_bot_block_show_activation_notice', 1 );
}
