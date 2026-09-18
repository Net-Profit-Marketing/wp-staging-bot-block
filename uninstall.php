<?php
/**
 * Remove only plugin-owned options from sites when the plugin is deleted.
 *
 * Legacy posts and metadata are intentionally retained because older versions
 * also searched other post types; ownership cannot be established safely.
 *
 * @package Staging_Bot_Block
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$staging_bot_block_offset = 0;
	do {
		$staging_bot_block_sites = get_sites(
			array(
				'fields'  => 'ids',
				'number'  => 100,
				'offset'  => $staging_bot_block_offset,
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);
		foreach ( $staging_bot_block_sites as $staging_bot_block_site_id ) {
			switch_to_blog( $staging_bot_block_site_id );
			delete_option( 'staging_bot_block_options' );
			delete_option( 'staging_bot_block_show_activation_notice' );
			restore_current_blog();
		}
		$staging_bot_block_offset += 100;
		$staging_bot_block_count   = count( $staging_bot_block_sites );
	} while ( 100 === $staging_bot_block_count );
} else {
	delete_option( 'staging_bot_block_options' );
	delete_option( 'staging_bot_block_show_activation_notice' );
}
