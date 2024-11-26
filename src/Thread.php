<?php

namespace AIpi;

class Thread
{
    public $messages = [];
    public $tools = [];
    public $options = null; // object of key-value pairs (option name => option value)

    private $_model = null;
    private $_apikey = null;
    private $_inputTokens = 0;
    private $_outputTokens = 0;
    private $_files = 0;

    public function __construct($model, $apikey, $opts=[])
    {
        $this->_apikey = $apikey;  
        $this->options = (object)array_merge([
            'temperature' => 0.5,
            'throwOnError' => true
        ], (array)$opts);

        $this->ChangeModel($model, $apikey);
    }

    public function ChangeModel($model, $apikey)
    {
        $this->_apikey = $apikey;

        if (is_string($model))
            $this->_model = ModelBase::Get($model);
        elseif (is_object($model) && $model instanceof IModel)
            $this->_model = $model;

        if ($this->_model == null)
            throw new \Exception('Model not supported or name typo.');

        $this->_inputTokens = 0;
        $this->_outputTokens = 0;
        $this->_files = 0;
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
            'files' => $this->_files
        ];
    }    

    public function AddMessage($message)
    {
        if ($message instanceof Message)
            $this->messages[] = $message;
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

    public function RunOnce()
    {
        return $this->Run(false);
    }

    public function Run($autocomplete=true)
    {
        $model = $this->GetModel();
        if ($this->_model == null)
            throw new \Exception('Model not assigned.');

        $tokens = null;
        $message = $model->Call(
            $this->_apikey, 
            $this->messages, 
            $this->tools, 
            $this->options, 
            $tokens
        );

        if (!$message)
        {
            if ($this->options['throwOnError'])
                throw new \Exception('Model returned no message');
            return null;
        }

        $this->messages[] = $message;

        $tokens = (object)$tokens;
        $this->_inputTokens += $tokens->input ?? 0;
        $this->_outputTokens += $tokens->output ?? 0;
        $this->_files += $tokens->files ?? 0;

        if ($message->role == MessageRole::TOOL && $autocomplete)
        {
            $calls = @json_decode($message->content) ?? (object)['calls' => []];
            foreach ($calls->calls as $call)
            {
                $toolcall = ToolCall::ParseArray($call);
                if ($toolcall)
                {
                    foreach ($this->tools as $tool)
                    {
                        if ($tool->GetName() == $toolcall->toolname)
                        {
                            $result = $tool->RunCallback($toolcall->args);
                            if ($result !== null)
                            {
                                $message = new Message('', MessageRole::RESULT);
                                $message->content = json_encode([
                                    'tool_result' => [
                                        'tool_name' => $tool->GetName(),
                                        'tool_type' => $tool->GetType(),
                                        'result' => $result
                                    ]
                                ]);
                                $this->messages[] = $message;
                            }
                        }
                    }
                }
            }
            
            if ($this->GetLastMessage()->role == MessageRole::RESULT)
                $message = $this->Run($autocomplete);
            
        }
        
        return $message;
    }

}