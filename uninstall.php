<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('staging_bot_block_options');
delete_option('staging_bot_block_show_activation_notice');
