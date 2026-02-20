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

$upload_dir_info = wp_upload_dir();
$upload_dir = $upload_dir_info['basedir'] . '/ba-chatbot/';

$old_files = glob($upload_dir . 'profile-picture.*');
foreach ($old_files as $file) {
    @unlink($file);
}

delete_option('ba_bot_icon_ext');
delete_option('ba_bot_icon_url');