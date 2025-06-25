<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Speech extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-gpt-4o-mini-tts',
        'openai-tts-1',
        'openai-tts-1-hd'
    ];
    
    private static $_voices = [
        'alloy',
        'echo',
        'fable',
        'onyx',
        'nova',
        'shimmer'
    ];

    public function __construct($name = 'openai-tts-1')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-tts-1';
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
        $tokens = ['input' => 0, 'output' => 0, 'files' => 0];
        $this->_lastError = '';

        // Set default voice if not provided
        if (!isset($options->voice)) {
            $options->voice = 'alloy';
        }

        // Validate voice parameter
        if (!in_array($options->voice, self::$_voices)) {
            $this->_lastError = 'Invalid voice parameter. Must be one of: ' . implode(', ', self::$_voices);
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Combine all user and system messages of type text
        $input = '';
        foreach ($messages as $msg) {
            if (in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM]) && 
                $msg->attributes['type'] === MessageType::TEXT) {
                $input .= $msg->content . "\n";
            }
        }
        $input = trim($input);

        if (empty($input)) {
            $this->_lastError = 'No valid input text found in messages';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Prepare the request data
        $modelName = explode('openai-', $this->_name)[1];
        $data = [
            'model' => $modelName,
            'input' => $input,
            'voice' => $options->voice
        ];

        // Add additional options if provided
        $additionalData = [];
        if (isset($options->response_format))
            $additionalData['response_format'] = $options->response_format;
        if (isset($options->speed))
            $additionalData['speed'] = $options->speed;
        if (isset($options->user))
            $additionalData['user'] = $options->user;

        $data = array_merge($data, $additionalData);

        // Initialize cURL session
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $this->_lastError = 'OpenAI API request failed with status ' . $httpCode . ': ' . $response;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token count (only counting files for speech)
        $tokens['files'] = 1;

        // Return audio content as a message
        return new Message(
            $response,
            ['role' => MessageRole::ASSISTANT, 'type' => MessageType::FILE]
        );
    }

    public static function GetSupported()
    {
        return self::$_supported;
    }

    public static function Register()
    {
        foreach (self::$_supported as $modelName) {
            parent::RegisterModel(new OpenAI_Speech($modelName));
        }
    }
}

OpenAI_Speech::Register();

