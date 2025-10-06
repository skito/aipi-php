<?php

namespace AIpi;

class Thread
{
    public $messages = [];
    public $tools = [];
    public $toolsEnabled = true; // handy for temporary disabling tools
    public $options = null; // object of key-value pairs (option name => option value)
    public $meta = []; // storing any additional helper data for your convenience - not used anywhere the communication

    private $_model = null;
    private $_apikey = null;
    private $_inputTokens = 0; // Total input tokens on message processing
    private $_outputTokens = 0; // Total output tokens on message processing
    private $_files = 0;
    private $_executionTime = 0; // Total execution time on message processing
    private $_messageCallbacks = [];
    private $_modelChangeCallbacks = [];
    private $_fallbackModels = [];

    public function __construct($model, $apikey, $opts=[])
    {
        $this->_apikey = $apikey;  
        $this->options = (object)array_merge([
            'throwOnError' => true
        ], (array)$opts);

        $this->ChangeModel($model, $apikey);
    }

    public function ChangeModel($model, $apikey, $opts=null)
    {
        $this->_apikey = $apikey;

        if (is_string($model))
            $this->_model = ModelBase::Get($model);
        elseif (is_object($model) && $model instanceof IModel)
            $this->_model = $model;

        if ($this->_model == null)
            throw new \Exception('Model not supported or name typo.');

        if ($opts !== null) {
            $this->options = (object)array_merge([
                'throwOnError' => true
            ], (array)$opts);
        }

        $this->_inputTokens = 0;
        $this->_outputTokens = 0;
        $this->_files = 0;

        $this->_RunCallbacks($this->_modelChangeCallbacks, (object)[
            'thread' => $this, 
            'model' => $this->_model
        ]);
    }

    public function AddFallbackModel($model, $apikey, $opts=null)
    {
        $this->_fallbackModels[] = (object)[
            'model' => $model,
            'apikey' => $apikey,
            'opts' => $opts
        ];
    }

    public function RemoveFallbackModel($model, $apikey, $opts=null)
    {
        foreach ($this->_fallbackModels as $key => $fallbackModel)
        {
            if ($fallbackModel->model == $model)
                unset($this->_fallbackModels[$key]);
        }
    }

    public function GetModel()
    {
        return $this->_model;
    }

    public function GetLastError()
    {
        return $this->_model->GetLastError();
    }

    public function GetUsage()
    {
        return (object)[ 
            'inputTokens' => $this->_inputTokens,
            'outputTokens' => $this->_outputTokens,
            'files' => $this->_files,
            'executionTime' => $this->_executionTime
        ];
    }    

    public function AddMessage($message, $options=[])
    {
        $options = (object)array_merge([
            'runCallbacks' => true,
            'model' => $this->GetModel(),
            'usage' => (object)[
                'inputTokens' => 0,
                'outputTokens' => 0,
                'files' => 0,
                'executionTime' => 0
            ]
        ], $options);

        if ($message instanceof Message)
        {
            $this->messages[] = $message;
            if ($options->runCallbacks)
            {
                $this->_RunCallbacks($this->_messageCallbacks, (object)[
                    'thread' => $this, 
                    'message' => $message, 
                    'usage' => $options->usage,
                    'model' => $options->model
                ]);   
            }
        }
    }

    public function AddTool($tool)
    {
        if ($tool instanceof ITool)
            $this->tools[] = $tool;
    }

    public function GetLastMessage()
    {
        $index = count($this->messages) - 1;
        if ($index >= 0) return $this->messages[$index];
        else return null;
    }

    public function GetLastToolResult($toolName)
    {
        foreach ($this->tools as $tool)
        {
            if ($tool->GetName() == $toolName)
                return $tool->GetLastResult();
        }
        return null;
    }

    public function DisableTools()
    {
        $this->toolsEnabled = false;
    }

    public function EnableTools()
    {
        $this->toolsEnabled = true;
    }

    // Alias for AddMessageCallback
    public function OnMessage($callback)
    {
        $this->AddMessageCallback($callback);
    }

    public function AddMessageCallback($callback)
    {
        $key = $this->_GetCallbackKey($callback);
        $this->_messageCallbacks[$key] = $callback;
    }

    public function RemoveMessageCallback($callback)
    {
        $key = $this->_GetCallbackKey($callback);
        unset($this->_messageCallbacks[$key]);
    }


    // Alias for AddModelChangeCallback
    public function OnModelChange($callback)
    {
        $this->AddModelChangeCallback($callback);
    }

    public function AddModelChangeCallback($callback)
    {
        $key = $this->_GetCallbackKey($callback);
        $this->_modelChangeCallbacks[$key] = $callback;
    }

    public function RemoveModelChangeCallback($callback)
    {
        $key = $this->_GetCallbackKey($callback);
        unset($this->_modelChangeCallbacks[$key]);
    }


    public function RunOnce()
    {
        return $this->Run(false);
    }

    public function Run($autocomplete=true)
    {
        $model = $this->GetModel();
        if ($this->_model == null)
            throw new \Exception('Model not assigned.');
    
        $hasFallbackModels = count($this->_fallbackModels) > 0;
        if ($hasFallbackModels) $this->options->throwOnError = false;

        $tokens = null;
        $exTimeBegin = microtime(true);
        $message = $model->Call(
            $this->_apikey, 
            $this->messages, 
            $this->toolsEnabled ? $this->tools : [], 
            $this->options, 
            $tokens
        );
        $executionTime = microtime(true) - $exTimeBegin;

        if (!$message)
        {
            if ($hasFallbackModels)
            {
                $nextFallbackModel = $this->_GetNextFallbackModel();
                if ($nextFallbackModel)
                {
                    $this->ChangeModel($nextFallbackModel->model, $nextFallbackModel->apikey, $nextFallbackModel->opts);
                    return $this->Run($autocomplete);
                }
            }

            if ($this->options->throwOnError)
                throw new \Exception('Model returned no message');
            return null;
        }

        $tokens = (object)$tokens;
        $this->_inputTokens += $tokens->input ?? 0;
        $this->_outputTokens += $tokens->output ?? 0;
        $this->_files += $tokens->files ?? 0;
        $this->_executionTime += $executionTime;

        $this->AddMessage($message, [
            'usage' => (object)[
                'inputTokens' => $tokens->input ?? 0,
                'outputTokens' => $tokens->output ?? 0,
                'files' => $tokens->files ?? 0,
                'executionTime' => $executionTime
            ]
        ]);

        if ($message->role == MessageRole::TOOL)
        {
            $calls = @json_decode($message->content) ?? (object)['calls' => []];
            $toolResults = [];
            
            foreach ($calls->calls as $call)
            {
                $toolcall = ToolCall::ParseArray($call);
                if ($toolcall)
                {
                    $toolMatched = false;
                    foreach ($this->tools as $tool)
                    {
                        if ($tool->GetName() == $toolcall->toolname)
                        {
                            $toolMatched = true;
                            $toolExecutionStart = microtime(true);
                            $result = $tool->RunCallback($toolcall->args);
                            $toolExecutionEnd = microtime(true);
                            $toolResults[] = [
                                'tool_result' => [
                                    'tool_name' => $tool->GetName(),
                                    'tool_type' => $tool->GetType(),
                                    'result' => $result,
                                    'exe_time' => $toolExecutionEnd - $toolExecutionStart
                                ]
                            ];
                            break;
                        }
                    }

                    if (!$toolMatched)
                    {
                        $toolResults[] = [
                            'tool_result' => [
                                'tool_name' => $toolcall->toolname,
                                'tool_type' => 'undefined',
                                'result' => null,
                                'error' => 'Tool not available.',
                                'exe_time' => 0
                            ]
                        ];
                    }
                }
            }
            
            // Create a single message with all tool results
            if (!empty($toolResults)) {
                if (count($toolResults) == 1) {
                    // Single tool result - use existing format
                    $resultMessage = new Message('', MessageRole::RESULT);
                    $resultMessage->content = json_encode($toolResults[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $exeTime = $toolResults[0]['tool_result']['exe_time'];
                    
                    $this->AddMessage($resultMessage, [
                        'usage' => (object)[
                            'inputTokens' => 0,
                            'outputTokens' => 0,
                            'files' => 0,
                            'executionTime' => $exeTime
                        ]
                    ]);
                } else {
                    // Multiple tool results - create separate messages for each for Google API compatibility
                    foreach ($toolResults as $toolResult) {
                        $resultMessage = new Message('', MessageRole::RESULT);
                        $resultMessage->content = json_encode($toolResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $exeTime = $toolResult['tool_result']['exe_time'];
                        
                        $this->AddMessage($resultMessage, [
                            'usage' => (object)[
                                'inputTokens' => 0,
                                'outputTokens' => 0,
                                'files' => 0,
                                'executionTime' => $exeTime
                            ]
                        ]);
                    }
                }
            }
            
            if ($autocomplete && $this->GetLastMessage()->role == MessageRole::RESULT)
                $message = $this->Run($autocomplete);
            
        }
        
        return $message;
    }

    private function _GetCallbackKey($callback)
    {
        if (is_array($callback)) {
            // For object methods or class static methods
            return md5(json_encode($callback));
        } elseif ($callback instanceof \Closure) {
            // For closures
            return spl_object_hash($callback);
        } elseif (is_string($callback)) {
            // For function names
            return $callback;
        } else {
            // Fallback for other types
            return md5(serialize($callback));
        }
    }

    private function _RunCallbacks($callbacks, $args)
    {
        foreach ($callbacks as $callback)
            call_user_func($callback, $args);
    }

    private function _GetNextFallbackModel()
    {
        $isUsingFallback = false;
        $selectNextModel = false;
        foreach ($this->_fallbackModels as $fallbackModel)
        {
            if ($selectNextModel)
                return $fallbackModel;

            if ($fallbackModel->model == $this->_model->GetName())
            {
                $selectNextModel = true;
                $isUsingFallback = true;
            }
        }

        return $isUsingFallback ? null : $this->_fallbackModels[0];
    }

}