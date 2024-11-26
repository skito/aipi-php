<?php

namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\MessageType;

class OpenAI_Images extends ModelBase implements IModel
{
    private $_name = '';
    private $_lastError = '';
    
    private static $_supported = [
        'openai-dall-e-3',
        'openai-dall-e-2'
    ];
    
    private function generateBoundary() {
        return '-------------' . uniqid();
    }

    private function buildMultipartBody($data, $boundary, $binaryData = null) {
        $body = '';
        
        // Add regular form fields
        foreach ($data as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        
        // Add binary data if present
        if ($binaryData !== null) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"image.png\"\r\n";
            $body .= "Content-Type: application/octet-stream\r\n\r\n";
            $body .= $binaryData . "\r\n";
        }
        
        $body .= "--{$boundary}--\r\n";
        return $body;
    }

    public function __construct($name = 'openai-dall-e-3')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-dall-e-3';
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

        // Find if we have any FILE type messages
        $fileMessage = null;
        $textPrompts = [];
        
        foreach ($messages as $msg) {
            if ($msg->attributes['type'] === MessageType::FILE && 
                in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                $fileMessage = $msg;
                break;
            }
            
            if (in_array($msg->role, [MessageRole::USER, MessageRole::SYSTEM])) {
                $textPrompts[] = $msg->content;
            }
        }

        // Prepare the request data and endpoint
        $modelName = explode('openai-', $this->_name)[1];
        $endpoint = 'https://api.openai.com/v1/';
        
        $data = []; 
        $additionalData = [];
        if (isset($options->quality))
            $additionalData['quality'] = $options->quality;
        if (isset($options->style))
            $additionalData['style'] = $options->style;
        if (isset($options->user))
            $additionalData['user'] = $options->user;

        if ($fileMessage) {
            // Image variation endpoint
            $isEdit = $fileMessage->attributes['edit'] ?? false;
            $endpoint .= $isEdit ? 'images/edits' : 'images/variations';
            $data = [
                'model' => $modelName,
                'n' => $options->n ?? 1,
                'size' => $options->size ?? '1024x1024'
            ];

            if ($isEdit)
                $data['prompt'] = implode("\n", $textPrompts);
            
            if (isset($options->user)) {
                $data['user'] = $options->user;
            }

            if (isset($options->response_format)) {
                $data['response_format'] = $options->response_format;
            }
            
            $boundary = $this->generateBoundary();
            $body = $this->buildMultipartBody($data, $boundary, $fileMessage->content);
            $headers = [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Content-Length: ' . strlen($body)
            ];
        } else {
            // Image generation endpoint
            $endpoint .= 'images/generations';
            $data = array_merge([
                'model' => $modelName,
                'prompt' => implode("\n", $textPrompts),
                'n' => $options->n ?? 1,
                'size' => $options->size ?? '1024x1024'
            ], $additionalData);
            
            $body = json_encode($data);
            $headers = [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body)
            ];
        }

        // Initialize cURL session
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
        if (!$result || !isset($result->data[0]->url)) {
            $this->_lastError = 'Invalid response from OpenAI API';
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        $tokens['files'] = $options->n ?? 1;

        // Create message object from response
        return new Message($result->data[0]->url, ['role' => MessageRole::ASSISTANT, 'type' => MessageType::LINK]);
    }

    public static function GetSupported()
    {
        return self::$_supported;
    }

    public static function Register()
    {
        foreach (self::$_supported as $modelName) {
            parent::RegisterModel(new OpenAI_Images($modelName));
        }
    }
}

OpenAI_Images::Register();

