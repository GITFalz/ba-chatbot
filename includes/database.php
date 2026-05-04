<?php 

function ai_chatbot_create_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $table_name_messages = $wpdb->prefix . 'ai_chat_messages';
    $messages_sql = "CREATE TABLE $table_name_messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message LONGTEXT NOT NULL,
        response LONGTEXT,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset_collate;";

    $table_name_pages = $wpdb->prefix . 'ai_chat_pages';
    $posts_table = $wpdb->posts;
    $pages_sql = "CREATE TABLE $table_name_pages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        page_id BIGINT UNSIGNED,
        PRIMARY KEY (id),
        KEY page_id (page_id)
    ) $charset_collate;";

    dbDelta($messages_sql);
    dbDelta($pages_sql);
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
        return false;
    }

    return $wpdb->insert_id;
}

function ai_chatbot_store_page($page_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'ai_chat_pages';
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE page_id = %d",
        $page_id
    ));

    if ($exists)
        return [
            "success" => false,
            "reason" => "ID already exists in the database"
        ];

    $inserted = $wpdb->insert(
        $table,
        [
            'page_id' => $page_id
        ],
        [
            '%d'
        ]
    );

    if ($inserted === false) {
        return [
            "success" => false,
            "reason" => $wpdb->last_error,
            "query" => $wpdb->last_query
        ];
    }

    return [
        "success" => true,
        "id" => $wpdb->insert_id
    ];
}

function ai_chatbot_uploaded_pages()
{
    global $wpdb;

    $table = $wpdb->prefix . 'ai_chat_pages';

    return $wpdb->get_results(
        "SELECT page_id
         FROM $table",
        ARRAY_A
    );
}

function ai_chatbot_get_pages()
{
    global $wpdb;

    $pages = $wpdb->get_results("
        SELECT ID, post_title, post_name, post_type
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
        ORDER BY post_title ASC
    ", ARRAY_A);

    $pages_array = [];

    foreach ($pages as $page) {
        $pages_array[$page['ID']] = [
            'title'  => $page['post_title'],
            'url'    => get_permalink($page['ID']),
            'type'   => $page['post_type'],
            'status' => 0
        ];
    }

    return $pages_array;
}

function ai_chatbot_get_pages_lookup() {
    global $wpdb;

    $pages = $wpdb->get_results("
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
        AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
    ", ARRAY_A);

    $page_set = [];

    foreach ($pages as $page) {
        $page_set[(int)$page['ID']] = true;
    }

    return $page_set;
}

function ai_chatbot_get_ai_chat_pages_lookup() {
    global $wpdb;

    $table = $wpdb->prefix . "ai_chat_pages";

    $rows = $wpdb->get_results("
        SELECT page_id
        FROM {$table}
    ", ARRAY_A);

    $page_set = [];

    foreach ($rows as $row) {
        $page_set[(int)$row['page_id']] = true;
    }

    return $page_set;
}

function ai_chatbot_get_page($page_id) {
    global $wpdb;

    $page = $wpdb->get_row( $wpdb->prepare(
        "
        SELECT ID, post_title, post_name
        FROM {$wpdb->posts}
        WHERE ID = %d
        AND post_status = 'publish'
        AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
        ",
        $page_id
    ), ARRAY_A );

    if (!$page) {
        return false;
    }

    return [
        'title'  => $page['post_title'],
        'url'    => get_permalink( $page['ID'] ),
        'status' => 0
    ];
}

function ai_chatbot_remove_page($id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'ai_chat_pages';
    $deleted = $wpdb->delete(
        $table,
        [
            'page_id' => $id
        ],
        [
            '%d'
        ]
    );

    return $deleted;
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
    if (isset($_GET['download_chatbot_day']) && current_user_can('manage_options') && wp_verify_nonce($_GET['ai_chatbot_nonce'], 'ai_chatbot_handler')) {
        $date = sanitize_text_field($_GET['download_chatbot_day']);
        ai_chatbot_download_conversations_txt($date);
        wp_send_json_success();
    }   
    wp_send_json_error();
}
add_action('wp_ajax_ai_chatbot_download_conversations', 'ai_chatbot_download_conversations_handler');

function ai_chatbot_delete_tables()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_chat_messages';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "DELETE TABLE $table_name;";

    dbDelta($sql);
}