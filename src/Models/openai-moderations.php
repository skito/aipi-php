<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Moderations extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-omni-moderation-latest',
        'openai-text-moderation-latest',
        'openai-text-moderation-stable'
    ];
    

    public function __construct($name = 'openai-omni-moderation-latest')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-omni-moderation-latest';
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
            'input' => $input
        ];

        // Add user if provided
        if (isset($options->user)) {
            $data['user'] = $options->user;
        }

        // Initialize cURL session
        $ch = curl_init('https://api.openai.com/v1/moderations');
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

        // Parse the response
        $result = json_decode($response);
        if (!$result || !isset($result->results[0])) {
            $this->_lastError = 'Invalid response from OpenAI API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Return moderation result as a message
        return new Message(
            json_encode($result->results[0], JSON_UNESCAPED_UNICODE),
            ['role' => MessageRole::ASSISTANT, 'type' => MessageType::TEXT]
        );
    }

    public static function GetSupported()
    {
        return self::$_supported;
    }

    public static function Register()
    {
        foreach (self::$_supported as $modelName) {
            parent::RegisterModel(new OpenAI_Moderations($modelName));
        }
    }
}

OpenAI_Moderations::Register();

