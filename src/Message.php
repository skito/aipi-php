<?php

namespace AIpi;

class Message
{
    public $role = MessageRole::USER;
    public $content = null;
    public $attributes = [
        'type' => MessageType::TEXT,
        'role' => MessageRole::USER
    ];

    public function __construct($content, $roleOrAttributes = MessageRole::USER)
    {
        $this->content = $content;
        if (is_array($roleOrAttributes)) {
            $this->attributes = array_merge($this->attributes, $roleOrAttributes);
            $this->role = $this->attributes['role'];
        }
        elseif (is_string($roleOrAttributes)) {
            $this->role = $roleOrAttributes;
            $this->attributes['role'] = $roleOrAttributes;
        }
    }
}

