<?php 

function ai_chatbot_create_tables()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_chat_messages';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message LONGTEXT NOT NULL,
        response LONGTEXT,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql);
}

function ai_chatbot_store_question($question)
{
    global $wpdb;

    if (empty($question)) {
        return false;
    }

    $table = $wpdb->prefix . 'ai_chat_messages';

    $inserted = $wpdb->insert(
        $table,
        [
            'message'         => $question,
            'created_at'      => current_time('mysql'),
        ],
        [
            '%s',
            '%s',
        ]
    );
    

    if ($inserted === false) {
        error_log('[AI Chatbot] Failed to store question: ' . $wpdb->last_error);
        return false;
    }

    return $wpdb->insert_id;
}

function ai_chatbot_set_response($question_id, $response)
{
    global $wpdb;

    if (empty($question_id) || $response === null) {
        return false;
    }

    $table = $wpdb->prefix . 'ai_chat_messages';

    $updated = $wpdb->update(
        $table,
        [
            'response' => $response,
        ],
        [
            'id' => $question_id,
        ],
        [
            '%s',
        ],
        [
            '%d',
        ]
    );

    if ($updated === false) {
        error_log('[AI Chatbot] Failed to store response: ' . $wpdb->last_error);
        return false;
    }

    return true;
}

function ai_chatbot_get_conversations_by_day($date)
{
    global $wpdb;

    if (empty($date)) {
        return [];
    }

    $start = $date . ' 00:00:00';
    $end   = date('Y-m-d H:i:s', strtotime($date . ' +1 day'));

    $table = $wpdb->prefix . 'ai_chat_messages';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, message, response, created_at
             FROM $table
             WHERE created_at >= %s
             AND created_at < %s
             ORDER BY created_at ASC",
            $start,
            $end
        ),
        ARRAY_A
    );
}

function ai_chatbot_download_conversations_txt($date)
{
    if (empty($date)) {
        wp_die('No date provided.');
    }

    $conversations = ai_chatbot_get_conversations_by_day($date);

    if (empty($conversations)) {
        wp_die('No conversations found for this date.');
    }

    // Use pipe as separator, change to "\t" for tab-separated
    $separator = " | ";

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="chatbot-conversations-' . $date . '.txt"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    foreach ($conversations as $row) {
        // Replace newlines in messages/responses so it stays one line per conversation
        $message = str_replace(["\r", "\n"], ' ', $row['message']);
        $response = str_replace(["\r", "\n"], ' ', $row['response']);

        fwrite($output, "DATE: " . $row['created_at'] . "\nUSER: " . $message . "\nBOT: " . $response . "\n--------------------\n");
    }

    fclose($output);
    exit;
}

function ai_chatbot_download_conversations_handler()
{
    if (!isset($_GET['download_chatbot_day']))
        error_log("fail 1");

    if (!current_user_can('manage_options'))
        error_log("fail 2");

    if (!wp_verify_nonce($_GET['ai_chatbot_nonce'], 'ai_chatbot_handler'))
        error_log("fail 3");

    if (isset($_GET['download_chatbot_day']) && current_user_can('manage_options') && wp_verify_nonce($_GET['ai_chatbot_nonce'], 'ai_chatbot_handler')) {
        $date = sanitize_text_field($_GET['download_chatbot_day']);
        ai_chatbot_download_conversations_txt($date);
        wp_send_json_success();
    }   
    wp_send_json_error();
}
add_action('wp_ajax_ai_chatbot_download_conversations', 'ai_chatbot_download_conversations_handler');