<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class Anthropic_Completions extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'anthropic-claude-3-opus-latest',
        'anthropic-claude-3-5-haiku-latest',
        'anthropic-claude-3-5-sonnet-latest',        
        'anthropic-claude-3-7-sonnet-latest',
        'anthropic-claude-opus-4-0',
        'anthropic-claude-sonnet-4-0'
    ];
    

    public function __construct($name = 'anthropic-claude-sonnet-4-0')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'anthropic-claude-sonnet-4-0';
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

        // Convert model name to Anthropic format
        $modelName = explode('anthropic-', $this->_name)[1];
        if (isset(self::$_links[$modelName])) {
            $modelName = explode('anthropic-', self::$_links[$modelName])[1];
        }

        // Prepare messages
        $system = '';
        $dataMessages = [];
        foreach ($messages as $msg) 
        {
            $role = 'user';
            if ($msg->role === MessageRole::SYSTEM)
            {
                if ($msg->attributes['type'] === MessageType::TEXT)
                    $system .= $msg->content."\r\n";
                
                continue;
            }
            elseif ($msg->role === MessageRole::ASSISTANT)
                $role = 'assistant';
            elseif (in_array($msg->role, [MessageRole::TOOL, MessageRole::RESULT]))
                $role = 'user';

            // Check if this is a file message that needs to be encoded
            if (in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM]) && 
                $msg->attributes['type'] === MessageType::FILE) {
                
                // Determine media type (default to jpeg if not specified)
                $mediaType = $msg->attributes['media_type'] ?? 'image/jpeg';
                $dataMessages[] = [
                    'role' => $role,
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mediaType,
                                'data' => base64_encode($msg->content)
                            ]
                        ]
                    ]
                ];
            }
            else 
            {
                // Regular text message
                $dataMessages[] = [
                    'role' => $role,
                    'content' => $msg->content
                ];
            }
            
        }

        // Prepare the request data
        $data = [
            'model' => $modelName,
            'max_tokens' => 4096,
            'messages' => $dataMessages,
            'system' => $system
        ];

        // Add additional data if provided
        $additionalData = [];
        $supportedAddonData = [
            'max_tokens', 'metadata', 'stop_sequences', 'stream', 'system',
            'temperature', 'top_k', 'top_p', 'user'
        ];
        
        foreach ($supportedAddonData as $key) {
            if (isset($options->$key))
                $additionalData[$key] = $options->$key;
        }
        
        $data = array_merge($data, $additionalData);

        // Add tools if provided and supported
        if (!empty($tools)) {
            $functions = [];
            foreach ($tools as $tool) {
                if ($tool instanceof \AIpi\Tools\FunctionCall) {
                    $properties = [];
                    foreach ($tool->properties as $property => $type) {
                        $properties[$property] = [
                            'type' => $type,
                            'description' => $tool->property_descriptions[$property] ?? ''
                        ];
                    }
                    
                    $functions[] = [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'input_schema' => [
                            "type" => "object",
                            'properties' => $properties,
                            'required' => $tool->property_required
                        ]
                    ];
                }
            }
            if (!empty($functions)) {
                $data['tools'] = $functions;
            }
        }
        
        // Initialize cURL session
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $apikey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // First error handling block - HTTP errors
        if ($httpCode !== 200) {
            $this->_lastError = 'Anthropic API request failed with status ' . $httpCode . ': ' . $response;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Parse the response
        $result = json_decode($response);
        if (!$result) {
            $this->_lastError = 'Invalid response from Anthropic API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token counts
        if (isset($result->usage)) {
            $tokens['input'] = $result->usage->input_tokens ?? 0;
            $tokens['output'] = $result->usage->output_tokens ?? 0;
        }

        // Handle tool calls if present
        if (isset($result->content) && is_array($result->content)) {
            foreach ($result->content as $content) {
                if ($content->type === 'tool_use') {
                    return new Message(json_encode([
                        'calls' => [
                            [
                                'name' => $content->name,
                                'args' => $content->input
                            ]
                        ]
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), MessageRole::TOOL);
                }
            }
            
            // If we have text content, return that
            foreach ($result->content as $content) {
                if ($content->type === 'text') {
                    return new Message($content->text, MessageRole::ASSISTANT);
                }
            }
        }
        
        $this->_lastError = 'No valid content found in response';
        if ($options->throwOnError ?? true) {
            throw new \Exception($this->_lastError);
        }
        return null;
    }

    public static function GetSupported()
    {
        return self::$_supported;
    }

    public static function Register()
    {
        foreach (self::$_supported as $modelName) {
            parent::RegisterModel(new Anthropic_Completions($modelName));
        }
    }
}

Anthropic_Completions::Register();

