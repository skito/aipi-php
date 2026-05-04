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
        'openai-gpt-5.5-pro',
        'openai-gpt-5.5',
        'openai-gpt-5.4-nano',
        'openai-gpt-5.4-mini',
        'openai-gpt-5.4-pro',
        'openai-gpt-5.4',
        'openai-gpt-5.3-codex',
        'openai-gpt-5.3-chat-latest',
        'openai-gpt-5.2-codex',
        'openai-gpt-5.2-pro',
        'openai-gpt-5.2-chat-latest',
        'openai-gpt-5.2',
        'openai-gpt-5.1-codex-max',
        'openai-gpt-5.1-codex-mini',
        'openai-gpt-5.1-codex',
        'openai-gpt-5.1-chat-latest',
        'openai-gpt-5.1',
        'openai-gpt-5',
        'openai-gpt-5-pro',
        'openai-gpt-5-mini',
        'openai-gpt-5-nano',
        'openai-gpt-5-codex',
        'openai-gpt-5-chat-latest',
        'openai-gpt-4.5-preview', // Depricated
        'openai-gpt-4',
        'openai-gpt-4-turbo',
        'openai-gpt-4-turbo-preview',
        'openai-gpt-4.1',
        'openai-gpt-4o',
        'openai-chatgpt-4o-latest',
        'openai-gpt-4o-mini',
        'openai-gpt-4.1-mini',
        'openai-gpt-4.1-nano',
        'openai-o1',
        'openai-o1-pro',
        'openai-o1-mini',
        'openai-o1-preview',
        'openai-o3',
        'openai-o3-mini',
        'openai-o4-mini',
        'openai-gpt-4o-mini-search-preview',
        'openai-gpt-4o-search-preview',
        'openai-gpt-3.5-turbo'
    ];
    

    public function __construct($name = 'openai-gpt-5.4')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'openai-gpt-5.4';
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
            'input' => $this->_BuildInput($messages)
        ];

        // Add additional data if provided
        $data = array_merge($data, $this->_BuildOptions($options));

        // Add tools if provided and supported
        $toolDefinitions = $this->_BuildTools($tools);
        if (!empty($toolDefinitions))
            $data['tools'] = $toolDefinitions;

        // Initialize cURL session
        $ch = curl_init('https://api.openai.com/v1/responses');
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
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlError != null) {
            $this->_lastError = 'OpenAI API request failed: ' . $curlError;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // First error handling block - HTTP errors
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->_lastError = 'OpenAI API request failed with status ' . $httpCode . ': ' . $response;
            if ($options->throwOnError ?? true) {
                throw new \Exception($this->_lastError);
            }
            return null;
        }

        // Parse the response
        $result = json_decode($response);
        if (!$result || !isset($result->output) || !is_array($result->output)) {
            $this->_lastError = 'Invalid response from OpenAI API';
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

        $textContent = $this->_GetOutputText($result);
        $functionCalls = $this->_GetFunctionCalls($result);
        if (!empty($functionCalls)) {
            $content = json_encode([
                'thoughts' => $textContent,
                'calls' => $functionCalls,
                'responses_output' => $result->output
            ], JSON_UNESCAPED_UNICODE);

            return new Message($content, MessageRole::TOOL);
        }

        return new Message($textContent, MessageRole::ASSISTANT);
    }

    private function _BuildInput($messages)
    {
        $input = [];
        $pendingToolCalls = [];
        $fallbackCallIndex = 0;

        foreach ($messages as $message)
        {
            if ($message->role === MessageRole::TOOL)
            {
                $this->_AppendToolCallInput($input, $pendingToolCalls, $fallbackCallIndex, $message);
                continue;
            }

            if ($message->role === MessageRole::RESULT && $this->_AppendToolResultInput($input, $pendingToolCalls, $fallbackCallIndex, $message))
                continue;

            $input[] = $this->_BuildInputMessage($message);
        }

        return $input;
    }

    private function _BuildInputMessage($message)
    {
        $role = 'user';
        if ($message->role === MessageRole::SYSTEM)
            $role = 'developer';
        elseif (in_array($message->role, [MessageRole::ASSISTANT, MessageRole::RESULT, MessageRole::TOOL]))
            $role = 'assistant';

        $messageType = $message->attributes['type'] ?? MessageType::TEXT;
        if (in_array($messageType, [MessageType::FILE, MessageType::LINK]) && in_array($message->role, [MessageRole::USER, MessageRole::SYSTEM]))
        {
            $content = [];
            if (!empty($message->text)) {
                $content[] = [
                    'type' => 'input_text',
                    'text' => $message->text
                ];
            }

            if ($messageType === MessageType::FILE) {
                $mediaType = $message->attributes['media_type'] ?? 'image/jpeg';
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => 'data:' . $mediaType . ';base64,' . base64_encode($message->content)
                ];
            } else {
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => $message->content
                ];
            }

            return [
                'role' => $role,
                'content' => $content
            ];
        }

        return [
            'role' => $role,
            'content' => $message->content
        ];
    }

    private function _AppendToolCallInput(&$input, &$pendingToolCalls, &$fallbackCallIndex, $message)
    {
        $decoded = json_decode($message->content, true);
        if (!is_array($decoded))
        {
            $input[] = $this->_BuildInputMessage($message);
            return;
        }

        if (isset($decoded['responses_output']) && is_array($decoded['responses_output']))
        {
            foreach ($decoded['responses_output'] as $outputItem)
            {
                if (!is_array($outputItem))
                    continue;

                $input[] = $outputItem;
                if (($outputItem['type'] ?? '') === 'function_call')
                    $this->_TrackPendingToolCall($pendingToolCalls, $fallbackCallIndex, $outputItem);
            }
            return;
        }

        foreach (($decoded['calls'] ?? []) as $call)
        {
            if (is_object($call))
                $call = (array)$call;
            if (!is_array($call))
                continue;

            $callInput = $this->_BuildFunctionCallInput($call, $fallbackCallIndex);
            $input[] = $callInput;
            $this->_TrackPendingToolCall($pendingToolCalls, $fallbackCallIndex, $callInput);
        }
    }

    private function _AppendToolResultInput(&$input, &$pendingToolCalls, &$fallbackCallIndex, $message)
    {
        $decoded = json_decode($message->content, true);
        if (!isset($decoded['tool_result']) || !is_array($decoded['tool_result']))
            return false;

        $toolResult = $decoded['tool_result'];
        $toolName = $toolResult['tool_name'] ?? '';
        $callID = null;
        if ($toolName != '' && !empty($pendingToolCalls[$toolName]))
            $callID = array_shift($pendingToolCalls[$toolName]);

        if ($callID == null)
        {
            $callID = 'call_' . (++$fallbackCallIndex);
        }

        $output = $toolResult['result'] ?? null;
        if (!is_string($output))
            $output = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $input[] = [
            'type' => 'function_call_output',
            'call_id' => $callID,
            'output' => $output
        ];

        return true;
    }

    private function _BuildFunctionCallInput($call, &$fallbackCallIndex)
    {
        $args = $call['args'] ?? new \stdClass();
        $arguments = is_string($args) ? $args : json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'type' => 'function_call',
            'call_id' => $call['call_id'] ?? $call['id'] ?? ('call_' . (++$fallbackCallIndex)),
            'name' => $call['name'] ?? '',
            'arguments' => $arguments
        ];
    }

    private function _TrackPendingToolCall(&$pendingToolCalls, &$fallbackCallIndex, $callInput)
    {
        $toolName = $callInput['name'] ?? '';
        if ($toolName == '')
            return;

        $callID = $callInput['call_id'] ?? $callInput['id'] ?? ('call_' . (++$fallbackCallIndex));
        if (!isset($pendingToolCalls[$toolName]))
            $pendingToolCalls[$toolName] = [];

        $pendingToolCalls[$toolName][] = $callID;
    }

    private function _BuildOptions($options)
    {
        $data = [];
        $supportedAddonData = [
            'store', 'metadata', 'service_tier', 'temperature', 'top_p',
            'parallel_tool_calls', 'user', 'tool_choice', 'stream', 'include', 'truncation'
        ];

        foreach ($supportedAddonData as $key) {
            if (isset($options->$key))
                $data[$key] = $options->$key;
        }

        if (isset($options->reasoning))
            $data['reasoning'] = $options->reasoning;
        elseif (isset($options->reasoning_effort))
            $data['reasoning'] = ['effort' => $options->reasoning_effort];

        if (isset($options->max_output_tokens))
            $data['max_output_tokens'] = $options->max_output_tokens;
        elseif (isset($options->max_completion_tokens))
            $data['max_output_tokens'] = $options->max_completion_tokens;
        elseif (isset($options->max_tokens))
            $data['max_output_tokens'] = $options->max_tokens;

        if (isset($options->response_format))
            $data['text'] = ['format' => $options->response_format];

        if (isset($options->text))
            $data['text'] = array_merge((array)($data['text'] ?? []), (array)$options->text);

        if (isset($options->instructions))
            $data['instructions'] = $options->instructions;

        return $data;
    }

    private function _BuildTools($tools)
    {
        $functions = [];
        foreach ($tools as $tool)
        {
            if ($tool instanceof \AIpi\Tools\FunctionCall)
            {
                $properties = [];
                foreach ($tool->properties as $property => $type)
                    $properties[$property] = $this->_BuildToolProperty($type, $tool->property_descriptions[$property] ?? '');

                $functions[] = [
                    'type' => 'function',
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)$properties,
                        'required' => $tool->property_required
                    ]
                ];
            }
        }

        return $functions;
    }

    private function _BuildToolProperty($type, $description)
    {
        $property = [
            'type' => $type
        ];

        if ($description != '')
            $property['description'] = $description;

        if ($type == 'array')
            $property['items'] = ['type' => 'string'];

        return $property;
    }

    private function _GetOutputText($result)
    {
        $text = $result->output_text ?? '';
        if ($text != '')
            return $text;

        foreach ($result->output as $outputItem)
        {
            if (($outputItem->type ?? '') !== 'message' || !isset($outputItem->content) || !is_array($outputItem->content))
                continue;

            foreach ($outputItem->content as $contentItem)
            {
                if (isset($contentItem->text) && in_array($contentItem->type ?? '', ['output_text', 'text', 'refusal']))
                    $text .= $contentItem->text;
                elseif (isset($contentItem->refusal))
                    $text .= $contentItem->refusal;
            }
        }

        return $text;
    }

    private function _GetFunctionCalls($result)
    {
        $functionCalls = [];
        foreach ($result->output as $outputItem)
        {
            if (($outputItem->type ?? '') !== 'function_call')
                continue;

            $args = json_decode($outputItem->arguments ?? '{}');
            if ($args == null && json_last_error() !== JSON_ERROR_NONE)
                $args = new \stdClass();

            $functionCalls[] = [
                'name' => $outputItem->name ?? '',
                'args' => $args,
                'call_id' => $outputItem->call_id ?? null,
                'id' => $outputItem->id ?? null
            ];
        }

        return $functionCalls;
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

