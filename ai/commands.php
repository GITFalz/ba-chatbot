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
    error_log($url);
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
            'success' => true,
            'data' => []
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

function ai_chatbot_ask_llm($question, $context_chunks) {

    $gpt_api = ba_decrypt(get_option("ba_gpt_api_key"));
    $api_key = $gpt_api;

    $email = get_option("ba_bot_email");
    $phone = get_option("ba_bot_phone");

    $contact_text = " You must only answer using the provided context.
If the answer cannot be found in the context, you must say that you do not know.
Do not guess, invent, or assume information that is not explicitly present in the context.";

    if ($email || $phone) {
        if ($email && $phone) {
            $contact_text .= " You can also suggest that the user contact us at $email or call us at $phone for further assistance.";
        } elseif ($email) {
            $contact_text .= " You can also suggest that the user contact us at $email for further assistance.";
        } elseif ($phone) {
            $contact_text .= " You can also suggest that the user call us at $phone for further assistance.";
        }
    }

    $context_text = implode("\n---\n", $context_chunks);
    
    $speech_instruction = "";

    $speech_type = get_option("ba_bot_speech");
    if ($speech_type === "friendly") {
        $speech_instruction = " Answer in a friendly, casual manner. Use informal pronouns where applicable (like 'je/jouw' in Dutch, 'tu' in French, 'du' in German).";
    } else {
        $speech_instruction = " Answer in a formal, respectful manner. Use formal pronouns where applicable (like 'u/uw' in Dutch, 'vous' in French, 'Sie' in German).";
    }

    $system_prompt = "
        You are the official virtual assistant of this company. 
        Always respond as a company representative. 
        Answer in the same language as the user's question. 
        Refer to the company as 'we', 'our', or 'us' never in the third person.";

    $system_prompt .= $contact_text;
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