<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Completions extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-gpt-4o',
        'openai-chatgpt-4o-latest',
        'openai-gpt-4o-mini',
        'openai-o1-preview',
        'openai-o1-mini',
        'openai-gpt-4',
        'openai-gpt-4-turbo',
        'openai-gpt-4-turbo-preview'
    ];
    

    public function __construct($name = 'openai-gpt-4o')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-gpt-4o';
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
        $modelName = explode('openai-', $this->_name)[1];
        $data = [
            'model' => $modelName,
            'messages' => array_map(function($msg) {
                $role = 'user';
                if (in_array($msg->role, [MessageRole::SYSTEM, MessageRole::USER, MessageRole::ASSISTANT]))
                    $role = $msg->role;
                elseif (in_array($msg->role, [MessageRole::TOOL, MessageRole::RESULT]))
                    $role = MessageRole::ASSISTANT;

                $content = $msg->content;
                return [
                    'role' => $role,
                    'content' => $content
                ];
            }, $messages)
        ];

        // Add additional data if provided
        $additionalData = [];
        $supportedAddonData = [
            'store', 'metadata', 'frequency_penalty', 'logit_bias', 'logprobs', 'top_logprobs', 
            'max_tokens', 'max_completion_tokens', 'n', 'modalities', 'prediction', 'audio',
            'presence_penalty', 'response_format', 'service_tier', 'stop', 'stream', 'stream_options',
            'temperature', 'top_p', 'parallel_tool_calls', 'user'
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
                    $functions[] = [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => [
                            'type' => 'object',
                            'properties' => array_map(function($type) {
                                return ['type' => $type];
                            }, $tool->properties),
                            'required' => $tool->property_required
                        ]
                    ];
                }
            }
            if (!empty($functions)) {
                $data['functions'] = $functions;
            }
        }

        // Initialize cURL session
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // First error handling block - HTTP errors
        if ($httpCode !== 200) {
            $this->_lastError = 'OpenAI API request failed with status ' . $httpCode . ': ' . $response;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Parse the response
        $result = json_decode($response);
        if (!$result || !isset($result->choices[0]->message)) {
            $this->_lastError = 'Invalid response from OpenAI API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token counts
        if (isset($result->usage)) {
            $tokens['input'] = $result->usage->prompt_tokens ?? 0;
            $tokens['output'] = $result->usage->completion_tokens ?? 0;
        }

        // Create message object from response
        $message = $result->choices[0]->message;
        $role = $message->role;
        $content = $message->content;

        // Handle function calls
        if (isset($message->function_call)) {
            $role = MessageRole::TOOL;
            $content = json_encode([
                'calls' => [
                    [
                        'name' => $message->function_call->name,
                        'args' => json_decode($message->function_call->arguments)
                    ]
                ]
            ]);
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
            parent::RegisterModel(new OpenAI_Completions($modelName));
        }
    }
}

OpenAI_Completions::Register();
