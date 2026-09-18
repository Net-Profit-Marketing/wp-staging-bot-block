<?php
/**
 * Plugin Name: Staging Bot Block
 * Description: Block or redirect recognized crawlers on staging sites, with clear protection status for administrators.
 * Version: 1.1.0
 * Author: Net Profit Marketing
 * Author URI: https://www.netprofitmarketing.com
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Text Domain: staging-bot-block
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package StagingBotBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STAGING_BOT_BLOCK_VERSION', '1.1.0' );

require_once __DIR__ . '/include/bb-action.php';
require_once __DIR__ . '/include/bb-detects-bots.php';
require_once __DIR__ . '/include/bb-redirect-rules.php';
require_once __DIR__ . '/include/bb-warning-banner.php';
require_once __DIR__ . '/setting-page/bb-staging.php';

/**
 * Load the settings enhancement only on this plugin's screen.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function staging_bot_block_enqueue_assets( $hook_suffix ) {
	if ( 'toplevel_page_block-bot-redirect-setting-page' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'bb-main-style', plugin_dir_url( __FILE__ ) . 'assets/css/bb-main.css', array(), STAGING_BOT_BLOCK_VERSION );
	wp_enqueue_script( 'bb-main-js', plugin_dir_url( __FILE__ ) . 'assets/js/bb-main.js', array(), STAGING_BOT_BLOCK_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'staging_bot_block_enqueue_assets' );

register_activation_hook( __FILE__, 'staging_bot_block_activate' );

/**
 * Link directly from the Plugins screen to settings.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function staging_bot_block_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) && ! is_network_admin() ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=block-bot-redirect-setting-page' ) ),
				esc_html__( 'Settings', 'staging-bot-block' )
			)
		);
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'staging_bot_block_plugin_action_links' );
