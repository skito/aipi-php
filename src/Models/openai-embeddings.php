<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Embeddings extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-text-embedding-3-large',
        'openai-text-embedding-3-small',
        'openai-text-embedding-ada-002'
    ];
    

    public function __construct($name = 'openai-text-embedding-3-large')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-text-embedding-3-large';
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

        $additionalData = [];
        if (isset($options->encoding_format))
            $additionalData['encoding_format'] = $options->encoding_format;
        if (isset($options->dimensions))
            $additionalData['dimensions'] = $options->dimensions;
        if (isset($options->user))
            $additionalData['user'] = $options->user;

        $data = array_merge($data, $additionalData);


        // Initialize cURL session
        $ch = curl_init('https://api.openai.com/v1/embeddings');
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
        if (!$result || !isset($result->data[0]->embedding)) {
            $this->_lastError = 'Invalid response from OpenAI API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token count (embeddings only have input tokens)
        if (isset($result->usage)) {
            $tokens['input'] = $result->usage->total_tokens ?? 0;
        }

        // Return embedding as a message
        return new Message(
            json_encode($result->data[0]->embedding),
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
            parent::RegisterModel(new OpenAI_Embeddings($modelName));
        }
    }
}

OpenAI_Embeddings::Register();

