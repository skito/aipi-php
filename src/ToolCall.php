<?php

namespace AIpi;

class ToolCall
{
    public $toolname = '';
    public $args = [];
    
    public function __construct($toolname='', $args=[])
    {
        $this->toolname = $toolname;
        $this->args = $args;
    }

    public static function ParseArray($arr)
    {
        if ($arr == null) return null;
        if (is_object($arr)) $arr = (array)$arr;
        if (!is_array($arr)) return null;
     
        if (($arr['name'] ?? '') != '')
            return new ToolCall($arr['name'], $arr['args']);
        
        return null;
    }

    public static function ParseJSON($message)
    {
        $content = @json_decode($message->content) ?? new stdClass();
        if (($content->name ?? '') != '')
            return new ToolCall($content->name, $content->args);
        
        return null;
    }

}