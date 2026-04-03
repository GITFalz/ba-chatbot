<?php

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_ai-chatbot-admin')
    {
        wp_enqueue_style( 
            'ai-chatbot-pbg-style-css', 
            AI_CHATBOT_URL . '/assets/css/pbg-style.css',
            array(),
            '1.1'
        );

        wp_enqueue_style( 
            'ai-chatbot-admin-css', 
            AI_CHATBOT_URL . '/assets/css/admin-panel.css',
            array(),
            '1.1'
        );

        wp_enqueue_script(
            'ai-chatbot-admin-js',
            AI_CHATBOT_URL . '/assets/js/admin-panel.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('ai-chatbot-admin-js', 'AIChatbot', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_handler')
        ]);
    }

    if ($hook === 'ai-chatbot-admin_page_ai-chatbot-analytics')
    {
        wp_enqueue_style( 
            'ai-chatbot-pbg-style-css', 
            AI_CHATBOT_URL . '/assets/css/pbg-style.css',
            array(),
            '1.1'
        );

        wp_enqueue_style( 
            'ai-chatbot-admin-css', 
            AI_CHATBOT_URL . '/assets/css/admin-panel.css',
            array(),
            '1.1'
        );

        wp_register_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'ai-analytics-js',
            AI_CHATBOT_URL . '/assets/js/analytics.js',
            ['jquery', 'chart-js'],
            null,
            true
        );

        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_messages';

        // Fetch stats
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        // Weekly message counts (last 7 days)
        $weekly_counts = $wpdb->get_results("
            SELECT DATE(created_at) as day, COUNT(*) as count
            FROM $table
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ", ARRAY_A);

        // Prepare JS data for chart
        $chart_labels = [];
        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = $day;
            $found = false;
            foreach ($weekly_counts as $row) {
                if ($row['day'] === $day) {
                    $chart_data[] = (int)$row['count'];
                    $found = true;
                    break;
                }
            }
            if (!$found) $chart_data[] = 0;
        }

        wp_localize_script('ai-analytics-js', 'AIChatbot', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_handler'),
            'total_messages' => $total_messages,
            'chart_labels' => $chart_labels,
            'chart_data' => $chart_data
        ]);
    }
});


// Enqueue widget JS/CSS on frontend if shortcode present
add_action('wp_enqueue_scripts', function() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'Chatbot')) {
        wp_enqueue_style('ai-chatbot-widget-css', AI_CHATBOT_URL . 'assets/css/ai-chatbot-widget.css');
        wp_enqueue_script('ai-chatbot-widget-js', AI_CHATBOT_URL . 'assets/js/ai-chatbot-widget.js', [], time(), true);
        wp_localize_script('ai-chatbot-widget-js', 'ai_chatbot_widget', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'speech' => (get_option("ba_bot_speech") == "friendly") ? "friendly" : "respectful",
            'botName' => get_option('ba_bot_name', "Assistent")
        ]);
    }
});

function get_all_pages_for_chatbot($max_pages = 40, $chars_per_page = 1200) {
    
    $context_chunks = [];

    $args = array(
        'post_type'      => ['page', 'post'],
        'post_status'    => 'publish',
        'posts_per_page' => $max_pages,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $title   = get_the_title();
            $url     = get_permalink();
            $content = wp_strip_all_tags(get_the_content());
            
            // Clean and limit content per page
            $content = preg_replace('/\s+/', ' ', $content);   // normalize whitespace
            $content = substr(trim($content), 0, $chars_per_page);

            $context_chunks[] = "Page Title: {$title}\nURL: {$url}\nContent:\n{$content}";
        }
        wp_reset_postdata();
    }

    return $context_chunks;
}


function ai_chatbot_search_handler() {
    $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
    if (!$question) {
        wp_send_json_error('No question provided.');
    }

    $question_id = ai_chatbot_store_question($question);

    // Get embedding for question
    $embeddingResult = ai_chatbot_send_to_openai_embeddings($question);
    if (!$embeddingResult['success']) {
        ai_chatbot_set_response($question_id, 'Geen antwoord gevonden.');
        wp_send_json_error(["message" => $embeddingResult['message']]);
    }

    // Query Qdrant
    $results = ai_chatbot_query_qdrant($embeddingResult['embedding'], 5);
    if (!$results["success"]) {
        ai_chatbot_set_response($question_id, 'Geen antwoord gevonden.');
        wp_send_json_error(["message" => $results['message']]);
    }
    
    // Extract context chunks
    $context_chunks = get_all_pages_for_chatbot();
    foreach ($results['data']['result'] as $point) {
        if (isset($point['payload']['text'])) {
            $context_chunks[] = $point['payload']['text'];
        }
    }

    if (empty($context_chunks))
        $context_chunks = ["Context: (none)"];
    
    // Ask LLM
    $answer = ai_chatbot_ask_llm($question, context_chunks: $context_chunks);
    if (!$answer) {
        ai_chatbot_set_response($question_id, 'Geen antwoord gevonden.');
        wp_send_json_error('LLM failed to generate an answer.');
    }

    ai_chatbot_set_response($question_id, $answer);
    wp_send_json_success(['answer' => $answer]);
}
add_action('wp_ajax_nopriv_ai_chatbot_search', 'ai_chatbot_search_handler');
add_action('wp_ajax_ai_chatbot_search', 'ai_chatbot_search_handler');

function ai_chatbot_file_deletion_handler() 
{
    if (!isset($_POST['ai_chatbot_nonce']) || !wp_verify_nonce($_POST['ai_chatbot_nonce'], 'ai_chatbot_handler')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        wp_die();
    }

    if (!isset($_POST['ai_chatbot_delete_file'])) {
        wp_send_json_error(['message' => 'No file uploaded.']);
        wp_die();
    }

    $file_name = sanitize_file_name($_POST['ai_chatbot_delete_file']);
    $result = ai_chatbot_file_deletion($file_name);

    if (!$result['success']) {
        wp_send_json_error(['message' => 'Failed to delete file ' . $file_name . ", for reason: " . $result['message']]);
        wp_die();
    }

    wp_send_json_success(['message' => $file_name . ' deleted successfully.']);
    wp_die();
}
add_action('wp_ajax_ai_chatbot_file_deletion', 'ai_chatbot_file_deletion_handler');

function ai_chatbot_file_deletion($file_name)
{
    $attachments = get_posts([
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'meta_query'  => [
            [
                'key'     => '_wp_attached_file',
                'value'   => $file_name,
                'compare' => 'LIKE',
            ]
        ],
    ]);

    if ($attachments) {
        $attach_id = $attachments[0]->ID;
        $document_id = 'file_' . $attach_id;

        $result = ai_chatbot_delete_qdrant_document($document_id);

        if (!$result['success'])
        {
            return [
                'success' => false,
                'message' => $result['message'],
                'data'    => null,
            ];
        }

        $file_path = get_attached_file($attach_id);
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        wp_delete_attachment($attach_id, true);

        return [
            'success' => true,
            'message' => "",
            'data'    => null,
        ];
    }
    else
    {
        return [
            'success' => false,
            'message' => "Failed to get attachements",
            'data'    => null,
        ];
    }
}

function ai_chatbot_upload_file_handler()
{
    if (!isset($_POST['ai_chatbot_nonce']) || !wp_verify_nonce($_POST['ai_chatbot_nonce'], 'ai_chatbot_handler')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        wp_die();
    }

    if (empty($_FILES['ai_chatbot_file'])) {
        wp_send_json_error(['message' => 'No file uploaded.']);
        wp_die();
    }

    $file = $_FILES['ai_chatbot_file'];
    $allowed_types = [
        'application/pdf',
        'text/plain', 'text/markdown', 'text/x-markdown', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ];

    $name = $file['name'];
    $type = $file['type'];
    $tmp  = $file['tmp_name'];

    if (!in_array($type, $allowed_types)) {
        wp_send_json_error(['message' => $name . ': Invalid file type.']);
        wp_die();
    }

    $upload_dir = wp_upload_dir()['basedir'] . '/' . "ba-chatbot/";
    $upload_url = wp_upload_dir()['url'] . '/' . "ba-chatbot/";

    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir); // WordPress-safe recursive directory creation
    }

    $filename = wp_unique_filename($upload_dir, $name);
    $target = $upload_dir . $filename;

    $filetype = wp_check_filetype($filename, null);
    if (!in_array($filetype['type'], $allowed_types)) {
        wp_send_json_error(['message' => $name . ': Invalid file type.']);
        wp_die();
    }

    if (!move_uploaded_file($tmp, $target)) {
        wp_send_json_error(['message' => $name . ': Upload failed.']);
        wp_die();
    }

    $filetype = wp_check_filetype($filename, null);

    $attachment = [
        'guid'           => $upload_url . $filename,
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'meta_input'     => [
            '_ai_chatbot_uploaded' => true,
        ],
    ];

    $attach_id = wp_insert_attachment($attachment, $target);
    $document_id = 'file_' . $attach_id;

    $data = ai_chatbot_process_uploaded_file($target, $type);

    if ($data['success']) {
        foreach ($data['data'] as $embedding) {
            if ($embedding) {
                $result = ai_chatbot_send_to_qdrant(
                    $embedding['embedding'],
                    $embedding['text'],
                    $document_id
                );

                if (!$result['success'])
                {
                    unlink($target);
                    wp_delete_attachment($attach_id, true);

                    wp_send_json_error(['message' => $result['message']]);
                    wp_die();
                }
            }
        }
    }
    else
    {
        unlink($target);
        wp_delete_attachment($attach_id, true);
        
        wp_send_json_error(['message' => $data['message']]);
        wp_die();
    }

    wp_send_json_success([
        'message' => $name . ' uploaded and processed successfully.',
        'file_name' => $filename,
        'attachment_id' => $attach_id,
        'document_id' => $document_id,
        'file_url' => $upload_url . $filename,
    ]);

    wp_die();
}
add_action('wp_ajax_ai_chatbot_upload_file', 'ai_chatbot_upload_file_handler');

function ai_chatbot_set_post_option($option, $post)
{
    if (!isset($_POST[$post]))
        return;

    if (!current_user_can('manage_options'))
        return;

    $value = sanitize_text_field($_POST[$post]);

    update_option($option, $value);

    return "Saving option: " . $option . " with value " . $value;
}

function ai_chatbot_save_settings_handler()
{
    if (!isset($_POST['ai_chatbot_nonce']) || !wp_verify_nonce($_POST['ai_chatbot_nonce'], 'ai_chatbot_handler')) 
    {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        wp_die();
    }

    $savedOptions = [];

    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_qdrant_collection', 'qdrant_collection');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_name', 'bot_name');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_intro_message', 'intro_message');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_open', 'open_chat');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_speech', 'speech_friendly');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_speech', 'speech_respectful');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_chat_color', 'chat_color');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_email', 'email');
    $savedOptions[] = ai_chatbot_set_post_option('ba_bot_phone', 'phone_number');

    $changed_qdrant_url = isset($_POST['qdrant_url']);
    $url_change_success = false;
    $url_change_message = "";
    $changed_qdrant_api = isset($_POST['qdrant_api']);
    $qdrant_api_change_success = false;
    $qdrant_api_change_message = "";
    $changed_gpt_api = isset($_POST['gpt_api']);
    $gpt_api_change_success = false;
    $gpt_api_change_message = "";

    $current_url_encrypted = get_option('ba_qdrant_url', false);
    $current_api_encrypted = get_option('ba_qdrant_api_key', false);

    $current_url = $current_url_encrypted ? ba_decrypt($current_url_encrypted) : '';
    $current_api = $current_api_encrypted ? ba_decrypt($current_api_encrypted) : '';

    $new_url = isset($_POST['qdrant_url']) ? esc_url_raw($_POST['qdrant_url']) : '';
    $new_api = isset($_POST['qdrant_api']) ? sanitize_text_field($_POST['qdrant_api']) : '';

    if ($changed_qdrant_url && $new_url) {
        $qdrant_url = $new_url;
    } else {
        $qdrant_url = $current_url;
    }

    if ($changed_qdrant_api && $new_api) {
        $qdrant_api = $new_api;
    } else {
        $qdrant_api = $current_api;
    }

    if ($qdrant_url && $qdrant_api) {
        $response = wp_remote_get(rtrim($qdrant_url, '/') . '/collections', [
            'headers' => ['api-key' => $qdrant_api],
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();

            if ($changed_qdrant_url) {
                $url_change_message = "Error connecting to Qdrant: $error_msg";
                $url_change_success = false;
            }

            if ($changed_qdrant_api) {
                $qdrant_api_change_message = "Error connecting to Qdrant: $error_msg";
                $qdrant_api_change_success = false;
            }
        } else {
            $status = wp_remote_retrieve_response_code($response);

            if ($status === 200) {
                if ($changed_qdrant_url) {
                    $url_change_message = "URL reachable!";
                    $url_change_success = true;
                    update_option('ba_qdrant_url', ba_encrypt($new_url));
                }

                if ($changed_qdrant_api) {
                    $qdrant_api_change_message = "API key works!";
                    $qdrant_api_change_success = true;
                    update_option('ba_qdrant_api_key', ba_encrypt($qdrant_api));
                }
            } else {
                if ($changed_qdrant_url) {
                    $url_change_message = "URL responded, but request failed (HTTP $status)";
                    $url_change_success = false;
                }

                if ($changed_qdrant_api) {
                    $qdrant_api_change_message = "API key invalid or request failed (HTTP $status)";
                    $qdrant_api_change_success = false;
                }
            }
        }
    } else {
        if ($changed_qdrant_url && !$qdrant_api) {
            $url_change_message = "Cannot test URL: API key not set.";
            $url_change_success = false;
        }

        if ($changed_qdrant_api && !$qdrant_url) {
            $qdrant_api_change_message = "Cannot test API key: Qdrant URL not set.";
            $qdrant_api_change_success = false;
        }
    }

    if ($changed_gpt_api) 
    {
        $gpt_api = sanitize_text_field($_POST['gpt_api']);
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $gpt_api,
            ],
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            $gpt_api_change_message = "Error connecting to OpenAI: " . $response->get_error_message();
            $gpt_api_change_success = false;
        } else {
            $status = wp_remote_retrieve_response_code($response);

            if ($status === 200) {
                $gpt_api_change_message = "API key works! Successfully connected to OpenAI.";
                $gpt_api_change_success = true;
                update_option('ba_gpt_api_key', ba_encrypt($gpt_api));
            } else {
                $gpt_api_change_message = "API key invalid or request failed (HTTP $status)";
                $gpt_api_change_success = false;
            }
        }
    }

    ba_chatbot_handle_icon_upload();

    wp_send_json_success([
        'message' => 'Saved settings successfully',
        'image_url' => get_option('ba_bot_icon_url'),
        'qdrant_url' => [
            'update' => $changed_qdrant_url,
            'success' => $changed_qdrant_url ? $url_change_success : false,
            'message' => $changed_qdrant_url ? $url_change_message : '',
        ],
        'qdrant_api' => [
            'update' => $changed_qdrant_api,
            'success' => $changed_qdrant_api ? $qdrant_api_change_success : false,
            'message' => $changed_qdrant_api ? $qdrant_api_change_message : '',
        ],
        'gpt_api' => [
            'update' => $changed_gpt_api,
            'success' => $changed_gpt_api ? $gpt_api_change_success : false,
            'message' => $changed_gpt_api ? $gpt_api_change_message : '',
        ],
        'saved_options' => $savedOptions
    ]);

    wp_die();
}
add_action('wp_ajax_ai_chatbot_save_settings', 'ai_chatbot_save_settings_handler');


function ba_chatbot_handle_icon_upload($file_input_name = 'bot_icon') {

    if (empty($_FILES[$file_input_name])) {
        return false;
    }

    $file_tmp  = $_FILES[$file_input_name]['tmp_name'];
    $file_type = mime_content_type($file_tmp);

    $allowed_mimes = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($file_type, $allowed_mimes)) {
        return false;
    }

    $ext = strtolower(pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION));

    $upload_dir_info = wp_upload_dir();
    $upload_dir = $upload_dir_info['basedir'] . '/ba-chatbot/';
    $upload_url = $upload_dir_info['baseurl'] . '/ba-chatbot/';

    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }

    $destination = $upload_dir . 'profile-picture.' . $ext;

    $old_files = glob($upload_dir . 'profile-picture.*');
    foreach ($old_files as $old) {
        if ($old !== $destination) {
            @unlink($old);
        }
    }

    if (move_uploaded_file($file_tmp, $destination)) {
        update_option('ba_bot_icon_ext', $ext);
        update_option('ba_bot_icon_url', $upload_url . 'profile-picture.' . $ext);
        return true;
    }

    return false;
}

function ba_chatbot_get_analytics_handler()
{
    if (!isset($_POST['ai_chatbot_nonce']) || !wp_verify_nonce($_POST['ai_chatbot_nonce'], 'ai_chatbot_handler')) 
    {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        wp_die();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ai_chat_messages';

    // Fetch stats
    $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    // Weekly message counts (last 7 days)
    $weekly_counts = $wpdb->get_results("
        SELECT DATE(created_at) as day, COUNT(*) as count
        FROM $table
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ", ARRAY_A);

    // Prepare JS data for chart
    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = $day;
        $found = false;
        foreach ($weekly_counts as $row) {
            if ($row['day'] === $day) {
                $chart_data[] = (int)$row['count'];
                $found = true;
                break;
            }
        }
        if (!$found) $chart_data[] = 0;
    }

    wp_send_json_success([
        'total_messages' => $total_messages,
        'chart_labels' => $chart_labels,
        'chart_data' => $chart_data
    ]);
    wp_die();
}
add_action('wp_ajax_ba_chatbot_get_analytics', 'ba_chatbot_get_analytics_handler');

function ba_chatbot_get_translated_text_handler()
{
    if (!isset($_POST['ai_chatbot_nonce']) || !wp_verify_nonce($_POST['ai_chatbot_nonce'], 'ai_chatbot_handler')) 
    {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        wp_die();
    }

    if (!isset($_POST["languages"]))
    {
        wp_send_json_error(['message' => 'No languages defined']);
        wp_die();
    }

    error_log($_POST["languages"]);
    wp_send_json_success(['message' => 'Success']);
}
add_action('wp_ajax_get_translated_text', 'ba_chatbot_get_translated_text_handler');