<?php
/**
 * Plugin Name: BA Chatbot
 * Description: Chatbot voor je website, BuroAmstelveen.
 * Version:     1.5.0
 * Author:      Bjornar Schinkel
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Plugin constants (keep here)
define('AI_CHATBOT_PATH', plugin_dir_path(__FILE__));
define('AI_CHATBOT_URL', plugin_dir_url(__FILE__));
define('AI_CHATBOT_VERSION', '1.5.0');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Include necessary files
require_once AI_CHATBOT_PATH . 'includes/functions.php';
require_once AI_CHATBOT_PATH . 'includes/parser.php';
require_once AI_CHATBOT_PATH . 'includes/hooks.php';
require_once AI_CHATBOT_PATH . 'includes/shortcodes.php';
require_once AI_CHATBOT_PATH . 'templates/admin.php';
require_once AI_CHATBOT_PATH . 'ai/commands.php';

// i am getting an error where it says it can't find the parser.php file, what should ido?
// answer

/**
 * Plugin activation hook
 */
function ai_chatbot_activate() {
    
}
register_activation_hook(__FILE__, 'ai_chatbot_activate');

/**
 * Plugin deactivation hook
 */
function ai_chatbot_deactivate() {
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
}
register_deactivation_hook(__FILE__, 'ai_chatbot_deactivate');

/**
 * Init plugin
 */
function ai_chatbot_init() {
    // Load files later
}
add_action('init', 'ai_chatbot_init');

function ai_chatbot_update_check()
{
    $installed = get_option('ai_chatbot_version');

    if ($installed !== AI_CHATBOT_VERSION)
    {
        ai_chatbot_run_updates($installed);
        update_option('ai_chatbot_version', AI_CHATBOT_VERSION);
    }
}

add_action('plugins_loaded', 'ai_chatbot_update_check');