<?php
/**
 * Run with wp eval-file on a disposable WordPress installation only.
 * SBB_DISPOSABLE_TESTS=1 is mandatory; this script changes plugin options/posts.
 * Run after activating staging-bot-block. It uses real WordPress APIs and never
 * follows redirects or sends requests to the synthetic destination.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'SBB_DISPOSABLE_TESTS' ) ) {
	throw new RuntimeException( 'Requires WP-CLI and SBB_DISPOSABLE_TESTS=1 on a disposable installation.' );
}
if ( ! function_exists( 'staging_bot_block_get_options' ) ) {
	throw new RuntimeException( 'Activate staging-bot-block before running integration checks.' );
}

$checks = 0;
$assert = static function ( $expected, $actual, $label ) use ( &$checks ) {
	++$checks;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
};
$previous_options = get_option( 'staging_bot_block_options', null );
$previous_notice = get_option( 'staging_bot_block_show_activation_notice', null );
$legacy_id = 0;
$redirect_probe = null;
try {
	$defaults = staging_bot_block_get_default_options();
	delete_option( 'staging_bot_block_options' );
	$assert( $defaults, staging_bot_block_get_options(), 'Defaults through real get_option' );
	$assert( null, get_option( 'staging_bot_block_options', null ), 'Getter did not persist defaults' );
	staging_bot_block_register_settings();
	$assert( null, get_option( 'staging_bot_block_options', null ), 'Explicit sentinel survives registered default' );

	$legacy_id = wp_insert_post( array( 'post_type' => 'bb_redirect_npm', 'post_status' => 'publish', 'post_title' => 'Disposable staging bot test settings' ), true );
	if ( is_wp_error( $legacy_id ) ) { throw new RuntimeException( $legacy_id->get_error_message() ); }
	update_post_meta( $legacy_id, 'bb_redirect_enabled', '1' );
	update_post_meta( $legacy_id, 'bb_redirect_choice', 'Redirect Bots' );
	update_post_meta( $legacy_id, 'bb_redirect_url', 'https://live.example.test/?keep=one%20two&value=2' );
	update_post_meta( $legacy_id, 'bb_redirect_type', '302' );
	$options = staging_bot_block_migrate_legacy_settings();
	$assert( 1, $options['enabled'], 'Real legacy post migration' );
	$assert( 'redirect_bots', $options['mode'], 'Real legacy mode migration' );
	$assert( $options, get_option( 'staging_bot_block_options' ), 'Migration persisted options' );
	staging_bot_block_activate();
	$assert( $options, get_option( 'staging_bot_block_options' ), 'Activation preserved configuration' );
	$assert( $legacy_id, get_post( $legacy_id )->ID, 'Legacy source preserved' );

	$assert( '', staging_bot_block_validate_redirect_url( home_url( '/any-path?x=1' ) ), 'Actual WordPress root loop rejected' );
	$assert( '', staging_bot_block_validate_redirect_url( 'https://[2001:db8::1]/' ), 'IPv6 literals unsupported by WordPress safe redirects rejected' );
	$assert( 'https://live.example.test/', staging_bot_block_validate_redirect_url( 'HTTPS://live.example.test/' ), 'Scheme compatible with WordPress validation' );
	$clean = staging_bot_block_sanitize_options( array( 'enabled' => 1, 'extra_user_agents' => "<b>Agent</b>\nSecond\\Agent" ) );
	$assert( "Agent\nSecond\\Agent", $clean['extra_user_agents'], 'WordPress textarea sanitization without double unslashing' );
	$assert( 0, $clean['warning_banner'], 'Unchecked checkbox saved false' );
	$clean = staging_bot_block_sanitize_options( array( 'mode' => 'redirect_all', 'redirect_url' => home_url() ) );
	$assert( 'block', staging_bot_block_get_effective_mode( $clean ), 'Unsafe redirect fallback' );
	$assert( true, count( get_settings_errors( 'staging_bot_block_options' ) ) > 0, 'Real Settings API validation message' );

	$observed = array();
	$redirect_probe = static function ( $location, $status ) use ( &$observed ) {
		$observed = array( $location, $status );
		return false; // Capture core validation without sending a Location header.
	};
	add_filter( 'wp_redirect', $redirect_probe, 999, 2 );
	// WP-CLI warns unconditionally even when a later filter cancels a redirect.
	// Remove only that diagnostic during this intentional, captured API exercise.
	$wp_cli_redirect_callback = 'WP_CLI\\Utils\\wp_redirect_handler';
	$wp_cli_redirect_priority = has_filter( 'wp_redirect', $wp_cli_redirect_callback );
	if ( false !== $wp_cli_redirect_priority ) {
		remove_filter( 'wp_redirect', $wp_cli_redirect_callback, $wp_cli_redirect_priority );
	}
	try {
		$assert( false, staging_bot_block_redirect_to_live_site(), 'Captured redirect intentionally cancelled' );
	} finally {
		if ( false !== $wp_cli_redirect_priority ) {
			add_filter( 'wp_redirect', $wp_cli_redirect_callback, $wp_cli_redirect_priority );
		}
	}
	$assert( array( $options['redirect_url'], 302 ), $observed, 'Core wp_safe_redirect accepted external saved host exactly' );
	$assert( '', wp_validate_redirect( $options['redirect_url'], '' ), 'External host permission removed after helper' );
	$assert( '/fallback/', apply_filters( 'wp_safe_redirect_fallback', '/fallback/', 302 ), 'Fallback permission removed after helper' );
	remove_filter( 'wp_redirect', $redirect_probe, 999 );
	$redirect_probe = null;

	$assert( 'redirect', staging_bot_block_get_request_action( $options, 'Googlebot/2.1', false, '/' ), 'Actual option bot redirect decision' );
	$options['mode'] = 'redirect_all';
	$assert( 'allow', staging_bot_block_get_request_action( $options, 'Googlebot/2.1', true, '/' ), 'Administrator bypass decision' );
	$assert( 'redirect', staging_bot_block_get_request_action( $options, '', false, '/' ), 'Anonymous empty UA redirect-all decision' );
	foreach ( array( '/.well-known/acme-challenge/abc123', '/.well-known/acme-challenge/a_b-c', '/.well-known/acme-challenge/abc123?anything=1' ) as $uri ) {
		$assert( 'allow', staging_bot_block_get_request_action( $options, 'Googlebot', false, $uri ), 'Valid ACME decision ' . $uri );
	}
	foreach ( array( '/.well-known/acme-challenge/', '/.well-known/acme-challenge', '/foo/.well-known/acme-challenge/abc', '/.well-known/acme-challenge/?foo=bar', '/.well-known/acme-challenge/../page', '/.well-known/acme-challenge/%61bc' ) as $uri ) {
		$assert( 'redirect', staging_bot_block_get_request_action( $options, 'Browser', false, $uri ), 'Invalid ACME follows redirect-all ' . $uri );
	}
	$assert( 'redirect', staging_bot_block_get_request_action( $options, 'Browser', false, '/?q=/.well-known/acme-challenge/token' ), 'Query cannot trigger ACME bypass' );

	if ( ! is_multisite() ) {
		add_option( 'sbb_disposable_unrelated_option', 'preserve' );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { define( 'WP_UNINSTALL_PLUGIN', 'staging-bot-block/staging-bot-block.php' ); }
		require WP_PLUGIN_DIR . '/staging-bot-block/uninstall.php';
		$assert( null, get_option( 'staging_bot_block_options', null ), 'Real uninstall deleted owned option' );
		$assert( null, get_option( 'staging_bot_block_show_activation_notice', null ), 'Real uninstall deleted notice option' );
		$assert( 'preserve', get_option( 'sbb_disposable_unrelated_option' ), 'Unrelated option preserved' );
		$assert( $legacy_id, get_post( $legacy_id )->ID, 'Uninstall preserved legacy post' );
	}
} finally {
	if ( null !== $redirect_probe ) { remove_filter( 'wp_redirect', $redirect_probe, 999 ); }
	if ( $legacy_id && ! is_wp_error( $legacy_id ) ) { wp_delete_post( $legacy_id, true ); }
	delete_option( 'sbb_disposable_unrelated_option' );
	if ( null === $previous_options ) { delete_option( 'staging_bot_block_options' ); } else { update_option( 'staging_bot_block_options', $previous_options ); }
	if ( null === $previous_notice ) { delete_option( 'staging_bot_block_show_activation_notice' ); } else { update_option( 'staging_bot_block_show_activation_notice', $previous_notice ); }
}
WP_CLI::success( $checks . ' real WordPress API assertions on WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . '. HTTP and browser QA remain separate.' );
