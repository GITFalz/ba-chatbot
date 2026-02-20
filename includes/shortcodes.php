<?php

function lighten($hex, $percent) {
    $hex = str_replace('#','',$hex);
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));

    $r = min(255, $r + ($percent/100)*255);
    $g = min(255, $g + ($percent/100)*255);
    $b = min(255, $b + ($percent/100)*255);

    return sprintf("#%02x%02x%02x", round($r), round($g), round($b));
}

function darken($hex, $percent) {
    $hex = str_replace('#','',$hex);
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));

    $r = max(0, $r - ($percent/100)*255);
    $g = max(0, $g - ($percent/100)*255);
    $b = max(0, $b - ($percent/100)*255);

    return sprintf("#%02x%02x%02x", round($r), round($g), round($b));
}

function hex_to_rgb($hex) {
    $hex = str_replace('#','',$hex);
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    return "$r,$g,$b";
}

// Shortcode for frontend chatbot widget (Dutch, orange style, polished icons)
add_shortcode('Chatbot', function() {
    $pfp_img_url = '';
    $ext = get_option('ba_bot_icon_ext');
    if ($ext)
    {
        $pfp_img_url = AI_CHATBOT_URL . '/assets/img/profile-picture' . '.' . $ext;
    }

    $widget_color      = get_option('ba_bot_chat_color');
    $open_widget      = get_option('ba_bot_open');
    $chatbot_name      = get_option('ba_bot_name');
    $intro_message      = get_option('ba_bot_intro_message');

    ob_start();
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
    </style>
    <div id="ai-chatbot-widget-button-content" class="<?=$open_widget ? 'ai-chatbot-open' : ''?>">
        <div id="ai-chatbot-widget-button-message">
            Vraag het <?= $chatbot_name ?>
        </div>
        <button id="ai-chatbot-widget-button" title="Chatbot" aria-label="Open chatbot">
            <img src="<?= $pfp_img_url ?>" alt="Chat Icon" />
        </button>
    </div>

    <div id="ai-chatbot-widget-window" class="ai-chatbot-widget-close">
        <div id="ai-chatbot-widget-header">
            <img src="<?= $pfp_img_url ?>" alt="<?= $chatbot_name ?> Profile" />
            Hulp nodig? Chat met <?=ucfirst($chatbot_name)?>
            <svg id="ai-chatbot-widget-header-close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                <path fill="none" stroke="currentColor" stroke-dasharray="12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l7 7M12 12l-7 -7M12 12l-7 7M12 12l7 -7">
                    <animate fill="freeze" attributeName="stroke-dashoffset" dur="0.4s" values="12;0"/>
                </path>
            </svg>
        </div>

        <div id="ai-chatbot-widget-messages">
            <div class="ai-chatbot-message ai-chatbot-bot-message">
                <strong><?=ucfirst($chatbot_name)?>:</strong> <?=$intro_message?>
            </div>
        </div>

        <form id="ai-chatbot-widget-form" autocomplete="off">
            <input id="ai-chatbot-widget-input" type="text" placeholder="Typ <?=(get_option('ba_bot_speech') == 'friendly') ? 'jouw' : 'uw'?> vraag..." required />
            <button id="ai-chatbot-widget-send" type="submit" aria-label="Verstuur">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});