<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Transcriptions extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-whisper-1',
        'openai-gpt-4o-transcribe',
        'openai-gpt-4o-mini-transcribe'
    ];
    

    public function __construct($name = 'openai-whisper-1')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-whisper-1';
    }

    public function GetName()
    {
        return $this->_name;
    }
    
    public function GetLastError()
    {
        return $this->_lastError;
    }

    private function generateBoundary() {
        return '-------------' . uniqid();
    }

    private function buildMultipartBody($data, $boundary, $fileContent = null) {
        $body = '';
        
        // Add regular form fields
        foreach ($data as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        
        // Add file data if present
        if ($fileContent !== null) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.mp3\"\r\n";
            $body .= "Content-Type: audio/mpeg\r\n\r\n";
            $body .= $fileContent . "\r\n";
        }
        
        $body .= "--{$boundary}--\r\n";
        return $body;
    }

    public function Call($apikey, $messages, $tools = [], $options=[], &$tokens=null)
    {
        $options = (object)$options;
        $tokens = ['input' => 0, 'output' => 0, 'files' => 0];
        $this->_lastError = '';

        // Find the first file message
        $fileMessage = null;
        $prompt = '';
        foreach ($messages as $msg) {
            if ($msg->attributes['type'] === MessageType::TEXT && 
                in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                $prompt .= $msg->content."\r\n";
            }
            else if ($fileMessage == null &&$msg->attributes['type'] === MessageType::FILE && 
                in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                $fileMessage = $msg;
            }
        }

        if (!$fileMessage) {
            $this->_lastError = 'No audio file found in messages';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Prepare the request data
        $modelName = explode('openai-', $this->_name)[1];
        $data = [
            'model' => $modelName
        ];

        if (trim($prompt) != '')
            $data['prompt'] = $prompt;

        // Add additional options if provided
        if (isset($options->language))
            $data['language'] = $options->language;
        if (isset($options->response_format))
            $data['response_format'] = $options->response_format;
        if (isset($options->temperature))
            $data['temperature'] = $options->temperature;
        if (isset($options->user))
            $data['user'] = $options->user;

        // Prepare multipart form data
        $boundary = $this->generateBoundary();
        $body = $this->buildMultipartBody($data, $boundary, $fileMessage->content);

        // Determine endpoint based on translation attribute
        $endpoint = 'https://api.openai.com/v1/audio/';
        $endpoint .= ($fileMessage->attributes['translation'] ?? false) ? 'translations' : 'transcriptions';

        // Initialize cURL session
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

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
        if (!$result || !isset($result->text)) {
            $this->_lastError = 'Invalid response from OpenAI API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Update token count (only counting files for transcriptions)
        $tokens['files'] = 1;

        // Return transcribed text as a message
        return new Message(
            $result->text,
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
            parent::RegisterModel(new OpenAI_Transcriptions($modelName));
        }
    }
}

OpenAI_Transcriptions::Register();

