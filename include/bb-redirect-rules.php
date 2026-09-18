<?php
/**
 * Validated redirects and frontend protection decisions.
 *
 * @package Staging_Bot_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a path only for conservative redirect-loop comparisons.
 *
 * @param string $path URL path.
 * @return string
 */
function staging_bot_block_comparison_path( $path ) {
	do {
		$previous = $path;
		$path     = rawurldecode( $path );
	} while ( $path !== $previous );
	$segments = array();
	foreach ( explode( '/', str_replace( '\\', '/', $path ) ) as $segment ) {
		if ( '..' === $segment ) {
			array_pop( $segments );
		} elseif ( '' !== $segment && '.' !== $segment ) {
			$segments[] = strtolower( $segment );
		}
	}
	return '/' . implode( '/', $segments );
}

/**
 * Reject destinations inside this site's frontend or WordPress installation.
 *
 * Ignore HTTP/HTTPS and their default ports, host casing, a trailing dot, www,
 * dot segments, and encoded paths. A separate same-host production directory
 * remains valid when both WordPress roots are confined to the staging path.
 * DNS aliases and another site's redirects cannot be inferred without requests.
 *
 * @param array $destination Parsed URL.
 * @return bool
 */
function staging_bot_block_redirects_to_self( $destination ) {
	$target_host = preg_replace( '/^www\./', '', strtolower( rtrim( $destination['host'], '.' ) ) );
	$target_port = isset( $destination['port'] ) ? (int) $destination['port'] : 0;
	$target_port = in_array( $target_port, array( 0, 80, 443 ), true ) ? 0 : $target_port;
	$target_path = staging_bot_block_comparison_path( isset( $destination['path'] ) ? $destination['path'] : '/' );
	foreach ( array( home_url( '/' ), site_url( '/' ) ) as $base_url ) {
		$base = wp_parse_url( $base_url );
		if ( ! is_array( $base ) || empty( $base['host'] ) ) {
			continue;
		}
		$base_host = preg_replace( '/^www\./', '', strtolower( rtrim( $base['host'], '.' ) ) );
		$base_port = isset( $base['port'] ) ? (int) $base['port'] : 0;
		$base_port = in_array( $base_port, array( 0, 80, 443 ), true ) ? 0 : $base_port;
		$base_path = staging_bot_block_comparison_path( isset( $base['path'] ) ? $base['path'] : '/' );
		if ( $target_host === $base_host && $target_port === $base_port ) {
			if ( '/' === $base_path || $target_path === $base_path || 0 === strpos( $target_path, $base_path . '/' ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Validate an administrator-stored destination without contacting its host.
 *
 * Reject unsafe input before URL sanitization can silently repair it. HTTP and
 * HTTPS are the only supported protocols; credentials are never accepted.
 *
 * @param mixed $url Candidate destination.
 * @return string Clean destination, or an empty string when unsafe.
 */
function staging_bot_block_validate_redirect_url( $url ) {
	if ( ! is_string( $url ) || preg_match( '/[\x00-\x1f\x7f]/', $url ) ) {
		return '';
	}
	$url = trim( $url );
	if ( '' === $url || preg_match( '/\s|\\\\|%(?![a-f0-9]{2})/i', $url ) || preg_match( '/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $url ) ) {
		return '';
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
		return '';
	}
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}
	// WordPress's wp_validate_redirect() rejects ':' in hosts, including IPv6 literals.
	if ( false !== strpos( $parts['host'], ':' ) ) {
		return '';
	}
	$host = $parts['host'];
	if ( ! filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( rtrim( $host, '.' ), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
		return '';
	}
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || staging_bot_block_redirects_to_self( $parts ) ) {
		return '';
	}
	$url = strtolower( $parts['scheme'] ) . substr( $url, strlen( $parts['scheme'] ) );
	return esc_url_raw( $url, array( 'http', 'https' ) );
}

/**
 * Send robots and cache directives only on intercepted responses.
 */
function staging_bot_block_send_robots_header() {
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}

/**
 * Recognize only a nonempty root ACME HTTP-01 token path, never query substrings.
 *
 * @param string|null $uri Optional raw request URI for decision tests.
 * @return bool
 */
function staging_bot_block_should_bypass_request( $uri = null ) {
	if ( null === $uri ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Match the raw path; sanitization must not transform an invalid path into a bypass.
	}
	if ( ! is_string( $uri ) ) {
		return false;
	}
	$path = explode( '?', $uri, 2 );
	return 1 === preg_match( '#^/\.well-known/acme-challenge/[A-Za-z0-9_-]+$#D', $path[0] );
}

/**
 * Decide an action without sending headers or terminating execution.
 *
 * @param array  $options  Saved options, normalized defensively.
 * @param string $ua       User agent.
 * @param bool   $is_admin Whether the visitor has manage_options capability.
 * @param string $uri      Request URI.
 * @return string One of allow, block, redirect.
 */
function staging_bot_block_get_request_action( $options, $ua, $is_admin, $uri ) {
	$options = staging_bot_block_normalize_options( $options );
	if ( ! $options['enabled'] || staging_bot_block_should_bypass_request( $uri ) ) {
		return 'allow';
	}
	// Preserve administrator access even if a redirect-all destination becomes invalid.
	if ( 'redirect_all' === $options['mode'] && $is_admin ) {
		return 'allow';
	}
	$mode = staging_bot_block_get_effective_mode( $options );
	if ( 'redirect_all' === $mode ) {
		return 'redirect';
	}
	if ( ! staging_bot_block_is_blocked_bot( $ua, $options ) ) {
		return 'allow';
	}
	return 'redirect_bots' === $mode ? 'redirect' : 'block';
}

/**
 * Redirect to a saved destination using a temporary exact-host allowlist.
 *
 * Never pass public request parameters here. Read the option again to keep this
 * helper bound to the administrator's saved destination. The filter exists only
 * during this redirect and is removed even when another plugin cancels it.
 *
 * @return bool Whether WordPress sent a redirect.
 */
function staging_bot_block_redirect_to_live_site() {
	$options = staging_bot_block_get_options();
	$url     = $options['redirect_url'];
	if ( '' === $url || headers_sent() ) {
		return false;
	}
	$host        = wp_parse_url( $url, PHP_URL_HOST );
	$allow_host  = static function ( $hosts, $requested_host ) use ( $host ) {
		if ( strtolower( $host ) === strtolower( $requested_host ) ) {
			$hosts[] = $requested_host;
		}
		return $hosts;
	};
	$no_fallback = static function () {
		return '';
	};
	add_filter( 'allowed_redirect_hosts', $allow_host, 10, 2 );
	add_filter( 'wp_safe_redirect_fallback', $no_fallback );
	try {
		return wp_safe_redirect( $url, $options['redirect_type'], 'Staging Bot Block' );
	} finally {
		remove_filter( 'allowed_redirect_hosts', $allow_host, 10 );
		remove_filter( 'wp_safe_redirect_fallback', $no_fallback );
	}
}

/**
 * Protect requests that reach WordPress's frontend template redirect hook.
 */
function staging_bot_block_maybe_intercept() {
	$options = staging_bot_block_get_options();
	$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$uri     = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only in the strict ACME path matcher, never output.
	$action  = staging_bot_block_get_request_action( $options, $ua, current_user_can( 'manage_options' ), $uri );
	if ( 'allow' === $action ) {
		return;
	}
	staging_bot_block_send_robots_header();
	if ( 'redirect' === $action && staging_bot_block_redirect_to_live_site() ) {
		exit;
	}
	// If another plugin cancels a redirect, fail safely for recognized bots only.
	if ( 'block' === $action || staging_bot_block_is_blocked_bot( $ua, $options ) ) {
		status_header( 403 );
		exit;
	}
}
add_action( 'template_redirect', 'staging_bot_block_maybe_intercept', 0 );
