<?php

function ba_encrypt($data) 
{
    return openssl_encrypt($data, 'aes-256-cbc', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
}

function ba_decrypt($data) 
{
    return openssl_decrypt($data, 'aes-256-cbc', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
}

function ba_get_qdrant_api_key() 
{
    $stored = get_option('ba_qdrant_api_key');
    if (!$stored) return '';

    return ba_decrypt($stored);
}

function ba_get_openai_api_key() 
{
    $stored = get_option('ba_openai_api_key');
    if (!$stored) return '';

    return ba_decrypt($stored);
}

function ai_chatbot_run_updates($installed)
{
    
}