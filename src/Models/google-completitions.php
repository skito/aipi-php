<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class Google_Completions extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'google-gemini-1.5-flash',
        'google-gemini-1.5-flash-8b',
        'google-gemini-1.5-pro',
        'google-gemini-2.0-flash',
        'google-veo-2.0-generate-001',
        'google-imagen-3.0-generate-002',
        'google-gemini-2.0-flash-lite',
        'google-gemini-2.0-flash-preview-image-generation',
        'google-gemini-2.0-flash',
        'google-gemini-2.5-pro-preview-tts',
        'google-gemini-2.5-pro-preview-05-06',
        'google-gemini-2.5-flash-preview-tts',
        'google-gemini-2.5-flash-preview-native-audio-dialog',
        'google-gemini-2.5-flash-exp-native-audio-thinking-dialog',
        'google-gemini-2.5-flash-preview-05-20',
        'google-gemini-2.5-pro',
        'google-gemini-2.5-flash'
    ];
    

    public function __construct($name = 'google-gemini-1.5-flash')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'google-gemini-1.5-flash';
    }

    public function GetName()
    {
        return $this->_name;
    }
    
    public function GetLastError()
    {
        return $this->_lastError;
    }

    public function Call($apikey, $messages, $tools = [], $options=[], &$tokens=null)
    {
        $options = (object)$options;
        $tokens = ['input' => 0, 'output' => 0];
        $this->_lastError = '';

        // Prepare the request data
        $modelName = explode('google-', $this->_name)[1];
        
        // Extract system message if present
        $systemInstruction = '';
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if ($msg->role === MessageRole::SYSTEM) {
                $systemInstruction = $msg->content;
            } else {
                $filteredMessages[] = $msg;
            }
        }

        // Convert old tool calls/responses to text, keep only latest as function structures
        // This reduces complexity for Google's API while preserving conversation context
        $processedMessages = [];
        $lastToolCallIndex = -1;
        $lastToolResponseIndex = -1;
        
        // Find the last tool call and response indices
        for ($i = count($filteredMessages) - 1; $i >= 0; $i--) {
            if ($filteredMessages[$i]->role === MessageRole::TOOL && $lastToolCallIndex === -1) {
                $lastToolCallIndex = $i;
            }
            if ($filteredMessages[$i]->role === MessageRole::RESULT && $lastToolResponseIndex === -1) {
                $lastToolResponseIndex = $i;
            }
            // Stop once we found both (if they exist)
            if ($lastToolCallIndex !== -1 && $lastToolResponseIndex !== -1) {
                break;
            }
        }
        
        // Process each message
        for ($i = 0; $i < count($filteredMessages); $i++) {
            $msg = $filteredMessages[$i];
            $isLatestTool = ($i === $lastToolCallIndex || $i === $lastToolResponseIndex);
            
            if (!$isLatestTool && ($msg->role === MessageRole::TOOL || $msg->role === MessageRole::RESULT)) {
                // Convert old tool calls/responses to regular text messages
                $textContent = '';
                
                if ($msg->role === MessageRole::TOOL) {
                    $calls = json_decode($msg->content, true);
                    if (isset($calls['calls']) && is_array($calls['calls'])) {
                        $callNames = array_map(function($call) { return $call['name']; }, $calls['calls']);
                        $textContent = '[Tool calls: ' . implode(', ', $callNames) . ']';
                    }
                } elseif ($msg->role === MessageRole::RESULT) {
                    $responseData = json_decode($msg->content, true);
                    if (isset($responseData['tool_result']['tool_name'])) {
                        $textContent = '[Tool response from: ' . $responseData['tool_result']['tool_name'] . ']';
                    }
                }
                
                // Create a new text message
                $processedMessages[] = (object)[
                    'role' => MessageRole::ASSISTANT,
                    'content' => $textContent,
                    'attributes' => ['type' => MessageType::TEXT, 'role' => MessageRole::ASSISTANT]
                ];
            } else {
                // Keep the message as-is (including latest tool calls/responses)
                $processedMessages[] = $msg;
            }
        }
        
        $filteredMessages = $processedMessages;

        $data = [
            'model' => $modelName,
            'contents' => array_map(function($msg) {
                $role = 'user';
                if ($msg->role === MessageRole::ASSISTANT)
                    $role = 'model';
                elseif ($msg->role === MessageRole::TOOL)
                    $role = 'model';
                elseif ($msg->role === MessageRole::RESULT)
                    $role = 'model';

                $parts = [];

                // Handle different message roles explicitly
                if ($msg->role === MessageRole::RESULT) {
                    // Handle tool responses
                    $responseData = json_decode($msg->content, true);
                    if (isset($responseData['tool_result'])) {
                        // Single tool result
                        $parts[] = [
                            'functionResponse' => [
                                'name' => $responseData['tool_result']['tool_name'],
                                'response' => ['result' => $responseData['tool_result']['result']]
                            ]
                        ];
                    }
                } elseif ($msg->role === MessageRole::TOOL) {
                    // Handle function calls only
                    $calls = json_decode($msg->content, true);
                    if (isset($calls['calls']) && is_array($calls['calls'])) {
                        foreach ($calls['calls'] as $call) {
                            // Get the args as they are from the decoded JSON
                            $args = $call['args'] ?? [];
                            
                            // Ensure it's an array (should already be from json_decode with true)
                            if (!is_array($args)) {
                                $args = [];
                            }
                            
                            // The `args` field must be a JSON object for the Google API.
                            // A PHP empty array `[]` becomes a JSON array `[]` when encoded, which is wrong for `args`.
                            // Casting to an object ensures it becomes `{}` for an empty array.
                            $args = (object)$args;
                            
                            $parts[] = [
                                'functionCall' => [
                                    'name' => $call['name'],
                                    'args' => $args
                                ]
                            ];
                        }
                    }
                } elseif ($msg->attributes['type'] === MessageType::FILE && 
                    in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                    // Handle file uploads
                    $mediaType = $msg->attributes['media_type'] ?? 'image/jpeg';
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mediaType,
                            'data' => base64_encode($msg->content)
                        ]
                    ];
                } else {
                    // Handle regular text content for all other cases
                    $parts[] = [
                        'text' => $msg->content
                    ];
                }

                return [
                    'role' => $role,
                    'parts' => $parts
                ];
            }, $filteredMessages)
        ];

        // Add system instruction if present
        if (!empty($systemInstruction)) {
            $data['systemInstruction'] = [
                'parts' => [
                    [
                        'text' => $systemInstruction
                    ]
                ]
            ];
        }

        // Add additional data if provided
        $additionalData = [];
        $supportedAddonData = [
            'candidateCount', 'maxOutputTokens', 'stopSequences', 
            'temperature', 'topP', 'topK', 'safetySettings', 'response_mime_type'
        ];

        foreach ($supportedAddonData as $googleKey) {
            if (isset($options->$googleKey)) {
                $additionalData[$googleKey] = $options->$googleKey;
            }
        }

        $openAIOptionLinks = [
            'candidateCount' => 'n',
            'maxOutputTokens' => 'max_tokens',
            'stopSequences' => 'stop',
            'temperature' => 'temperature',
            'topP' => 'top_p',
            'topK' => 'top_k',
            'safetySettings' => 'safety_settings'
        ];
        foreach ($openAIOptionLinks as $googleKey => $openaiKey) {
            if (isset($options->$openaiKey) && !isset($additionalData[$googleKey])) {
                $additionalData[$googleKey] = $options->$openaiKey;
            }
        }
        
        // Special handling for generation config
        $generationConfig = [];
        foreach (['temperature', 'topP', 'topK', 'maxOutputTokens', 'stopSequences', 'response_mime_type'] as $key) {
            if (isset($additionalData[$key])) {
                $generationConfig[$key] = $additionalData[$key];
                unset($additionalData[$key]);
            }
        }

        if (!empty($generationConfig)) {
            $data['generationConfig'] = $generationConfig;
        }

        $data = array_merge($data, $additionalData);

        // Add tools if provided and supported
        if (!empty($tools)) {
            $toolsArray = [];
            foreach ($tools as $tool) {
                if ($tool instanceof \AIpi\Tools\FunctionCall) {
                    $parameters = [];
                    foreach ($tool->properties as $name => $type) {
                        $parameters[$name] = [
                            'type' => $type,
                            'description' => $tool->property_descriptions[$name] ?? ''
                        ];
                    }

                    $toolsArray[] = [
                        'name' => $tool->name,
                        'parameters' => [
                            'type' => 'OBJECT',
                            'description' => $tool->description,
                            'properties' => (object)$parameters,
                            'required' => $tool->property_required
                        ]
                    ];
                }
            }
            if (!empty($toolsArray)) {
                $data['tools'] = [
                    'function_declarations' => $toolsArray
                ];
            }
        }

        $apiVersion = $options->apiVersion ?? 'v1beta';
        
        // Add debug output to see what we're sending
        if (isset($options->debug) && $options->debug) {
            error_log("DEBUG: Sending to Google API:");
            error_log(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        
        // Initialize cURL session
        $ch = curl_init('https://generativelanguage.googleapis.com/'.$apiVersion.'/models/'.$modelName.':generateContent');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-goog-api-key: ' . $apikey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $this->_lastError = 'Google API request failed with status ' . $httpCode . ': ' . $response;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Parse the response
        $result = json_decode($response);
        if (!$result) {
            $this->_lastError = 'Invalid JSON response from Google API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Check for API-level errors first
        if (isset($result->error)) {
            $this->_lastError = 'Google API error: ' . ($result->error->message ?? 'Unknown error');
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Check if we have candidates
        if (!isset($result->candidates) || empty($result->candidates)) {
            $this->_lastError = 'No candidates in Google API response';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        $candidate = $result->candidates[0];
        
        // Check for finish reason errors
        if (isset($candidate->finishReason)) {
            $finishReason = $candidate->finishReason;
            if (in_array($finishReason, ['SAFETY', 'RECITATION', 'MALFORMED_FUNCTION_CALL', 'OTHER'])) {
                $this->_lastError = 'Google API response blocked or malformed. Reason: ' . $finishReason;
                if ($options->throwOnError ?? true) {
                    throw new \Exception($this->_lastError);
                }
                return null;
            }
        }

        // Check if content exists
        if (!isset($candidate->content)) {
            $this->_lastError = 'No content in Google API response';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token counts if available
        if (isset($result->usageMetadata)) {
            $tokens['input'] = $result->usageMetadata->promptTokenCount ?? 0;
            $tokens['output'] = $result->usageMetadata->candidatesTokenCount ?? 0;
        }

        $content = $candidate->content;
        $role = MessageRole::ASSISTANT;

        // Handle function calls and responses
        $functionCalls = [];
        $functionResponses = [];
        
        // Check if parts exist before iterating
        if (isset($content->parts) && is_array($content->parts)) {
            foreach ($content->parts as $part) {
                if (isset($part->functionCall)) {
                    $functionCalls[] = [
                        'name' => $part->functionCall->name,
                        'args' => $part->functionCall->args ?? new \stdClass()
                    ];
                }
                if (isset($part->functionResponse)) {
                    $functionResponses[] = [
                        'name' => $part->functionResponse->name,
                        'response' => $part->functionResponse->response
                    ];
                }
            }
        }

        if (!empty($functionCalls)) {
            $role = MessageRole::TOOL;
            $content = json_encode([
                'calls' => $functionCalls
            ], JSON_UNESCAPED_UNICODE);
        } elseif (!empty($functionResponses)) {
            $role = MessageRole::RESULT;
            $content = json_encode([
                'tool_result' => [
                    'tool_name' => $functionResponses[0]['name'],
                    'result' => $functionResponses[0]['response']['result'] ?? $functionResponses[0]['response']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // Regular text response - check if parts exist before accessing
            $textContent = '';
            if (isset($content->parts) && is_array($content->parts) && !empty($content->parts)) {
                // Look for text content in parts
                foreach ($content->parts as $part) {
                    if (isset($part->text)) {
                        $textContent .= $part->text;
                    }
                }
            }
            $content = $textContent;
        }

        return new Message($content, $role);
    }

    public static function GetSupported()
    {
        return self::$_supported;
    }

    public static function Register()
    {
        foreach (self::$_supported as $modelName) {
            parent::RegisterModel(new Google_Completions($modelName));
        }
    }
}

Google_Completions::Register();

