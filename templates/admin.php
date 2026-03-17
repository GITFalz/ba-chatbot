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

    add_submenu_page(
        'ai-chatbot-admin',
        'Analytics',
        'Analytics',
        'manage_options',
        'ai-chatbot-analytics',
        'ai_chatbot_analytics_panel'
    );

    add_submenu_page(
        'ai-chatbot-admin',
        'Languages',
        'Languages',
        'manage_options',
        'ai-chatbot-languages',
        'ai_chatbot_languages_panel'
    );
});

function ai_chatbot_admin_panel() {
    $upload_dir = AI_CHATBOT_PATH . 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $uploads = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'meta_key'       => '_ai_chatbot_uploaded', // optional flag you set when uploading
    ]);

    $qdrant_url         = get_option('ba_qdrant_url');
    $qdrant_api         = get_option('ba_qdrant_api_key');
    $gpt_api            = get_option('ba_gpt_api_key');

    $qdrant_collection  = get_option('ba_bot_qdrant_collection');
    $bot_name           = get_option('ba_bot_name');
    $bot_intro          = get_option('ba_bot_intro_message');
    $open_widget        = get_option('ba_bot_open');
    $speech             = get_option('ba_bot_speech');
    $widget_color       = get_option('ba_bot_chat_color');
    $email              = get_option('ba_bot_email');
    $phone              = get_option('ba_bot_phone');

    $pfp_img_url        = get_option('ba_bot_icon_url');

    ?>
<script>
    const theme = localStorage.getItem("ba_theme");
    if(theme === "light"){
        document.body.classList.add("light-theme");
    }
</script>
<div id="ba-chatbot-admin-panel" class="ba-chatbot-main ba-chatbot-admin-wrap">
    <div class="ba-chatbot-page-header just-between">
        <div class="flex row just-center items-center gap-4">
            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2m16 0h2m-7-1v2m-6-2v2"/></g></svg>
            <h1>Knowledge Base</h1>
        </div>
        <label class="ba-theme-toggle">
            <input type="checkbox" id="themeToggle">
            <span class="ba-theme-slider">
                <span class="ba-theme-icon sun">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 19a1 1 0 0 1 .993.883L13 20v1a1 1 0 0 1-1.993.117L11 21v-1a1 1 0 0 1 1-1m6.313-2.09l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7a1 1 0 0 1 1.218-1.567zm-11.306.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M4 11a1 1 0 0 1 .117 1.993L4 13H3a1 1 0 0 1-.117-1.993L3 11zm17 0a1 1 0 0 1 .117 1.993L21 13h-1a1 1 0 0 1-.117-1.993L20 11zM6.213 4.81l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7A1 1 0 0 1 6.11 4.74zm12.894.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M12 2a1 1 0 0 1 .993.883L13 3v1a1 1 0 0 1-1.993.117L11 4V3a1 1 0 0 1 1-1m0 5a5 5 0 1 1-4.995 5.217L7 12l.005-.217A5 5 0 0 1 12 7"/></svg>
                </span>
                <span class="ba-theme-icon moon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 1.992a10 10 0 1 0 9.236 13.838c.341-.82-.476-1.644-1.298-1.31a6.5 6.5 0 0 1-6.864-10.787l.077-.08c.551-.63.113-1.653-.758-1.653h-.266l-.068-.006z"/></svg>
                </span>
            </span>
        </label>
        
    </div>
    <div class="ba-chatbot-main-grid">
        <div class="ba-chatbot-files-panel">
            <div class="ba-chatbot-left-col">
                <div class="ba-chatbot-dropzone rounded-xl" id="dropzone">
                    <div class="ba-chatbot-dropzone-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    </div>
                    <span class="ba-chatbot-dropzone-title">Drag and drop files here</span>
                    <span class="ba-chatbot-dropzone-sub">or click the button below to browse</span>
                    <label class="ba-chatbot-dropzone-btn text-white" for="fileInput">Choose Files</label>
                    <input type="file" id="fileInput" class="ba-chatbot-file-input" multiple accept=".pdf,.txt,.doc,.docx,.md" />
                    <span class="ba-chatbot-dropzone-formats">Supported: PDF, TXT, DOC, DOCX, MD</span>
                </div>
                <div id="fileTableWrap" class="rounded-xl b-2">
                    <div id="tableCard">
                        <table class="ba-chatbot-file-table">
                            <thead>
                                <tr class="hide-columns">
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
                                    <tr class="hide-columns" id="ba-chatbot-element-<?= $file_id ?>">
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
                                                <svg class="text-red-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
            <div class="ba-chatbot-right-col rounded-xl b-2">
                <div class="ba-chatbot-card-header flex items-center gap-4">
                    <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2"><path stroke-linecap="round" d="M7 21a2 2 0 0 1-2-2V3h9l5 5v11a2 2 0 0 1-2 2z"/><path d="M13 3v6h6"/></g></svg>
                    <h2>Processing Files</h2>
                </div>
                <div id="resultsPanel" class=" ba-border-top">
                    <div id="resultsContent">
                        <div class="ba-chatbot-results-list" id="resultsList"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ba-chatbot-side-panel rounded-xl b-2">
            <div id="settingsPanel">
                <div class="ba-chatbot-card-header flex items-center gap-4">
                    <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M12 8.25a3.75 3.75 0 1 0 0 7.5a3.75 3.75 0 0 0 0-7.5M9.75 12a2.25 2.25 0 1 1 4.5 0a2.25 2.25 0 0 1-4.5 0"/><path d="M11.975 1.25c-.445 0-.816 0-1.12.02a2.8 2.8 0 0 0-.907.19a2.75 2.75 0 0 0-1.489 1.488c-.145.35-.184.72-.2 1.122a.87.87 0 0 1-.415.731a.87.87 0 0 1-.841-.005c-.356-.188-.696-.339-1.072-.389a2.75 2.75 0 0 0-2.033.545a2.8 2.8 0 0 0-.617.691c-.17.254-.356.575-.578.96l-.025.044c-.223.385-.408.706-.542.98c-.14.286-.25.568-.29.88a2.75 2.75 0 0 0 .544 2.033c.231.301.532.52.872.734a.87.87 0 0 1 .426.726a.87.87 0 0 1-.426.726c-.34.214-.64.433-.872.734a2.75 2.75 0 0 0-.545 2.033c.041.312.15.594.29.88c.135.274.32.595.543.98l.025.044c.222.385.408.706.578.96c.177.263.367.5.617.69a2.75 2.75 0 0 0 2.033.546c.376-.05.716-.2 1.072-.389a.87.87 0 0 1 .84-.005a.86.86 0 0 1 .417.731c.015.402.054.772.2 1.122a2.75 2.75 0 0 0 1.488 1.489c.29.12.59.167.907.188c.304.021.675.021 1.12.021h.05c.445 0 .816 0 1.12-.02c.318-.022.617-.069.907-.19a2.75 2.75 0 0 0 1.489-1.488c.145-.35.184-.72.2-1.122a.87.87 0 0 1 .415-.732a.87.87 0 0 1 .841.006c.356.188.696.339 1.072.388a2.75 2.75 0 0 0 2.033-.544c.25-.192.44-.428.617-.691c.17-.254.356-.575.578-.96l.025-.044c.223-.385.408-.706.542-.98c.14-.286.25-.569.29-.88a2.75 2.75 0 0 0-.544-2.033c-.231-.301-.532-.52-.872-.734a.87.87 0 0 1-.426-.726c0-.278.152-.554.426-.726c.34-.214.64-.433.872-.734a2.75 2.75 0 0 0 .545-2.033a2.8 2.8 0 0 0-.29-.88a18 18 0 0 0-.543-.98l-.025-.044a18 18 0 0 0-.578-.96a2.8 2.8 0 0 0-.617-.69a2.75 2.75 0 0 0-2.033-.546c-.376.05-.716.2-1.072.389a.87.87 0 0 1-.84.005a.87.87 0 0 1-.417-.731c-.015-.402-.054-.772-.2-1.122a2.75 2.75 0 0 0-1.488-1.489c-.29-.12-.59-.167-.907-.188c-.304-.021-.675-.021-1.12-.021zm-1.453 1.595c.077-.032.194-.061.435-.078c.247-.017.567-.017 1.043-.017s.796 0 1.043.017c.241.017.358.046.435.078c.307.127.55.37.677.677c.04.096.073.247.086.604c.03.792.439 1.555 1.165 1.974s1.591.392 2.292.022c.316-.167.463-.214.567-.227a1.25 1.25 0 0 1 .924.247c.066.051.15.138.285.338c.139.206.299.483.537.895s.397.69.506.912c.107.217.14.333.15.416a1.25 1.25 0 0 1-.247.924c-.064.083-.178.187-.48.377c-.672.422-1.128 1.158-1.128 1.996s.456 1.574 1.128 1.996c.302.19.416.294.48.377c.202.263.29.595.247.924c-.01.083-.044.2-.15.416c-.109.223-.268.5-.506.912s-.399.689-.537.895c-.135.2-.219.287-.285.338a1.25 1.25 0 0 1-.924.247c-.104-.013-.25-.06-.567-.227c-.7-.37-1.566-.398-2.292.021s-1.135 1.183-1.165 1.975c-.013.357-.046.508-.086.604a1.25 1.25 0 0 1-.677.677c-.077.032-.194.061-.435.078c-.247.017-.567.017-1.043.017s-.796 0-1.043-.017c-.241-.017-.358-.046-.435-.078a1.25 1.25 0 0 1-.677-.677c-.04-.096-.073-.247-.086-.604c-.03-.792-.439-1.555-1.165-1.974s-1.591-.392-2.292-.022c-.316.167-.463.214-.567.227a1.25 1.25 0 0 1-.924-.247c-.066-.051-.15-.138-.285-.338a17 17 0 0 1-.537-.895c-.238-.412-.397-.69-.506-.912c-.107-.217-.14-.333-.15-.416a1.25 1.25 0 0 1 .247-.924c.064-.083.178-.187.48-.377c.672-.422 1.128-1.158 1.128-1.996s-.456-1.574-1.128-1.996c-.302-.19-.416-.294-.48-.377a1.25 1.25 0 0 1-.247-.924c.01-.083.044-.2.15-.416c.109-.223.268-.5.506-.912s.399-.689.537-.895c.135-.2.219-.287.285-.338a1.25 1.25 0 0 1 .924-.247c.104.013.25.06.567.227c.7.37 1.566.398 2.292-.022c.726-.419 1.135-1.182 1.165-1.974c.013-.357.046-.508.086-.604c.127-.307.37-.55.677-.677"/></g></svg>
                    <h2>Settings</h2>
                </div>

                <div class="flex row h-full ba-border-top">
                    <div class="flex col w-full">
                        <div class="ba-panel gap-2" id="ba_api_settings">
                            <div class="ba-card flex col gap-2 " id="ba_chatbot_gpt_api">
                                <label for="ba_chatgpt_api_key">ChatGPT API Key</label>
                                <input type="text" id="ba_chatgpt_api_key" class="ba-chatbot-input" placeholder="<?php echo $gpt_api ? '..........' : 'Enter API Key'?>">
                            </div>

                            <div class="ba-card flex col gap-2 " id="ba_chatbot_qdrant_api">
                                <label for="ba_qdrant_api_key">Qdrant API Key</label>
                                <input type="text" id="ba_qdrant_api_key" class="ba-chatbot-input" placeholder="<?php echo $qdrant_api ? '..........' : 'Enter API Key'?>">
                            </div>
                        </div>
                        <div class="ba-panel gap-2 ba-settings-hidden" id="ba_database_settings">
                            <div class="ba-card flex col gap-2 " id="ba_chatbot_qdrant_url">
                                <label for="ba_qdrant_url">Qdrant URL</label>
                                <input type="text" id="ba_qdrant_url" class="ba-chatbot-input" placeholder="<?php echo $qdrant_url ? '..........' : 'Enter Qdrant URL'?>">
                            </div>

                            <div class="ba-card flex col gap-2 " id="ba_chatbot_qdrant_collection">
                                <label for="ba_qdrant_collection">Qdrant Collection Name</label>
                                <input type="text" id="ba_qdrant_collection" class="ba-chatbot-input" placeholder="e.g. website_knowledge" value="<?=$qdrant_collection?>">
                            </div>
                        </div>
                        <div class="ba-panel gap-2 ba-settings-hidden" id="ba_bot_settings">
                            <div class="ba-card flex col gap-2 " id="ba_chatbot_bot_name">
                                <label for="ba_bot_name">Bot Name</label>
                                <input type="text" id="ba_bot_name" class="ba-chatbot-input" placeholder="e.g. Support Assistant" value="<?=$bot_name?>">
                            </div>

                            <div class="ba-card flex col gap-2 " id="ba_chatbot_intro_message">
                                <label for="ba_bot_intro">Intro Message</label>
                                <textarea id="ba_bot_intro" class="ba-chatbot-textarea" rows="4" placeholder="Hello! How can I help you today?"><?=$bot_intro?></textarea>
                            </div>

                            <div class="ba-card ba-card-border flex row items-center just-between" id="ba_chatbot_open_widget">
                                <label for="ba_widget_color">Open Widget On Page Entered</label>
                                <label class="ba-toggle">
                                    <input type="checkbox" id="ba_open_widget" class="ba-chatbot-checkbox" <?php if ($open_widget) echo "checked" ?>>
                                    <span class="ba-slider"></span>
                                </label>
                            </div>

                            <div class="ba-card ba-select-card flex col gap-2 " id="ba_chatbot_speech">
                                <label for="ba_widget_color">Speech Type</label>
                                <div class="ba-radio-group just-between">
                                    <div class="flex w-full">
                                        <input type="radio" id="ba_speech_friendly"  name="mode" <?php if ($speech == "friendly") echo "checked" ?>>
                                        <label for="ba_speech_friendly">Friendly</label>
                                    </div>
                                    <div class="flex w-full">
                                        <input type="radio" id="ba_speech_respectful" name="mode" <?php if ($speech != "friendly") echo "checked" ?>>
                                        <label for="ba_speech_respectful">Respectful</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ba-panel gap-2 ba-settings-hidden" id="ba_appearance_settings">
                            <div class="ba-card ba-card-border flex row just-between items-center " id="ba_chatbot_widget_color">
                                <label for="ba_widget_color">Widget Color</label>
                                <input type="color" id="ba_widget_color" class="ba-chatbot-color-picker" value="<?=$widget_color?>">
                            </div>

                            <div class="ba-card ba-file flex col gap-2 ">
                                <div class="flex row just-between items-center">  
                                    <label for="ba_bot_icon">Chatbot Icon</label>
                                </div>
                                <div class="ba-chatbot-icon-previews flex just-between items-center">
                                    <div class="ba-chatbot-icons flex row">
                                        <div class="ba-chatbot-icon" id="ba_bot_icon_current">
                                            <?php if ($pfp_img_url) : ?>
                                                <img src="<?= $pfp_img_url ?>" alt="Chat Icon" />
                                            <?php else : ?>
                                                <span>No icon selected</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ba-chatbot-icon-arrow" id="ba_bot_icon_arrow" style="display: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M8 6.82v10.36c0 .79.87 1.27 1.54.84l8.14-5.18a1 1 0 0 0 0-1.69L9.54 5.98A.998.998 0 0 0 8 6.82"/></svg>
                                        </div>
                                        <div class="ba-chatbot-icon-preview" id="ba_bot_icon_preview" style="display: none;">
                                            <img id="ba-bot-icon-preview-img" src="" alt="Bot Icon">
                                        </div>  
                                    </div>
                                    <div>
                                        <div class="ba-chatbot-icon-upload" id="ba_chatbot_icon_upload">
                                            <label for="ba_bot_icon" class="ba-chatbot-file-btn">
                                                Browse
                                            </label>
                                            <input type="file" id="ba_bot_icon" class="ba-chatbot-file-input" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ba-panel gap-2 ba-settings-hidden" id="ba_contact_settings">
                            <div class="ba-card flex col gap-2 " id="ba_chatbot_email">
                                <label for="ba_bot_name">Customer Service Email</label>
                                <input type="email" id="ba_bot_name" class="ba-chatbot-input" placeholder="Enter your email" value="<?=$email?>">
                            </div>

                            <div class="ba-card flex col gap-2 " id="ba_chatbot_phone_number">
                                <label for="ba_bot_intro">Customer Service Phone number</label>
                                <input type="tel" id="ba_bot_name" class="ba-chatbot-input" placeholder="Enter phone number" value="<?=$phone?>">
                            </div>
                        </div>
                    </div>
                    <div class="ba-chatbot-settings-menu">
                        <div class="ba-chatbot-settings-menu-item ba-settings-selected" id="ba_api_settings_btn">
                            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m15.5 7.5l2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4m2-2l-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/></g></svg>
                        </div>
                        <div class="ba-chatbot-settings-menu-item" id="ba_database_settings_btn">
                            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 21q-3.775 0-6.387-1.162T3 17V7q0-1.65 2.638-2.825T12 3t6.363 1.175T21 7v10q0 1.675-2.613 2.838T12 21m0-11.975q2.225 0 4.475-.638T19 7.025q-.275-.725-2.512-1.375T12 5q-2.275 0-4.462.638T5 7.025q.35.75 2.538 1.375T12 9.025M12 14q1.05 0 2.025-.1t1.863-.288t1.675-.462T19 12.525v-3q-.65.35-1.437.625t-1.675.463t-1.863.287T12 11t-2.05-.1t-1.888-.288T6.4 10.15T5 9.525v3q.625.35 1.4.625t1.663.463t1.887.287T12 14m0 5q1.15 0 2.338-.175t2.187-.462t1.675-.65t.8-.738v-2.45q-.65.35-1.437.625t-1.675.463t-1.863.287T12 16t-2.05-.1t-1.888-.288T6.4 15.15T5 14.525V17q.125.375.788.725t1.662.638t2.2.462T12 19"/></svg>
                        </div>
                        <div class="ba-chatbot-settings-menu-item" id="ba_bot_settings_btn">
                            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2m16 0h2m-7-1v2m-6-2v2"/></g></svg>
                        </div>
                        <div class="ba-chatbot-settings-menu-item" id="ba_appearance_settings_btn">
                            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 22A10 10 0 0 1 2 12A10 10 0 0 1 12 2c5.5 0 10 4 10 9a6 6 0 0 1-6 6h-1.8c-.3 0-.5.2-.5.5c0 .1.1.2.1.3c.4.5.6 1.1.6 1.7c.1 1.4-1 2.5-2.4 2.5m0-18a8 8 0 0 0-8 8a8 8 0 0 0 8 8c.3 0 .5-.2.5-.5c0-.2-.1-.3-.1-.4c-.4-.5-.6-1-.6-1.6c0-1.4 1.1-2.5 2.5-2.5H16a4 4 0 0 0 4-4c0-3.9-3.6-7-8-7m-5.5 6c.8 0 1.5.7 1.5 1.5S7.3 13 6.5 13S5 12.3 5 11.5S5.7 10 6.5 10m3-4c.8 0 1.5.7 1.5 1.5S10.3 9 9.5 9S8 8.3 8 7.5S8.7 6 9.5 6m5 0c.8 0 1.5.7 1.5 1.5S15.3 9 14.5 9S13 8.3 13 7.5S13.7 6 14.5 6m3 4c.8 0 1.5.7 1.5 1.5s-.7 1.5-1.5 1.5s-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5"/></svg>
                        </div>
                        <div class="ba-chatbot-settings-menu-item" id="ba_contact_settings_btn">
                            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2zm-2 0l-8 5l-8-5zm0 12H4V8l8 5l8-5z"/></svg>                        </div>
                    </div>
                </div>
                <div class="ba-chatbot-form-actions flex row just-between items-center ba-border-top">
                    <button class="ba-chatbot-primary-btn text-white" id="ba_chatbot_save_btn">
                        Save Settings
                    </button>
                    <div id="notifications" class="notifications-container">
                        <div class="loading notification" style="display:none">
                            <svg class="ba-icon" viewBox="0 0 24 24">
                                <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                                <circle class="ba-icon-loader" cx="12" cy="12" r="9"/>
                            </svg>
                        </div>

                        <div class="success notification" style="display:none">
                            <svg class="ba-icon" viewBox="0 0 24 24">
                                <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                                <path class="ba-icon-success" d="M7 12l3 3 6-6"/>
                            </svg>
                        </div>

                        <div class="fail notification" style="display:none">
                            <svg class="ba-icon" viewBox="0 0 24 24">
                                <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                                <path class="ba-icon-error" d="M8 8l8 8M16 8l-8 8"/>
                            </svg>
                        </div>

                        <div class="warning notification" style="display:none">
                            <svg class="ba-icon" viewBox="0 0 24 24">
                                <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                                <path class="ba-icon-warning" d="M12 7v6"/>
                                <circle class="ba-icon-warning-dot" cx="12" cy="16" r="1"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function ai_chatbot_analytics_panel() {
    ?>
<script>
    const theme = localStorage.getItem("ba_theme");
    if(theme === "light"){
        document.body.classList.add("light-theme");
    }
</script>
<div class="ba-chatbot-main ba-chatbot-admin-wrap">
    <div class="ba-chatbot-page-header just-between">
        <div class="flex row just-center items-center gap-4">
            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5 22a1 1 0 0 1-1-1v-8a1 1 0 0 1 2 0v8a1 1 0 0 1-1 1m5 0a1 1 0 0 1-1-1V3a1 1 0 0 1 2 0v18a1 1 0 0 1-1 1m5 0a1 1 0 0 1-1-1V9a1 1 0 0 1 2 0v12a1 1 0 0 1-1 1m5 0a1 1 0 0 1-1-1v-4a1 1 0 0 1 2 0v4a1 1 0 0 1-1 1"/></svg>
            <h1>AI Chatbot Analytics</h1>
        </div>
        <label class="ba-theme-toggle">
            <input type="checkbox" id="themeToggle">
            <span class="ba-theme-slider">
                <span class="ba-theme-icon sun">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 19a1 1 0 0 1 .993.883L13 20v1a1 1 0 0 1-1.993.117L11 21v-1a1 1 0 0 1 1-1m6.313-2.09l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7a1 1 0 0 1 1.218-1.567zm-11.306.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M4 11a1 1 0 0 1 .117 1.993L4 13H3a1 1 0 0 1-.117-1.993L3 11zm17 0a1 1 0 0 1 .117 1.993L21 13h-1a1 1 0 0 1-.117-1.993L20 11zM6.213 4.81l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7A1 1 0 0 1 6.11 4.74zm12.894.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M12 2a1 1 0 0 1 .993.883L13 3v1a1 1 0 0 1-1.993.117L11 4V3a1 1 0 0 1 1-1m0 5a5 5 0 1 1-4.995 5.217L7 12l.005-.217A5 5 0 0 1 12 7"/></svg>
                </span>
                <span class="ba-theme-icon moon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 1.992a10 10 0 1 0 9.236 13.838c.341-.82-.476-1.644-1.298-1.31a6.5 6.5 0 0 1-6.864-10.787l.077-.08c.551-.63.113-1.653-.758-1.653h-.266l-.068-.006z"/></svg>
                </span>
            </span>
        </label>
    </div>

    <!-- Top Stats -->
    <div class="ba-chatbot-main-grid w-full">
        <div class="ba-chatbot-left-col w-full">
            <div class="bac-row w-full" style="gap:16px;">
                <div class="ba-chatbot-card ba-chatbot-messages-sent w-full rounded-xl b-2 flex row items-center" style="padding:16px; gap:20px">
                    <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2m0 14H5.2L4 17.2V4h16z"/></svg>
                    <div class="flex col">
                        <h2>Messages Sent</h2>
                        <p id="ba_total_messages" style="font-size:24px; font-weight:700;">0</p>
                    </div>
                </div>
            </div>

            <!-- Weekly Bar Chart -->
            <div class="ba-card ba-card-border w-full h-full flex col rounded-xl b-2 items-start just-between">
                <h2>Messages Last 7 Days</h2>
                <canvas id="ba-chatbot-weekly-chart" style="margin-top:12px;"></canvas>
            </div>
        </div>

        <!-- Right Column -->
        <div class="ba-chatbot-side-panel rounded-xl b-2">
            <!-- Download Section -->
            <div class="ba-chatbot-card rounded-xl">
                <div class="ba-chatbot-card-header flex items-center gap-4">
                    <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M11.625 15.513q-.175-.063-.325-.213l-3.6-3.6q-.3-.3-.288-.7t.288-.7q.3-.3.713-.312t.712.287L11 12.15V5q0-.425.288-.712T12 4t.713.288T13 5v7.15l1.875-1.875q.3-.3.713-.288t.712.313q.275.3.288.7t-.288.7l-3.6 3.6q-.15.15-.325.213t-.375.062t-.375-.062M6 20q-.825 0-1.412-.587T4 18v-2q0-.425.288-.712T5 15t.713.288T6 16v2h12v-2q0-.425.288-.712T19 15t.713.288T20 16v2q0 .825-.587 1.413T18 20z"/></svg>
                    <h2 class="">Download Conversations</h2>
                </div>
                <div class="ba-panel ba-border-top">
                    <div class="ba-card">
                        <input type="date" id="download_day" name="download_day" class="ba-chatbot-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="ba-card" style="margin-top:8px;">
                        <button id="bac_download" class="ba-chatbot-primary-btn">Download Logs</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php
}

function ai_chatbot_languages_panel() {
    $languages = get_option("ba_languages");
    ?>
<script>
    const theme = localStorage.getItem("ba_theme");
    if(theme === "light"){
        document.body.classList.add("light-theme");
    }
</script>
<div id="ba_chatbot_main" class="ba-chatbot-main ba-chatbot-admin-wrap">
    <!-- Page Header -->
    <div class="ba-chatbot-page-header just-between">
        <div class="flex row just-center items-center gap-4">
            <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m13 19l3.5-9l3.5 9m-6.125-2h5.25M3 7h7m0 0h2m-2 0c0 1.63-.793 3.926-2.239 5.655M7.5 6.818V5m.261 7.655C6.79 13.82 5.521 14.725 4 15m3.761-2.345L5 10m2.761 2.655L10.2 15"/></svg>
            <h1>Languages</h1>
        </div>
        <label class="ba-theme-toggle">
            <input type="checkbox" id="themeToggle">
            <span class="ba-theme-slider">
                <span class="ba-theme-icon sun">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 19a1 1 0 0 1 .993.883L13 20v1a1 1 0 0 1-1.993.117L11 21v-1a1 1 0 0 1 1-1m6.313-2.09l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7a1 1 0 0 1 1.218-1.567zm-11.306.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M4 11a1 1 0 0 1 .117 1.993L4 13H3a1 1 0 0 1-.117-1.993L3 11zm17 0a1 1 0 0 1 .117 1.993L21 13h-1a1 1 0 0 1-.117-1.993L20 11zM6.213 4.81l.094.083l.7.7a1 1 0 0 1-1.32 1.497l-.094-.083l-.7-.7A1 1 0 0 1 6.11 4.74zm12.894.083a1 1 0 0 1 .083 1.32l-.083.094l-.7.7a1 1 0 0 1-1.497-1.32l.083-.094l.7-.7a1 1 0 0 1 1.414 0M12 2a1 1 0 0 1 .993.883L13 3v1a1 1 0 0 1-1.993.117L11 4V3a1 1 0 0 1 1-1m0 5a5 5 0 1 1-4.995 5.217L7 12l.005-.217A5 5 0 0 1 12 7"/></svg>
                </span>
                <span class="ba-theme-icon moon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 1.992a10 10 0 1 0 9.236 13.838c.341-.82-.476-1.644-1.298-1.31a6.5 6.5 0 0 1-6.864-10.787l.077-.08c.551-.63.113-1.653-.758-1.653h-.266l-.068-.006z"/></svg>
                </span>
            </span>
        </label>
    </div>

    <!-- Languages List -->
    <div class="ba-chatbot-main-grid w-full">
        <div class="ba-chatbot-left-col w-full flex col gap-4 items-start">
            <?php foreach ($languages as $name => $data) : ?>
                <div class="ba-chatbot-language ba-chatbot-card rounded-xl b-2 w-full">
                    <div class="flew w-full p-0">
                        <div class="ba-chatbot-card-header flex items-center just-between gap-4 ba-collapsible-content-header" onclick="toggleLanguageSection(this)">
                            <div class="flex items-center gap-4">
                                <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M8.125 21.213q-1.825-.788-3.187-2.15t-2.15-3.188T2 11.988t.788-3.875t2.15-3.175t3.187-2.15T12.013 2t3.875.788t3.175 2.15t2.15 3.175t.787 3.875t-.787 3.887t-2.15 3.188t-3.175 2.15t-3.875.787t-3.888-.787M12 19.95q.65-.9 1.125-1.875T13.9 16h-3.8q.3 1.1.775 2.075T12 19.95m-2.6-.4q-.45-.825-.787-1.713T8.05 16H5.1q.725 1.25 1.813 2.175T9.4 19.55m5.2 0q1.4-.45 2.488-1.375T18.9 16h-2.95q-.225.95-.562 1.838T14.6 19.55M4.25 14h3.4q-.075-.5-.112-.987T7.5 12t.038-1.012T7.65 10h-3.4q-.125.5-.187.988T4 12t.063 1.013t.187.987m5.4 0h4.7q.075-.5.113-.987T14.5 12t-.038-1.012T14.35 10h-4.7q-.075.5-.112.988T9.5 12t.038 1.013t.112.987m6.7 0h3.4q.125-.5.188-.987T20 12t-.062-1.012T19.75 10h-3.4q.075.5.113.988T16.5 12t-.038 1.013t-.112.987m-.4-6h2.95q-.725-1.25-1.812-2.175T14.6 4.45q.45.825.788 1.713T15.95 8M10.1 8h3.8q-.3-1.1-.775-2.075T12 4.05q-.65.9-1.125 1.875T10.1 8m-5 0h2.95q.225-.95.563-1.838T9.4 4.45Q8 4.9 6.912 5.825T5.1 8"/></svg>
                                <input type="text" class="ba-chatbot-input ba-h2" placeholder="Language name" value=<?= $name ?>>
                                <h2><?= $name ?></h2>
                            </div>
                            <button class="b-0 bg-hidden" onclick="deleteLanguage(this)">
                                <svg class="text-red-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 9v10H8V9zm-1.5-6h-5l-1 1H5v2h14V4h-3.5zM18 7H6v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2z"/></svg>
                            </button>
                        </div>
                        <div class="flex row h-full ba-collapsible-content ba-border-top collapsed">
                            <div class="flex col w-full">
                                <div class="ba-panel gap-2" id="ba_api_settings">
                                    <div class="ba-card flex col gap-2 ba-header-text">
                                        <label class="gap-2 flex col">
                                            Header Text
                                            <input type="text" class="ba-chatbot-input" placeholder="Need help? Chat with" value="<?= isset($data['header']) ? htmlspecialchars($data['header'], ENT_QUOTES) : '' ?>">
                                        </label>
                                    </div>

                                    <div class="ba-card flex col gap-2 ba-bot-name">
                                        <label class="gap-2 flex col">
                                            Bot Name
                                            <input type="text" class="ba-chatbot-input" placeholder="Chatbot name" value="<?= isset($data['name']) ? htmlspecialchars($data['name'], ENT_QUOTES) : '' ?>">
                                        </label>
                                    </div>

                                    <div class="ba-card flex col gap-2 ba-user-label">
                                        <label class="gap-2 flex col">
                                            User Label
                                            <input type="text" class="ba-chatbot-input" placeholder="E.g. 'You', 'U', 'Je'" value="<?= isset($data['label']) ? htmlspecialchars($data['label'], ENT_QUOTES) : '' ?>">
                                        </label>
                                    </div>

                                    <div class="ba-card flex col gap-2 ba-bot-intro">
                                        <label class="gap-2 flex col">
                                            Intro Message
                                            <textarea class="ba-chatbot-input" rows="4" placeholder="E.g. 'Hello! how can i help you?'"><?= isset($data["intro"]) ? $data["intro"] : ""?></textarea>
                                        </label>   
                                    </div>

                                    <div class="ba-card flex col gap-2 ba-placeholder">
                                        <label class="gap-2 flex col">
                                            Placeholder Message
                                            <input type="text" class="ba-chatbot-input" placeholder="E.g. 'Write your question'" value="<?= isset($data['placeholder']) ? htmlspecialchars($data['placeholder'], ENT_QUOTES) : '' ?>">
                                        </label>   
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>  
                </div>
            <?php endforeach; ?>
            <div id="ba_add_language" class="flex w-full just-center items-center ba-add-language cursor-ptr">
                <h1>+</h1>
            </div>
            <div class="flex row just-between items-center">
                <button class="ba-chatbot-primary-btn text-white" id="ba_chatbot_save_btn">
                    Save Language
                </button>
                <div id="notifications" class="notifications-container">
                    <div class="loading notification" style="display:none">
                        <svg class="ba-icon" viewBox="0 0 24 24">
                            <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                            <circle class="ba-icon-loader" cx="12" cy="12" r="9"/>
                        </svg>
                    </div>

                    <div class="success notification" style="display:none">
                        <svg class="ba-icon" viewBox="0 0 24 24">
                            <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                            <path class="ba-icon-success" d="M7 12l3 3 6-6"/>
                        </svg>
                    </div>

                    <div class="fail notification" style="display:none">
                        <svg class="ba-icon" viewBox="0 0 24 24">
                            <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                            <path class="ba-icon-error" d="M8 8l8 8M16 8l-8 8"/>
                        </svg>
                    </div>

                    <div class="warning notification" style="display:none">
                        <svg class="ba-icon" viewBox="0 0 24 24">
                            <circle class="ba-icon-bg" cx="12" cy="12" r="9"/>
                            <path class="ba-icon-warning" d="M12 7v6"/>
                            <circle class="ba-icon-warning-dot" cx="12" cy="16" r="1"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="ba-chatbot-right-col-wide rounded-xl b-2 flex items-end">
            <div class="ba-chatbot-card-header w-full flex items-center gap-4 ba-border-bottom">
                <svg class="text-blue-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 48 48"><g fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="4"><path d="M24 36c11.046 0 20-12 20-12s-8.954-12-20-12S4 24 4 24s8.954 12 20 12Z"/><path d="M24 29a5 5 0 1 0 0-10a5 5 0 0 0 0 10Z"/></g></svg>
                <h2>Preview</h2>
            </div>
            <?php
                $pfp_img_url        = get_option('ba_bot_icon_url');
                $widget_color       = get_option('ba_bot_chat_color');
                $open_widget        = get_option('ba_bot_open');
                $chatbot_name       = get_option('ba_bot_name');
                $intro_message      = get_option('ba_bot_intro_message');

                $user_label = "You";
                $message_placeholder = "Write your question...";
            ?>
            <style>
                :root {
                    --chat-color: <?= esc_attr($widget_color) ?>;
                    --chat-color-rgb: <?= esc_attr(hex_to_rgb($widget_color)) ?>;
                    --chat-color-light: <?= esc_attr(lighten($widget_color, 15)) ?>;
                    --chat-color-border: <?= esc_attr(lighten($widget_color, 30)) ?>;
                    --chat-color-border-top: <?= esc_attr(lighten($widget_color, 50)) ?>;
                    --chat-color-background: <?= esc_attr(lighten($widget_color, 90)) ?>;
                    --chat-color-dark: <?= esc_attr(darken($widget_color, 15)) ?>;
                    --chat-color-text-dark: <?= esc_attr(darken($widget_color, 50)) ?>;
                }

                #ai-chatbot-widget-button-content-display {
                    position: relative;
                    right: 0px;
                    bottom: 0px;
                    z-index: 9999;
                    display: flex;
                    flex-direction: row;
                    padding-right: 20px;
                    padding-top: 20px;
                    padding-bottom: 20px;
                }

                #ai-chatbot-widget-window-display {
                    padding-right: 0;
                    position: relative;
                    width: 360px;
                    max-width: calc(100vw - 40px);
                    background: #fff;
                    background-color: var(--chat-color);
                    border-radius: 16px;
                    box-shadow: 0 8px 32px rgba(var(--chat-color-rgb),0.22);
                    z-index: 9998;
                    overflow: hidden;
                    transform: translateY(20px) scale(0.96);
                    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
                    pointer-events: none;
                }

                #ai-chatbot-widget-button-message-display {
                    position: relative;
                    background-color: var(--chat-color);
                    color: white;
                    margin-top: 10px;
                    margin-right: 10px;
                    padding: 8px 14px;
                    border-radius: 20px;
                    font-size: 15px;
                    font-weight: 600;
                    white-space: nowrap;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
                    transition: transform 0.18s ease !important;
                    cursor: pointer;
                    height: 40px;
                }

                #ai-chatbot-widget-button-message-display:hover {
                    transform: scale(1.05);
                }
            </style>
            <div id="ai-chatbot-widget-window-display" class="ai-chatbot-widget-open">
                <div id="ai-chatbot-widget-header">
                    <img src="<?= $pfp_img_url ?>" alt="<?= $chatbot_name ?> Profile" />
                    <h2 class="text-white">Need help? Chat with <?=ucfirst($chatbot_name)?></h2>
                    <svg id="ai-chatbot-widget-header-close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-dasharray="12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l7 7M12 12l-7 -7M12 12l-7 7M12 12l7 -7">
                            <animate fill="freeze" attributeName="stroke-dashoffset" dur="0.4s" values="12;0"/>
                        </path>
                    </svg>
                </div>

                <div id="ai-chatbot-widget-messages" class="text-black">
                    <div class="ai-chatbot-message ai-chatbot-bot-message">
                        <strong><?=ucfirst($chatbot_name)?>:</strong> <?=$intro_message?>
                    </div>
                    <div class="ai-chatbot-message ai-chatbot-user-message">
                        <strong><?=ucfirst($user_label)?>:</strong> Your question
                    </div>
                </div>

                <form id="ai-chatbot-widget-form" autocomplete="off">
                    <input id="ai-chatbot-widget-input" class="bg-white" type="text" placeholder="<?=$message_placeholder?>" required />
                    <button id="ai-chatbot-widget-send" type="submit" aria-label="Verstuur">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </form>
            </div>

            <div id="ai-chatbot-widget-button-content-display">
                <div id="ai-chatbot-widget-button-message-display">
                    Ask <?= $chatbot_name ?>
                </div>
                <button id="ai-chatbot-widget-button" title="Chatbot" aria-label="Open chatbot">
                    <img src="<?= $pfp_img_url ?>" alt="Chat Icon" />
                </button>
            </div>
        </div>
    </div>
</div>
    <?php
}