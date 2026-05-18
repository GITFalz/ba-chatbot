<?php

// load autoloader for external libraries
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

function ai_chatbot_process_uploaded_file($filepath, $type) 
{
    if (!file_exists($filepath))
    {
        return [
            "success" => false,
            'message' => 'File does not exist',
        ];
    }

    $text = '';
    if ($type === 'text/plain' || $type === 'text/markdown' || $type === 'text/x-markdown') 
    {
        $text = file_get_contents($filepath);
    } elseif ($type === 'application/pdf') {
        $parser = new Parser();
        $pdf = $parser->parseFile($filepath);
        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText();
        }

        // if text is empty, try custom parser
        if (trim($text) === '') {
            $text = parsePdf($filepath);
        }
    } elseif ($type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $type === 'application/msword') {
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
            $result = ai_chatbot_send_to_openai_embeddings($chunk);
            if ($result['success']) 
            {
                $data[] = [
                    'text' => $chunk,
                    'embedding' => $result['embedding']
                ];
            }
            else
            {
                return $result;
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
        return [
            'success' => false,
            'message' => $response->get_error_message(),
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code < 200 || $status_code >= 300) {
        return [
            'success' => false,
            'message' => 'HTTP Error: ' . $status_code,
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response',
        ];
    }

    if (isset($data['error'])) {
        return [
            'success' => false,
            'message' => $data['error']['message'] ?? 'OpenAI API error',
        ];
    }

    if (!isset($data['data'][0]['embedding']))
    {
        return [
            'success' => false,
            'message' => 'OpenAI response did not contain embedding data'
        ];
    }

    return [
        'success' => true,
        'embedding' => $data['data'][0]['embedding'],
    ];
}

function ai_chatbot_send_to_qdrant($vector, $chunk, $document_id, $type) {

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
                'type' => $type,
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
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => $response->get_error_message(),
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code < 200 || $status_code >= 300) {
        return [
            'success' => false,
            'message' => 'HTTP Error: ' . $status_code,
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response',
        ];
    }

    return [
        'success' => true,
        'data' => $data,
    ];
}


function ai_qdrant_scroll($offset = null) {
    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $collection . '/points/scroll';

    $body = [
        'limit' => 100,
        'with_payload' => ['document_id'],
        'with_vectors' => false,
    ];

    if ($offset !== null) {
        $body['offset'] = $offset;
    }

    $response = wp_remote_post($url, [
        'body' => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key' => $qdrant_api,
        ]
    ]);

    return json_decode(wp_remote_retrieve_body($response), true);
}

function ai_qdrant_scroll_by_type($type, $offset = null) {
    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $collection = get_option("ba_bot_qdrant_collection");
    $url = $qdrant_url . '/collections/' . $collection . '/points/scroll';

    $body = [
        'limit' => 100,
        'with_payload' => true,
        'with_vectors' => false,
        'filter' => [
            'must' => [
                [
                    'key' => 'type',
                    'match' => [
                        'value' => $type
                    ]
                ]
            ]
        ]
    ];

    if ($offset !== null) {
        $body['offset'] = $offset;
    }

    error_log("[QDRANT SCROLL BY TYPE] Querying type: '{$type}'");
    error_log("[QDRANT SCROLL BY TYPE] URL: " . $url);
    error_log("[QDRANT SCROLL BY TYPE] Full body: " . json_encode($body));

    $response = wp_remote_post($url, [
        'body' => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key' => $qdrant_api,
        ]
    ]);

    if (is_wp_error($response)) {
        error_log("[QDRANT SCROLL BY TYPE] WP_Error: " . $response->get_error_message());
        return null;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);

    error_log("[QDRANT SCROLL BY TYPE] HTTP code: " . $http_code);
    error_log("[QDRANT SCROLL BY TYPE] Raw response: " . $raw_body);

    $decoded = json_decode($raw_body, true);
    error_log("[QDRANT SCROLL BY TYPE] Points returned: " . count($decoded['result']['points'] ?? []));

    // Dump the first point raw so we can see the actual payload structure
    if (!empty($decoded['result']['points'])) {
        error_log("[QDRANT SCROLL BY TYPE] First point sample: " . json_encode($decoded['result']['points'][0]));
    }

    return $decoded;
}


function ai_qdrant_set_payload($ids, $type) {
    if (empty($ids)) return;

    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $collection . '/points/payload';

    $body = [
        'payload' => [
            'type' => $type
        ],
        'points' => $ids
    ];

    wp_remote_post($url, [
        'body' => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key' => $qdrant_api,
        ]
    ]);
}

function ai_qdrant_are_pages_valid()
{
    $page_set = ai_chatbot_get_pages_lookup();
    $ai_page_set = ai_chatbot_get_ai_chat_pages_lookup();

    $offset = null;

    do {
        $res = ai_qdrant_scroll_by_type('page', $offset);
        $points = $res['result']['points'] ?? [];

        foreach ($points as $point) {

            $document_id = $point['payload']['document_id'] ?? null;
            if (!$document_id) continue;

            $post_id = (int) str_replace('page_', '', $document_id);

            if (!isset($page_set[$post_id]) || !isset($ai_page_set[$post_id])) {
                return [
                    "success" => false,
                    "post_id" => $post_id,
                    "fail_state" => "" . (isset($page_set[$post_id]) ? "true" : "false") . " " . (isset($ai_page_set[$post_id]) ? "true" : "false")
                ];
            }
        }

        $offset = $res['result']['next_page_offset'] ?? null;

    } while ($offset !== null);

    return [
        "success" => true
    ];
}

function ai_qdrant_delete_points($ids) {
    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $collection = get_option("ba_bot_qdrant_collection");
    $url = $qdrant_url . '/collections/' . $collection . '/points/delete';

    $body = ['points' => $ids];

    $response = wp_remote_post($url, [
        'method'  => 'POST',
        'body'    => json_encode($body),
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key'      => $qdrant_api,
        ]
    ]);

    return [
        "response" => $response
    ];
}



function ai_update_qdrant_type_for($points, $start, $type)
{
    $ids = [];
    foreach ($points as $point) {
        if (str_starts_with($point['payload']['document_id'] ?? "", $start)) {
            $ids[] = $point['id'];
        }
    }

    ai_qdrant_set_payload($ids, $type);
}

function ai_update_qdrant_type() {
    $status = get_option("ba_payload_update_status", "not_started");

    if ($status === "done" || $status === "running") {
        return;
    }

    update_option("ba_payload_update_status", "running");

    $offset = null;

    do {
        $res = ai_qdrant_scroll($offset);

        $points = $res['result']['points'] ?? [];

        ai_update_qdrant_type_for($points, "page_", "page");
        ai_update_qdrant_type_for($points, "file_", "file");

        $offset = $res['result']['next_page_offset'] ?? null;

    } while ($offset !== null);

    ai_chatbot_create_qdrant_type_index();

    update_option("ba_payload_update_status", "done");
}
add_action('wp_ajax_qdrant_update_type', 'ai_update_qdrant_type');

function ai_cleanup_qdrant_pages() {

    $page_set = ai_chatbot_get_pages_lookup();
    $ai_page_set = ai_chatbot_get_ai_chat_pages_lookup();

    $offset = null;
    $to_delete = [];

    do {
        $res = ai_qdrant_scroll_by_type('page', $offset);
        $points = $res['result']['points'] ?? [];

        foreach ($points as $point) {

            $document_id = $point['payload']['document_id'] ?? null;
            if (!$document_id) continue;

            $post_id = (int) str_replace('page_', '', $document_id);

            if (!isset($page_set[$post_id]) || !isset($ai_page_set[$post_id])) {
                $to_delete[] = $point['id'];
            }
        }

        $offset = $res['result']['next_page_offset'] ?? null;

    } while ($offset !== null);

    if (!empty($to_delete)) {
        ai_qdrant_delete_points($to_delete);
    }
}
add_action('wp_ajax_cleanup_qdrant_pages', 'ai_cleanup_qdrant_pages');


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

function ai_chatbot_query_qdrant($query_vector, $top_k = 10) 
{
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
        return [
            'success' => false,
            'message' => $response->get_error_message(),
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code < 200 || $status_code >= 300) {
        return [
            'success' => false,
            'message' => 'HTTP Error: ' . $status_code,
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response',
        ];
    }

    return [
        'success' => true,
        'data' => $data,
    ];
}

function ensure_utf8($string) {
    return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
}

function ai_chatbot_ask_llm($question, $context_chunks) {
    $api_key = ba_decrypt(get_option("ba_gpt_api_key"));
    $email   = get_option("ba_bot_email");
    $phone   = get_option("ba_bot_phone");

    $contact_text = "If the user needs more help, you can suggest contacting us";
    if ($email && $phone) {
        $contact_text .= " at $email or by phone at $phone.";
    } elseif ($email) {
        $contact_text .= " at $email.";
    } elseif ($phone) {
        $contact_text .= " by phone at $phone.";
    } else {
        $contact_text = "";
    }

    $context_text = implode("\n\n---\n\n", $context_chunks);

    $speech_instruction = "";
    $speech_type = get_option("ba_bot_speech");
    if ($speech_type == "friendly") {
        $speech_instruction = "Use an informal, friendly tone appropriate for a general audience.";
    } else {
        $speech_instruction = "Use a formal, respectful tone appropriate for an elderly audience.";
    }

    $system_prompt = "
You are the official virtual assistant of this company.
Answer in a friendly and helpful tone. Always speak as 'we', 'our', or 'us'.

The user message will contain a set of pages from our website (with title, URL and content), followed by the user's question.

Rules for using page content:
- Use the information from these pages to answer the user's question.
- If the question is related to any of the pages (even if not an exact word match), use the relevant information and include a link.
- CRITICAL: Only use URLs that appear VERBATIM in the provided pages. Never modify, guess, or construct a URL yourself.
- CRITICAL: Never use example.com or any placeholder. If you are not certain of the exact URL from the context, omit the link entirely.
- When linking, use this format naturally in your sentence: <a href=\"[EXACT URL FROM CONTEXT]\">[Page title]</a>
- If nothing in the pages is relevant at all, say you don't have that information and offer to help with something else or suggest contacting us.
- CRITICAL: Always respond in the exact same language as the user's question. 
  The language of the source pages must NEVER influence the language of your response.
  Detect the user's language from their question and match it precisely.";

    $system_prompt .= "\n\n" . $contact_text . "\n" . $speech_instruction;

    $messages = [
        [
            "role" => "system",
            "content" => trim($system_prompt)
        ],
        [
            "role" => "user",
            "content" => "Here are the pages from our website:\n\n" . $context_text . "\n\nIMPORTANT: The question below is written in a specific language. You MUST reply in that exact same language, regardless of the language of the pages above.\n\nQuestion: " . $question
        ]
    ];

    foreach ($messages as &$msg) {
        $msg['content'] = ensure_utf8($msg['content']);
    }

    $body = json_encode([
        'model'       => 'gpt-4o-mini',
        'messages'    => $messages,
        'temperature' => 0.3
    ]);

    // Recommended: Switch to a better model (much better at following instructions)
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => $body,
        'timeout' => 25
    ]);

    if (is_wp_error($response)) {
        error_log('OpenAI Error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? null;
}

function ai_chatbot_create_qdrant_payload_index() {
    $qdrant_url   = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api   = ba_decrypt(get_option("ba_qdrant_api_key"));
    $qdrant_collection = get_option("ba_bot_qdrant_collection");

    $url = $qdrant_url . '/collections/' . $qdrant_collection . '/index';

    $body = [
        'field_name' => 'document_id',
        'field_schema' => 'keyword',
    ];

    $response = wp_remote_post($url, [
        'method'  => 'PUT',
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key'      => $qdrant_api,
        ],
        'body' => json_encode($body),
    ]);

    return json_decode(wp_remote_retrieve_body($response), true);
}

function ai_chatbot_create_qdrant_type_index() {
    $qdrant_url = ba_decrypt(get_option("ba_qdrant_url"));
    $qdrant_api = ba_decrypt(get_option("ba_qdrant_api_key"));
    $collection = get_option("ba_bot_qdrant_collection");
    $url = $qdrant_url . '/collections/' . $collection . '/index';

    $response = wp_remote_request($url, [
        'method' => 'PUT',
        'body' => json_encode([
            'field_name'   => 'type',
            'field_schema' => 'keyword'
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key'      => $qdrant_api,
        ]
    ]);

    return json_decode(wp_remote_retrieve_body($response), true);
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
        $error_message = $response->get_error_message();
        return [
            'success' => false,
            'message' => "Failed to connect to Qdrant: " . $error_message,
            'data'    => null,
        ];
    }

    $res_body = wp_remote_retrieve_body($response);
    $res = json_decode($res_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'Invalid JSON response from Qdrant',
            'data'    => null,
        ];
    }

    if (!isset($res['status']) || $res['status'] !== 'ok') {

        $short_status = isset($res['status']) 
            ? 'Status: ' . $res['status'] 
            : 'Missing status field';

        return [
            'success' => false,
            'message' => 'Qdrant request failed. ' . $short_status,
            'data'    => null,
        ];
    }

    return [
        'success' => true,
        'message' => "Document deleted successfully",
        'data'    => $res,
    ];
}