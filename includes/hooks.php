<?php

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_ai-chatbot-admin') {
        return;
    }

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

});


// Enqueue widget JS/CSS on frontend if shortcode present
add_action('wp_enqueue_scripts', function() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'Chatbot')) {
        wp_enqueue_style('ai-chatbot-widget-css', AI_CHATBOT_URL . 'assets/css/ai-chatbot-widget.css');
        wp_enqueue_script('ai-chatbot-widget-js', AI_CHATBOT_URL . 'assets/js/ai-chatbot-widget.js', [], time(), true);
        wp_localize_script('ai-chatbot-widget-js', 'ai_chatbot_widget', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'speech' => (get_option("ba_bot_speech") == "friendly") ? "friendly" : "respectful"
        ]);
    }
});


function ai_chatbot_search_handler() {
    $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
    if (!$question) {
        error_log('[AI Chatbot] No question provided.');
        wp_send_json_error('No question provided.');
    }

    // Get embedding for question
    $embedding = ai_chatbot_send_to_openai_embeddings($question);
    if (!$embedding) {
        error_log('[AI Chatbot] Failed to get embedding for question: ' . $question);
        wp_send_json_error('Failed to get embedding.');
    }

    // Query Qdrant
    $results = ai_chatbot_query_qdrant($embedding, 5);
    if (!$results || empty($results['result'])) {
        error_log('[AI Chatbot] No results found in Qdrant for question: ' . $question);
        wp_send_json_error('No results found.');
    }
    
    // Extract context chunks
    $context_chunks = [];
    foreach ($results['result'] as $point) {
        if (isset($point['payload']['text'])) {
            $context_chunks[] = $point['payload']['text'];
        }
    }

    // Ask LLM
    $answer = ai_chatbot_ask_llm($question, $context_chunks);
    if (!$answer) {
        error_log('[AI Chatbot] LLM failed to generate an answer for question: ' . $question);
        wp_send_json_error('LLM failed to generate an answer.');
    }
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
        wp_send_json_error(['message' => 'Failed to delete file ' . $file_name . "."]);
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

        $file_path = get_attached_file($attach_id);
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }

        wp_delete_attachment($attach_id, true);

        return ai_chatbot_delete_qdrant_document($document_id);
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

    $upload_dir = AI_CHATBOT_PATH . 'uploads/';
    $upload_url = AI_CHATBOT_URL . 'uploads/';

    $filename = wp_unique_filename($upload_dir, $name);
    $target = $upload_dir . $filename;

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
        'file_url' => wp_upload_dir()['url'] . '/' . $filename,
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
                $qdrant_url_change_message = "Error connecting to Qdrant: $error_msg";
                $qdrant_url_change_success = false;
            }

            if ($changed_qdrant_api) {
                $qdrant_api_change_message = "Error connecting to Qdrant: $error_msg";
                $qdrant_api_change_success = false;
            }
        } else {
            $status = wp_remote_retrieve_response_code($response);

            if ($status === 200) {
                if ($changed_qdrant_url) {
                    $qdrant_url_change_message = "URL reachable!";
                    $qdrant_url_change_success = true;
                    update_option('ba_qdrant_url', ba_encrypt($new_url));
                }

                if ($changed_qdrant_api) {
                    $qdrant_api_change_message = "API key works!";
                    $qdrant_api_change_success = true;
                    update_option('ba_qdrant_api_key', ba_encrypt($qdrant_api));
                }
            } else {
                if ($changed_qdrant_url) {
                    $qdrant_url_change_message = "URL responded, but request failed (HTTP $status)";
                    $qdrant_url_change_success = false;
                }

                if ($changed_qdrant_api) {
                    $qdrant_api_change_message = "API key invalid or request failed (HTTP $status)";
                    $qdrant_api_change_success = false;
                }
            }
        }
    } else {
        if ($changed_qdrant_url && !$qdrant_api) {
            $qdrant_url_change_message = "Cannot test URL: API key not set.";
            $qdrant_url_change_success = false;
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
            $body = wp_remote_retrieve_body($response);

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

    if (!empty($_FILES['bot_icon'])) 
    {
        $file_tmp  = $_FILES['bot_icon']['tmp_name'];
        $file_type = mime_content_type($file_tmp);

        $allowed_mimes = [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif'
        ];

        if (in_array($file_type, $allowed_mimes) ) 
        {
            $ext = pathinfo($_FILES['bot_icon']['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext);

            $upload_dir = plugin_dir_path(__FILE__) . '../assets/img/';
            if ( ! file_exists($upload_dir) ) {
                mkdir($upload_dir, 0755, true);
            }

            $destination = $upload_dir . 'profile-picture.' . $ext;

            $old_files = glob($upload_dir . 'profile-picture.*');
            foreach ($old_files as $old) {
                if ($old !== $destination) 
                    unlink($old);
            }

            if ( move_uploaded_file($file_tmp, $destination) ) {
                update_option('ba_bot_icon_ext', $ext);
            }
        }
    }

    $pfp_img_url = '';
    $ext = get_option('ba_bot_icon_ext');
    if ($ext)
    {
        $pfp_img_url = AI_CHATBOT_URL . '/assets/img/profile-picture' . '.' . $ext;
    }

    wp_send_json_success([
        'message' => 'Saved settings successfully',
        'image_url' => $pfp_img_url,
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