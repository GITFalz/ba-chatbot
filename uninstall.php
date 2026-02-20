<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ba_qdrant_url');
delete_option('ba_qdrant_api_key');
delete_option('ba_gpt_api_key');

delete_option('ba_bot_qdrant_collection');
delete_option('ba_bot_name');
delete_option('ba_bot_intro_message');
delete_option('ba_bot_chat_color');
delete_option('ba_bot_open');
delete_option('ba_bot_speech');

delete_option('ba_bot_icon_ext');