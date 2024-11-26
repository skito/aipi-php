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
        'google-gemini-1.5-pro'
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
        $data = [
            'model' => $modelName,
            'contents' => array_map(function($msg) {
                $role = 'user';
                if ($msg->role === MessageRole::SYSTEM)
                    $role = 'system';
                elseif ($msg->role === MessageRole::ASSISTANT)
                    $role = 'model';
                elseif (in_array($msg->role, [MessageRole::TOOL, MessageRole::RESULT]))
                    $role = 'model';

                $parts = [];

                // Handle different message types
                if ($msg->attributes['type'] === MessageType::FILE && 
                    in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                    $mediaType = $msg->attributes['media_type'] ?? 'image/jpeg';
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mediaType,
                            'data' => base64_encode($msg->content)
                        ]
                    ];
                }

                // Add text content if present
                if ($msg->attributes['type'] === MessageType::TEXT || 
                    !in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                    $parts[] = [
                        'text' => $msg->content
                    ];
                }

                return [
                    'role' => $role,
                    'parts' => $parts
                ];
            }, $messages)
        ];

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
                            'properties' => $parameters,
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
        
        // Initialize cURL session
        $ch = curl_init('https://generativelanguage.googleapis.com/'.$apiVersion.'/models/'.$modelName.':generateContent');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-goog-api-key: ' . $apikey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

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
        if (!$result || !isset($result->candidates[0]->content)) {
            $this->_lastError = 'Invalid response from Google API';
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

        $content = $result->candidates[0]->content;
        $role = MessageRole::ASSISTANT;

        // Handle function calls
        if (isset($content->parts[0]->functionCall)) {
            $role = MessageRole::TOOL;
            $functionCall = $content->parts[0]->functionCall;
            $content = json_encode([
                'calls' => [
                    [
                        'name' => $functionCall->name,
                        'args' => $functionCall->args
                    ]
                ]
            ]);
        } else {
            // Regular text response
            $content = $content->parts[0]->text ?? '';
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

