<?php

// Admin panel: Add menu item
add_action('admin_menu', function() {
    add_menu_page(
        'AI Chatbot Admin',
        'AI Chatbot Admin',
        'manage_options',
        'ai-chatbot-admin',
        'ai_chatbot_admin_panel',
        'dashicons-upload',
        80
    );
});

function ai_chatbot_admin_panel() {
    $upload_dir = AI_CHATBOT_PATH . 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $error = '';
    $success = '';
    $allowed_types = [
        'application/pdf',
        'text/plain',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/msword', // doc
    ];

    // Handle file upload (multiple)
    if (isset($_POST['ai_chatbot_upload'])) {
        if (!empty($_FILES['ai_chatbot_file']['name'][0])) {
            $files = $_FILES['ai_chatbot_file'];
            $uploaded = 0;
            for ($i = 0; $i < count($files['name']); $i++) {
                $name = $files['name'][$i];
                $type = $files['type'][$i];
                $tmp = $files['tmp_name'][$i];
                if (!in_array($type, $allowed_types)) {
                    $error .= esc_html($name) . ': Invalid file type.<br>';
                    continue;
                }
                // Move file first
                $filename = wp_unique_filename($upload_dir, $name);
                $target = $upload_dir . '/' . $filename;

                if (move_uploaded_file($tmp, $target)) {
                    // Register as WP attachment
                    $filetype = wp_check_filetype($filename, null);
                    $attachment = [
                        'guid'           => $target,
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => sanitize_file_name($filename),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                        'meta_input'     => [
                            '_ai_chatbot_uploaded' => true, // optional flag
                        ],
                    ];
                    $attach_id = wp_insert_attachment($attachment, $target);

                    // This is your stable document ID
                    $document_id = 'file_' . $attach_id;

                    // Process file and send to Qdrant
                    $data = ai_chatbot_process_uploaded_file($target, $type);

                    if ($data) {
                        foreach ($data as $embedding) {
                            if ($embedding) {
                                $result = ai_chatbot_send_to_qdrant(
                                    $embedding['embedding'], 
                                    $embedding['text'], 
                                    $document_id
                                );
                                error_log("Send to Qdrant result: " . print_r($result, true));
                            }
                        }
                    }
                } else {
                    $error .= esc_html($name) . ': Upload failed.<br>';
                }
            }
            if ($uploaded) {
                $success = "$uploaded file(s) uploaded and processed.";
            }
        } else {
            $error = 'No file selected.';
        }
    }

    $uploads = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'meta_key'       => '_ai_chatbot_uploaded', // optional flag you set when uploading
    ]);

    $qdrant_url        = get_option('ba_qdrant_url');
    $qdrant_api        = get_option('ba_qdrant_api_key');
    $gpt_api           = get_option('ba_gpt_api_key');

    $qdrant_collection = get_option('ba_bot_qdrant_collection');
    $bot_name          = get_option('ba_bot_name');
    $bot_intro         = get_option('ba_bot_intro_message');
    $open_widget       = get_option('ba_bot_open');
    $speech            = get_option('ba_bot_speech');
    $widget_color      = get_option('ba_bot_chat_color');

    $base_path = plugin_dir_path(__FILE__) . '../assets/img/profile-picture';
    $base_url  = plugin_dir_url(__FILE__) . '../assets/img/profile-picture';

    $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    $pfp_img_url = '';

    foreach ($extensions as $ext) {
        if (file_exists($base_path . '.' . $ext)) {
            $pfp_img_url = $base_url . '.' . $ext;
            break;
        }
    }

    ?>
<div id="ba-chatbot-admin-panel" class="ba-chatbot-admin-wrap">

    <div class="ba-chatbot-page-header">
        <h1>Knowledge Base</h1>
        <p>Upload files to train your chatbot. Select files and process them to add to your knowledge base.</p>
    </div>

    <div class="ba-chatbot-main-grid">
        <div class="ba-chatbot-left-col">

            <div class="ba-chatbot-dropzone" id="dropzone">
                <div class="ba-chatbot-dropzone-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                </div>
                <span class="ba-chatbot-dropzone-title">Drag and drop files here</span>
                <span class="ba-chatbot-dropzone-sub">or click the button below to browse</span>
                <label class="ba-chatbot-dropzone-btn" for="fileInput">Choose Files</label>
                <input type="file" id="fileInput" class="ba-chatbot-file-input" multiple accept=".pdf,.txt,.doc,.docx,.md" />
                <span class="ba-chatbot-dropzone-formats">Supported: PDF, TXT, DOC, DOCX, MD</span>
            </div>

            <div id="fileTableWrap">
                <div class="ba-chatbot-card" id="tableCard">
                    <table class="ba-chatbot-file-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th class="ba-chatbot-col-size">Size</th>
                                <th class="ba-chatbot-col-status">Status</th>
                                <th class="ba-chatbot-col-actions"></th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBody">
                            <?php foreach ($uploads as $upload) : 
                                $file_path = get_attached_file($upload->ID);
                                if (!file_exists($file_path)) {
                                    $file_path = wp_get_upload_dir()['basedir'] . '/' . $upload->_wp_attached_file;
                                }

                                $filename = basename($file_path);
                                $filesize = filesize($file_path);
                                $formatted_size = size_format($filesize);
                                $file_id = $upload->ID;

                                if (!$formatted_size)
                                    $formatted_size = "N/A";

                                $badgeClass = 'badge-success';
                                $badgeText = 'Uploaded';
                            ?>
                                <tr id="ba-chatbot-element-<?= $file_id ?>">
                                    <td>
                                        <div class="ba-chatbot-file-name-cell">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                            </svg>
                                            <span><?php echo esc_html($filename); ?></span>
                                        </div>
                                    </td>

                                    <td class="ba-chatbot-col-size">
                                        <?php echo esc_html($formatted_size); ?>
                                    </td>

                                    <td class="ba-chatbot-col-status">
                                        <span class="ba-chatbot-<?php echo esc_attr($badgeClass); ?>">
                                            <?php echo esc_html($badgeText); ?>
                                        </span>
                                    </td>

                                    <td class="ba-chatbot-col-actions">
                                        <button 
                                            class="ba-chatbot-remove-btn"
                                            onclick="removeFile('<?php echo esc_js($file_id); ?>')"
                                            aria-label="Remove <?php echo esc_attr($filename); ?>"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="ba-chatbot-right-col">
            <div class="ba-chatbot-card" id="resultsPanel">
                <div id="resultsContent">
                    <div class="ba-chatbot-results-list" id="resultsList"></div>
                </div>
            </div>
        </div>
        <div class="ba-chatbot-settings-col">
            <div class="ba-chatbot-card" id="settingsPanel">
                <div class="ba-chatbot-card-header">
                    <h2>Plugin Settings</h2>
                    <p>Configure your chatbot connection and appearance.</p>
                </div>

                <div class="flex col gap-2">

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_qdrant_url">
                        <label for="ba_qdrant_url">Qdrant URL</label>
                        <input type="text" id="ba_qdrant_url" class="ba-chatbot-input" placeholder="<?php echo $qdrant_url ? '..........' : 'Enter Qdrant URL'?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_qdrant_api">
                        <label for="ba_qdrant_api_key">Qdrant API Key</label>
                        <input type="text" id="ba_qdrant_api_key" class="ba-chatbot-input" placeholder="<?php echo $qdrant_api ? '..........' : 'Enter API Key'?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_gpt_api">
                        <label for="ba_chatgpt_api_key">ChatGPT API Key</label>
                        <input type="text" id="ba_chatgpt_api_key" class="ba-chatbot-input" placeholder="<?php echo $gpt_api ? '..........' : 'Enter API Key'?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_qdrant_collection">
                        <label for="ba_qdrant_collection">Qdrant Collection Name</label>
                        <input type="text" id="ba_qdrant_collection" class="ba-chatbot-input" placeholder="e.g. website_knowledge" value="<?=$qdrant_collection?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_bot_name">
                        <label for="ba_bot_name">Bot Name</label>
                        <input type="text" id="ba_bot_name" class="ba-chatbot-input" placeholder="e.g. Support Assistant" value="<?=$bot_name?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_intro_message">
                        <label for="ba_bot_intro">Intro Message</label>
                        <textarea id="ba_bot_intro" class="ba-chatbot-textarea" rows="4" placeholder="Hello! How can I help you today?"><?=$bot_intro?></textarea>
                    </div>

                    <div class="flex row items-center b-1 b-gray-3 p-2 rounded just-between" id="ba_chatbot_open_widget">
                        <label for="ba_widget_color">Open Widget On Enter</label>
                        <input type="checkbox" id="ba_open_widget" class="ba-chatbot-checkbox" <?php if ($open_widget) echo "checked" ?>>
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded" id="ba_chatbot_speech">
                        <label for="ba_widget_color">Speech Type</label>
                        <div class="flex row just-between">
                            <div class="flex row gap-2 items-center">
                                <label for="ba_widget_color">Friendly</label>
                                <input type="radio" name="ba_chatbot_speech_radio" id="ba_speech_friendly" class="ba-chatbot-radio" <?php if ($speech == "friendly") echo "checked" ?>>
                            </div>
                            <div class="flex row gap-2 items-center">
                                <label for="ba_widget_color">Respectful</label>
                                <input type="radio" name="ba_chatbot_speech_radio" id="ba_speech_respectful" class="ba-chatbot-radio" <?php if ($speech != "friendly") echo "checked" ?>>
                            </div>
                        </div>
                    </div>

                    <div class="flex row just-between items-center b-1 b-gray-3 p-2 rounded" id="ba_chatbot_widget_color">
                        <label for="ba_widget_color">Widget Color</label>
                        <input type="color" id="ba_widget_color" class="ba-chatbot-color-picker" value="<?=$widget_color?>">
                    </div>

                    <div class="flex col gap-2 b-1 b-gray-3 p-2 rounded">
                        <div class="flex row just-between items-center">  
                            <label for="ba_bot_icon">Chatbot Icon</label>
                            <div class="ba-chatbot-icon-upload" id="ba_chatbot_icon_upload">
                                <label for="ba_bot_icon" class="ba-chatbot-file-btn">
                                    Choose Chatbot Icon
                                </label>
                                <input type="file" id="ba_bot_icon" class="ba-chatbot-file-input" accept="image/*">
                            </div>
                        </div>
                        <div class="ba-chatbot-icon-previews">
                            <div class="ba-chatbot-icon" id="ba_bot_icon_current">
                                <?php if ($pfp_img_url) : ?>
                                    <img src="<?= $pfp_img_url ?>" alt="Chat Icon" />
                                <?php else : ?>
                                    <span>No icon selected</span>
                                <?php endif; ?>
                            </div>
                            <div class="ba-chatbot-icon-arrow" id="ba_bot_icon_arrow" style="display: none;">
                                <span>&#8594;</span> <!-- → arrow -->
                            </div>
                            <div class="ba-chatbot-icon-preview" id="ba_bot_icon_preview" style="display: none;">
                                <img id="ba-bot-icon-preview-img" src="<?php echo esc_url($bot_icon_url); ?>" alt="Bot Icon">
                            </div>  
                        </div>
                    </div>

                    <div class="ba-chatbot-form-actions flex row just-between items-center">
                        <button class="ba-chatbot-primary-btn" id="ba_chatbot_save_btn">
                            Save Settings
                        </button>
                        <div id="notifications" class="notifications-container">
                            <div class="loading notification" style="display: none">
                                <svg width="24" height="24" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="#e5e7eb" stroke-width="4"/>
                                <circle cx="12" cy="12" r="10" stroke="#3b82f6" stroke-width="4" stroke-dasharray="31.4" stroke-linecap="round"/>
                                </svg>
                            </div>

                            <div class="success notification" style="display: none">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#10b981"/>
                                <path d="M8 12l3 3 5-5" stroke="white" stroke-width="2" fill="none"/>
                                </svg>
                            </div>

                            <div class="fail notification" style="display: none">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#ef4444"/>
                                <line x1="8" y1="8" x2="16" y2="16" stroke="white" stroke-width="2"/>
                                <line x1="16" y1="8" x2="8" y2="16" stroke="white" stroke-width="2"/>
                                </svg>
                            </div>

                            <div class="warning notification" style="display: none">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#f59e0b"/>
                                <text x="12" y="16" text-anchor="middle" font-size="14" fill="white" font-weight="bold">!</text>
                                </svg>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}