<?php
/**
 * Recognized crawler user-agent matching.
 *
 * @package Staging_Bot_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get known user-agent substrings, not verified crawler identities.
 *
 * Google-Extended is absent: it has no separate HTTP user agent.
 *
 * @see https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers
 * @see https://developers.openai.com/api/docs/bots
 * @see https://privacy.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler
 * @see https://docs.perplexity.ai/docs/resources/perplexity-crawlers
 * @return string[]
 */
function staging_bot_block_get_default_bots() {
	return array(
		'googlebot',
		'mediapartners-google',
		'adsbot-google',
		'googleother',
		'google-cloudvertexbot',
		'bingbot',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'yandeximages',
		'yandexmobilebot',
		'slurp',
		'exabot',
		'sogou spider',
		'facebot',
		'twitterbot',
		'applebot',
		'petalbot',
		'gptbot',
		'oai-searchbot',
		'oai-adsbot',
		'chatgpt-user',
		'claudebot',
		'claude-searchbot',
		'claude-user',
		'perplexitybot',
		'perplexity-user',
	);
}

/**
 * Combine default and custom match strings and expose a small extension point.
 *
 * @param array|null $options Optional normalized settings.
 * @return string[]
 */
function staging_bot_block_get_blocked_bots( $options = null ) {
	$options = is_array( $options ) ? staging_bot_block_normalize_options( $options ) : staging_bot_block_get_options();
	$bots    = staging_bot_block_get_default_bots();
	$extra   = preg_split( '/\r\n|\r|\n/', $options['extra_user_agents'] );
	if ( is_array( $extra ) ) {
		$bots = array_merge( $bots, $extra );
	}

	/**
	 * Filter the final case-insensitive user-agent substrings.
	 *
	 * @param string[] $bots Built-in and administrator-configured match strings.
	 */
	$filtered = apply_filters( 'staging_bot_block_blocked_bots', $bots );
	$filtered = is_array( $filtered ) ? $filtered : $bots;
	$result   = array();
	foreach ( $filtered as $bot ) {
		if ( is_string( $bot ) && '' !== trim( $bot ) ) {
			$result[] = strtolower( trim( $bot ) );
		}
	}
	return array_values( array_unique( $result ) );
}

/**
 * Match a user agent without DNS lookups or remote services.
 *
 * @param mixed      $ua      Request user agent.
 * @param array|null $options Optional normalized settings.
 * @return bool
 */
function staging_bot_block_is_blocked_bot( $ua, $options = null ) {
	if ( ! is_string( $ua ) || '' === $ua ) {
		return false;
	}
	$ua = strtolower( $ua );
	foreach ( staging_bot_block_get_blocked_bots( $options ) as $bot ) {
		if ( false !== strpos( $ua, $bot ) ) {
			return true;
		}
	}
	return false;
}
