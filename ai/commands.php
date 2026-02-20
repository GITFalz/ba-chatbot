<?php

// load autoloader for external libraries
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

function ai_chatbot_process_uploaded_file($filepath, $type) 
{
    if (!file_exists($filepath))
    {
        error_log("File does not exist: $filepath");
        return [];
    }

    $text = '';
    if ($type === 'text/plain' || $type === 'text/markdown' || $type === 'text/x-markdown') 
    {
        error_log("File is a text or markdown file");
        $text = file_get_contents($filepath);
    } elseif ($type === 'application/pdf') {
        error_log("File is a pdf file");
        $parser = new Parser();
        $pdf = $parser->parseFile($filepath);
        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText();
        }

        // if text is empty, try custom parser
        if (trim($text) === '') {
            $text = parsePdf($filepath);
            error_log($text);
        }
    } elseif ($type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $type === 'application/msword') {
        error_log("File is a docx file");
        $phpWord = IOFactory::load($filepath);
        foreach ($phpWord->getSections() as $section) {
            $elements = $section->getElements();
            foreach ($elements as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
    }
    $data = [];
    if ($text) {
        $chunks = ai_chatbot_chunk_text($text);
        foreach ($chunks as $chunk) {
            $embedding = ai_chatbot_send_to_openai_embeddings($chunk);
            if ($embedding) {
                $data[] = [
                    'text' => $chunk,
                    'embedding' => $embedding,
                ];
            }
        }
    }

    if ($text == '' || empty($data))
    {
        return [
            'success' => false,
            'message' => 'File is empty or unable to parse text',
            'data' => []
        ];
    }

    return [
        'success' => true,
        'message' => "",
        'data' => $data
    ];
}

function ai_chatbot_chunk_text($text, $size = 300) {
    $chunks = [];
    $len = strlen($text);
    for ($i = 0; $i < $len; $i += $size) {
        $chunks[] = substr($text, $i, $size);
    }
    return $chunks;
}

function ai_chatbot_send_to_openai_embeddings($chunk) {

    $gpt_api = ba_decrypt(get_option("ba_gpt_api_key"));
    $api_key = $gpt_api;

    $url = 'https://api.openai.com/v1/embeddings';
    $data = [
        'input' => $chunk,
        'model' => 'text-embedding-3-large',
    ];

    $body = json_encode($data);
    if ($body === false) {
        error_log('JSON encode failed: ' . json_last_error_msg() . " " . $chunk);
        return false;
    }

    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'method'      => 'POST',
        'data_format' => 'body',
    ];
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        error_log('OpenAI API request failed: ' . $response->get_error_message());
        return null;
    } 
    else 
    {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        if (isset($result['data'][0]['embedding'])) {
            return $result['data'][0]['embedding'];
        } else {
            error_log('OpenAI API response error: ' . $body);
            return null;
        }
    }
}

function ai_chatbot_send_to_qdrant($vector, $chunk, $document_id) {

    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $qdrant_collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $qdrant_collection . '/points';
    $data = [
        'points' => [[ // 'valid values are either an unsigned integer or a UUID'
            'id' => guidv4(),
            'vector' => $vector,
            'payload' => [
                'document_id' => $document_id,
                'text' => $chunk
            ]
        ]]
    ];

    $args = [
        'body'        => json_encode($data),
        'headers'     => [
            'Content-Type'  => 'application/json',
            'api-key'       => $qdrant_api,
        ],
        'method'      => 'PUT'
    ];
    $response = wp_remote_post($url, $args);
    $res = json_decode(wp_remote_retrieve_body($response), true);
    return $res;
}

function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ai_chatbot_query_qdrant($query_vector, $top_k = 10) {

    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $qdrant_collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $qdrant_collection . '/points/search';
    $body = [
        'vector' => $query_vector,
        'top'    => $top_k,
        'with_payload' => true,
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key'      => $qdrant_api,
        ],
        'body' => json_encode($body),
    ]);
    if (is_wp_error($response)) {
        error_log('[AI Chatbot] Qdrant query failed: ' . $response->get_error_message());
        return null;
    }
    $res = json_decode(wp_remote_retrieve_body($response), true);
    return $res;
}

function ai_chatbot_ask_llm($question, $context_chunks) {

    $gpt_api = ba_decrypt(get_option("ba_gpt_api_key"));
    $api_key = $gpt_api;

    $context_text = implode("\n---\n", $context_chunks);

    $speech_instruction = "";

    $speech_type = get_option("ba_bot_speech");
    if ($speech_type == "friendly")
    {
        $speech_instruction = "The people asking questions are most likely average people so use; 'je' and 'jouw' instead of 'u' and 'uw' in Dutch to appear more friendly.";
    }
    else
    {
        $speech_instruction = "The people asking questions are most likely elderly so use; 'u' and 'uw' instead of 'je' and 'jouw' in Dutch to appear more respectful.";
    }

    $system_prompt = "
        You are the official virtual assistant of this company. 
        Always answer as a representative of this company, using the information provided in the context. 
        Respond in the same language as the user's question. 
        Use a friendly and helpful tone.
        If the answer is not in the context, politely say you don't know. 
        Do not refer to 'the company' in the third person; use 'we', 'our', or 'us' as appropriate.";

    $system_prompt .= $speech_instruction;

    $messages = [
        [
            "role" => "system",
            "content" => $system_prompt
        ],
        [
            "role" => "user",
            "content" => "Context:\n$context_text\n\nQuestion:\n$question"
        ]
    ];

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.2
        ])
    ]);

    if (is_wp_error($response)) 
        return null;

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['choices'][0]['message']['content'] ?? null;
}


function ai_chatbot_delete_qdrant_document($document_id) {

    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $qdrant_collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $qdrant_collection . '/points/delete';
    $body = [
        'filter' => [
            'must' => [
                [
                    'key'   => 'document_id',
                    'match' => ['value' => $document_id],
                ]
            ]
        ]
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key'      => $qdrant_api,
        ],
        'body' => json_encode($body),
    ]);

    if (is_wp_error($response)) {
        error_log('Qdrant delete failed: ' . $response->get_error_message());
        return [
            'success' => false,
            'message' => "Failed to connect to Qdrant: $error_message",
            'data'    => null,
        ];
    }

    $res = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!isset($res['status']) || $res['status'] !== 'ok') {
        $msg = isset($res['status']) ? "Qdrant responded: {$res['status']}" : "Unexpected response from Qdrant";
        return [
            'success' => false,
            'message' => $msg,
            'data'    => $res,
        ];
    }

    return [
        'success' => true,
        'message' => "Document deleted successfully",
        'data'    => $res,
    ];
}