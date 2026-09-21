<?php
/**
 * Focused decision tests using small WordPress doubles; run with PHP 7.4+.
 * These are not a substitute for real WordPress HTTP/browser integration QA.
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['sbb_options'] = array();
$GLOBALS['sbb_filters'] = array();
$GLOBALS['sbb_errors'] = array();
$GLOBALS['sbb_posts'] = array();
$GLOBALS['sbb_meta'] = array();
$GLOBALS['sbb_queries'] = 0;
$GLOBALS['sbb_writes'] = 0;
$GLOBALS['sbb_home'] = 'https://staging.example.test/';
$GLOBALS['sbb_site'] = 'https://staging.example.test/';
$GLOBALS['sbb_admin'] = false;
$GLOBALS['sbb_multisite'] = false;
$GLOBALS['sbb_blog'] = 1;
$GLOBALS['sbb_deleted'] = array();
$GLOBALS['sbb_checks'] = 0;

set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );
function sbb_expect( $expected, $actual, $label ) {
	++$GLOBALS['sbb_checks'];
	if ( $expected !== $actual ) {
		throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}
function add_action() {}
function add_filter( $name, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['sbb_filters'][ $name ][] = $callback;
}
function remove_filter( $name, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['sbb_filters'][ $name ] as $key => $item ) {
		if ( $item === $callback ) {
			unset( $GLOBALS['sbb_filters'][ $name ][ $key ] );
		}
	}
}
function apply_filters( $name, $value, ...$args ) {
	foreach ( $GLOBALS['sbb_filters'][ $name ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}
function get_option( $key, $default = false ) { return $GLOBALS['sbb_options'][ $key ] ?? $default; }
function add_option( $key, $value ) {
	if ( array_key_exists( $key, $GLOBALS['sbb_options'] ) ) { return false; }
	$GLOBALS['sbb_options'][ $key ] = $value;
	++$GLOBALS['sbb_writes'];
	return true;
}
function update_option( $key, $value ) {
	$GLOBALS['sbb_options'][ $key ] = $value;
	++$GLOBALS['sbb_writes'];
}
function delete_option( $key ) {
	$GLOBALS['sbb_deleted'][] = array( $GLOBALS['sbb_blog'], $key );
	unset( $GLOBALS['sbb_options'][ $key ] );
}
function register_setting() {}
function sanitize_textarea_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function esc_url_raw( $url, $protocols = null ) { return $url; }
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function wp_unslash( $text ) { return stripslashes( $text ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return $GLOBALS['sbb_home']; }
function site_url( $path = '' ) { return $GLOBALS['sbb_site']; }
function current_user_can( $capability ) { return $GLOBALS['sbb_admin']; }
function is_network_admin() { return false; }
function is_multisite() { return $GLOBALS['sbb_multisite']; }
function wp_die() { throw new RuntimeException( 'wp_die' ); }
function add_settings_error( $setting, $code, $message, $type ) { $GLOBALS['sbb_errors'][] = $code; }
function get_posts( $args ) { ++$GLOBALS['sbb_queries']; return $GLOBALS['sbb_posts']; }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['sbb_meta'][ $key ] ?? ''; }
function nocache_headers() { $GLOBALS['sbb_nocache'] = true; }
function status_header( $status ) { $GLOBALS['sbb_status'] = $status; }
function get_sites( $args ) { return array_slice( range( 1, 101 ), $args['offset'], $args['number'] ); }
function switch_to_blog( $id ) { $GLOBALS['sbb_blog'] = $id; }
function restore_current_blog() { $GLOBALS['sbb_blog'] = 1; }
function wp_safe_redirect( $url, $status, $source ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$hosts = apply_filters( 'allowed_redirect_hosts', array( wp_parse_url( home_url(), PHP_URL_HOST ) ), $host );
	$fallback = apply_filters( 'wp_safe_redirect_fallback', '/wp-admin/' );
	$GLOBALS['sbb_redirect'] = array( in_array( $host, $hosts, true ) ? $url : $fallback, $status, $source );
	$GLOBALS['sbb_other_hosts'] = apply_filters( 'allowed_redirect_hosts', array(), 'untrusted.example.test' );
	if ( ! empty( $GLOBALS['sbb_redirect_throws'] ) ) { throw new RuntimeException( 'redirect cancelled' ); }
	return empty( $GLOBALS['sbb_redirect_cancel'] ) && '' !== $GLOBALS['sbb_redirect'][0];
}

require __DIR__ . '/../include/bb-action.php';
require __DIR__ . '/../include/bb-detects-bots.php';
require __DIR__ . '/../include/bb-redirect-rules.php';

$defaults = staging_bot_block_get_default_options();
sbb_expect( 0, $defaults['enabled'], 'Disabled by default' );
sbb_expect( 302, $defaults['redirect_type'], '302 default' );
sbb_expect( 1, $defaults['warning_banner'], 'Warning visible by default' );
sbb_expect( $defaults, staging_bot_block_get_options(), 'Missing option schema' );
sbb_expect( 0, $GLOBALS['sbb_queries'], 'No legacy frontend queries' );
sbb_expect( 0, $GLOBALS['sbb_writes'], 'No frontend writes' );
foreach ( array( false, null, 'corrupt', 42, array( 'unknown' => 'discard' ) ) as $input ) {
	sbb_expect( $defaults, staging_bot_block_normalize_options( $input ), 'Invalid or unknown stored options' );
}
foreach ( array( 'BLOCK', 'arbitrary', array(), false ) as $mode ) {
	sbb_expect( 'block', staging_bot_block_normalize_options( array( 'mode' => $mode ) )['mode'], 'Mode allowlist' );
}
foreach ( array( 301, '301', 302, '302', 307, '301abc', array(), 301.5, null ) as $status ) {
	sbb_expect( in_array( $status, array( 301, '301' ), true ) ? 301 : 302, staging_bot_block_normalize_options( array( 'redirect_type' => $status ) )['redirect_type'], 'Status allowlist' );
}
foreach ( array( 1, '1', true, false, 0, '0', 'false', array(), 2 ) as $value ) {
	$normalized = staging_bot_block_normalize_options( array( 'enabled' => $value, 'warning_banner' => $value ) );
	sbb_expect( in_array( $value, array( 1, '1', true ), true ) ? 1 : 0, $normalized['enabled'], 'Enabled checkbox' );
	sbb_expect( $normalized['enabled'], $normalized['warning_banner'], 'Banner checkbox' );
}
$saved = staging_bot_block_sanitize_options( array() );
sbb_expect( 0, $saved['warning_banner'], 'Unchecked banner on form save' );
sbb_expect( "Agent One\nAgent Two", staging_bot_block_normalize_options( array( 'extra_user_agents' => " <b>Agent One</b>\nAgent Two " ) )['extra_user_agents'], 'Multiline user-agent sanitization' );
sbb_expect( '', staging_bot_block_normalize_options( array( 'extra_user_agents' => array() ) )['extra_user_agents'], 'Malformed custom UA input' );

$valid_urls = array(
	'https://live.example.test/', 'http://live.example.test/path?q=one%20two&x=2',
	'https://live.example.test:8443/', 'https://192.0.2.1/',
	'https://localhost/', 'https://xn--bcher-kva.example/path#fragment',
);
foreach ( $valid_urls as $url ) {
	sbb_expect( $url, staging_bot_block_validate_redirect_url( $url ), 'Valid external URL ' . $url );
}
$invalid_urls = array(
	'', '//live.example.test/', '/path', 'ftp://live.example.test/', 'javascript:alert(1)',
	'https://', 'https:///live.example.test/', 'https://user:pass@live.example.test/',
	'https://user@live.example.test/', 'https://bad_host.example/', 'https://-bad.example/',
	"https://live.example.test/\r\nX-Evil: yes", 'https://live.example.test/%0d%0aheader',
	'https://live.example.test/space here', 'https://live.example.test/%zz',
	'https://live.example.test\\@evil.example/', 'https://live.example.test:99999/',
	'https://staging.example.test/', 'http://STAGING.example.test.:80/any?different=1',
	'https://www.staging.example.test:443/path', 'https://[2001:db8::1]/', array(), 123,
);
foreach ( $invalid_urls as $url ) {
	sbb_expect( '', staging_bot_block_validate_redirect_url( $url ), 'Invalid/self URL ' . json_encode( $url ) );
}
sbb_expect( 'https://live.example.test/', staging_bot_block_validate_redirect_url( 'HTTPS://live.example.test/' ), 'Scheme normalized for WordPress safe redirects' );
$GLOBALS['sbb_home'] = 'https://example.test/staging/';
$GLOBALS['sbb_site'] = 'https://example.test/staging/wp/';
foreach ( array( '/staging', '/staging/page', '/STAGING/', '/%73taging/', '/other/../staging/', '/staging%252fpage', '/staging//page' ) as $path ) {
	sbb_expect( '', staging_bot_block_validate_redirect_url( 'http://www.example.test' . $path ), 'Equivalent subdirectory loop ' . $path );
}
foreach ( array( '/', '/live/', '/staging-other/' ) as $path ) {
	sbb_expect( 'https://example.test' . $path, staging_bot_block_validate_redirect_url( 'https://example.test' . $path ), 'Separate same-host destination' );
}
$GLOBALS['sbb_site'] = 'https://admin.example.test/wp/';
sbb_expect( '', staging_bot_block_validate_redirect_url( 'http://admin.example.test/wp/page' ), 'Different site_url host also guarded' );
$GLOBALS['sbb_home'] = $GLOBALS['sbb_site'] = 'https://staging.example.test/';
$saved = staging_bot_block_sanitize_options( array( 'enabled' => 1, 'mode' => 'redirect_all', 'redirect_url' => 'https://staging.example.test/' ) );
sbb_expect( 'redirect_all', $saved['mode'], 'Invalid URL retains requested mode for correction' );
sbb_expect( 'block', staging_bot_block_get_effective_mode( $saved ), 'Invalid redirect falls back to blocking' );
sbb_expect( 'staging_bot_block_redirect_url_invalid', end( $GLOBALS['sbb_errors'] ), 'Useful Settings API error' );

foreach ( array( '', 'Mozilla/5.0 Safari/537.36', 'UnknownCrawler/1.0' ) as $ua ) {
	sbb_expect( false, staging_bot_block_is_blocked_bot( $ua ), 'Unknown/browser/empty user agent' );
}
foreach ( staging_bot_block_get_default_bots() as $bot ) {
	sbb_expect( true, staging_bot_block_is_blocked_bot( 'Mozilla/5.0 ' . strtoupper( $bot ) . '/1.0' ), 'Known bot ' . $bot );
}
$custom = array_merge( $defaults, array( 'extra_user_agents' => " Custom Agent\n\nSecond Agent" ) );
sbb_expect( true, staging_bot_block_is_blocked_bot( 'custom AGENT v1', $custom ), 'Custom case-insensitive substring' );
sbb_expect( false, staging_bot_block_is_blocked_bot( array(), $custom ), 'Malformed user agent' );
$filter = function ( $bots ) { $bots[] = 'Filtered Agent'; $bots[] = ''; $bots[] = array(); return $bots; };
add_filter( 'staging_bot_block_blocked_bots', $filter );
sbb_expect( true, staging_bot_block_is_blocked_bot( 'Filtered Agent' ), 'Final-list extension filter' );
remove_filter( 'staging_bot_block_blocked_bots', $filter );
sbb_expect( false, in_array( 'google-extended', staging_bot_block_get_default_bots(), true ), 'No robots-only Google-Extended token' );

foreach ( array( '/.well-known/acme-challenge/abc123', '/.well-known/acme-challenge/a_b-c', '/.well-known/acme-challenge/abc123?anything=1', '/.well-known/acme-challenge/x' ) as $uri ) {
	sbb_expect( true, staging_bot_block_should_bypass_request( $uri ), 'ACME path ' . $uri );
}
foreach ( array( '/.well-known/acme-challenge/', '/.well-known/acme-challenge', '/foo/.well-known/acme-challenge/abc', '/.well-known/acme-challenge/?foo=bar', '/?path=/.well-known/acme-challenge/token', '/nested/.well-known/acme-challenge/token', '/.well-known/acme-challenge-other/token', '/.well-known/acme-challenge/../page', '//evil.test/.well-known/acme-challenge/token', '/.well-known/acme-challenge/token/extra', '/.well-known/acme-challenge/%61bc', '/.well-known/acme-challenge/token.txt', "/.well-known/acme-challenge/token\n", '', null, array() ) as $uri ) {
	sbb_expect( false, staging_bot_block_should_bypass_request( $uri ), 'Non-ACME path ' . json_encode( $uri ) );
}
foreach ( array( 'block', 'redirect_bots', 'redirect_all' ) as $mode ) {
	$options = array_merge( $defaults, array( 'enabled' => 1, 'mode' => $mode, 'redirect_url' => 'https://live.example.test/' ) );
	foreach ( array( false, true ) as $admin ) {
		foreach ( array( '', 'Browser/1', 'Googlebot/1', 'UnknownCrawler' ) as $ua ) {
			$bot = 'Googlebot/1' === $ua;
			$expected = 'allow';
			if ( 'redirect_all' === $mode && ! $admin ) { $expected = 'redirect'; }
			if ( 'redirect_all' !== $mode && $bot ) { $expected = 'block' === $mode ? 'block' : 'redirect'; }
			sbb_expect( $expected, staging_bot_block_get_request_action( $options, $ua, $admin, '/path?q=1' ), $mode . ' request matrix' );
			sbb_expect( 'allow', staging_bot_block_get_request_action( $options, $ua, $admin, '/.well-known/acme-challenge/test' ), 'ACME overrides every mode' );
			sbb_expect( $expected, staging_bot_block_get_request_action( $options, $ua, $admin, '/.well-known/acme-challenge/' ), 'Empty ACME token follows selected mode' );
			sbb_expect( $expected, staging_bot_block_get_request_action( $options, $ua, $admin, '/.well-known/acme-challenge/?foo=bar' ), 'Query cannot supply missing ACME token' );
			$options['enabled'] = 0;
			sbb_expect( 'allow', staging_bot_block_get_request_action( $options, $ua, $admin, '/' ), 'Disabled overrides every mode' );
			$options['enabled'] = 1;
		}
	}
}
$options['redirect_url'] = '';
sbb_expect( 'allow', staging_bot_block_get_request_action( $options, 'Browser', false, '/' ), 'Bad redirect permits people' );
sbb_expect( 'block', staging_bot_block_get_request_action( $options, 'Googlebot', false, '/' ), 'Bad redirect blocks recognized bots' );
sbb_expect( 'allow', staging_bot_block_get_request_action( $options, 'Googlebot', true, '/' ), 'Redirect-all administrator survives invalid config' );

$GLOBALS['sbb_options']['staging_bot_block_options'] = array_merge( $defaults, array( 'enabled' => 1, 'mode' => 'redirect_bots', 'redirect_url' => 'https://live.example.test/a?x=1', 'redirect_type' => 301 ) );
sbb_expect( true, staging_bot_block_redirect_to_live_site(), 'Stored external redirect accepted' );
sbb_expect( array( 'https://live.example.test/a?x=1', 301, 'Staging Bot Block' ), $GLOBALS['sbb_redirect'], 'Exact stored redirect destination/status' );
sbb_expect( array(), $GLOBALS['sbb_other_hosts'], 'Unrelated host never allowlisted' );
sbb_expect( array(), apply_filters( 'allowed_redirect_hosts', array(), 'live.example.test' ), 'Host filter removed after success' );
sbb_expect( '/original/', apply_filters( 'wp_safe_redirect_fallback', '/original/' ), 'Fallback filter removed after success' );
$GLOBALS['sbb_redirect_cancel'] = true;
sbb_expect( false, staging_bot_block_redirect_to_live_site(), 'Cancelled redirect reported as failed' );
sbb_expect( array(), apply_filters( 'allowed_redirect_hosts', array(), 'live.example.test' ), 'Host filter removed on cancellation' );
$GLOBALS['sbb_redirect_throws'] = true;
try { staging_bot_block_redirect_to_live_site(); } catch ( RuntimeException $exception ) {}
sbb_expect( array(), apply_filters( 'allowed_redirect_hosts', array(), 'live.example.test' ), 'Host filter removed on exception' );
$GLOBALS['sbb_redirect_throws'] = false;
$GLOBALS['sbb_redirect_cancel'] = false;

$original = $GLOBALS['sbb_options']['staging_bot_block_options'];
staging_bot_block_activate();
sbb_expect( $original, get_option( 'staging_bot_block_options' ), 'Activation preserves existing option byte values' );
sbb_expect( 0, $GLOBALS['sbb_queries'], 'Existing activation does not query legacy data' );
$GLOBALS['sbb_options'] = array();
$GLOBALS['sbb_posts'] = array( 17 );
$GLOBALS['sbb_meta'] = array( 'bb_redirect_enabled' => '1', 'bb_redirect_choice' => 'Redirect Bots', 'bb_redirect_url' => 'https://live.example.test/', 'bb_redirect_type' => '301', 'bb_redirect_enable_warning_banner' => '0' );
staging_bot_block_activate();
sbb_expect( 1, get_option( 'staging_bot_block_options' )['enabled'], 'Legacy enabled migration' );
sbb_expect( 'redirect_bots', get_option( 'staging_bot_block_options' )['mode'], 'Legacy mode migration' );
sbb_expect( 301, get_option( 'staging_bot_block_options' )['redirect_type'], 'Legacy redirect migration' );
sbb_expect( 0, get_option( 'staging_bot_block_options' )['warning_banner'], 'Legacy banner migration' );
$queries = $GLOBALS['sbb_queries'];
staging_bot_block_migrate_legacy_settings();
sbb_expect( $queries, $GLOBALS['sbb_queries'], 'Migration only once' );
$GLOBALS['sbb_options'] = array();
$GLOBALS['sbb_posts'] = array();
staging_bot_block_activate();
sbb_expect( $defaults, get_option( 'staging_bot_block_options' ), 'Fresh activation disabled defaults' );
$GLOBALS['sbb_multisite'] = true;
$writes = $GLOBALS['sbb_writes'];
try { staging_bot_block_activate( true ); throw new LogicException( 'Expected network activation rejection' ); } catch ( RuntimeException $exception ) { sbb_expect( 'wp_die', $exception->getMessage(), 'Network activation rejected' ); }
sbb_expect( $writes, $GLOBALS['sbb_writes'], 'Network activation makes no writes' );

$GLOBALS['sbb_multisite'] = false;
$GLOBALS['sbb_options']['unrelated_option'] = 'keep';
define( 'WP_UNINSTALL_PLUGIN', 'staging-bot-block/staging-bot-block.php' );
require __DIR__ . '/../uninstall.php';
sbb_expect( 'keep', get_option( 'unrelated_option' ), 'Uninstall preserves unrelated options' );
sbb_expect( false, get_option( 'staging_bot_block_options' ), 'Uninstall removes options' );
sbb_expect( false, get_option( 'staging_bot_block_show_activation_notice' ), 'Uninstall removes activation notice' );
$GLOBALS['sbb_multisite'] = true;
$GLOBALS['sbb_deleted'] = array();
require __DIR__ . '/../uninstall.php';
sbb_expect( 202, count( $GLOBALS['sbb_deleted'] ), 'Paged multisite uninstall covers 101 sites' );
sbb_expect( array( 101, 'staging_bot_block_show_activation_notice' ), end( $GLOBALS['sbb_deleted'] ), 'Final site cleaned' );
sbb_expect( 1, $GLOBALS['sbb_blog'], 'Uninstall restores original blog' );
echo 'PASS: ' . $GLOBALS['sbb_checks'] . ' focused assertions on PHP ' . PHP_VERSION . PHP_EOL;
