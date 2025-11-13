<?php
/*
	Plugin Name: Staging Bot Block
	Description: A plugin to use when developing a site or hosting a staging environment. It will help fix issues with staging sites getting indexed by Google, redirecting staging sites once they do get indexed, and forgetting about setting robots.txt to nofollow and accidentally deindexing the production site.
	Tags: bot-block,staging
	Author: Jared Pomranky
	Author URI: https://www.netprofitmarketing.com
	Requires at least: 4.6
	Tested up to: 6.7.1
	Stable tag: 1.0
	Version: 1.0
	Requires PHP: 7.2
	Text Domain: staging-bot-block
	License: GPL v2 or later

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 
	2 of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	with this program. If not, visit: https://www.gnu.org/licenses/
	
*/

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



require plugin_dir_path( __FILE__ ) . 'setting-page/bb-staging.php';

// Enqueue Assets

function enqueue_assets() {

	wp_enqueue_style( 'bb-main-style', plugin_dir_url( __FILE__ ) . 'assets/css/bb-main.css' );

	wp_enqueue_script( 'bb-main-js', plugin_dir_url( __FILE__ ) . 'assets/js/bb-main.js', array(), '', true );

}

add_action( 'admin_enqueue_scripts', 'enqueue_assets' );



function callback( $buffer ) {
	return $buffer;
}

function buffer_start() {
	ob_start( 'callback' );
}

function buffer_end() {
	ob_end_flush();
}

add_action( 'init', 'buffer_start' );

add_action( 'wp_footer', 'buffer_end' );



require plugin_dir_path( __FILE__ ) . 'include/bb-action.php';

require plugin_dir_path( __FILE__ ) . 'include/bb-warning-banner.php';

require plugin_dir_path( __FILE__ ) . 'include/bb-detects-bots.php';

require plugin_dir_path( __FILE__ ) . 'include/bb-redirect-rules.php';

register_activation_hook( __FILE__, 'staging_bot_block_activate' );

