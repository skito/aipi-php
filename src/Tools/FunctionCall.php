<?php

namespace AIpi\Tools;

class FunctionCall implements \AIpi\ITool
{
    public $name = '';
    public $description = '';
    public $properties = []; // array of key-value pairs (property_name => type)
    public $property_descriptions = []; // array of key-value pairs (property_name => description)
    public $property_required = []; // array of property names (strings)
    public $callback = null; // callback function (callable)

    public function __construct($name='', $description='', $properties=[], $attributes=[], $callback=null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->properties = $properties;  
        $this->property_descriptions = $attributes['descriptions'] ?? [];
        $this->property_required = $attributes['required'] ?? [];
        $this->callback = $callback;
    }

    public function GetType()
    {
        return 'function';
    }   

    public function GetName()
    {
        return $this->name;
    }   

    public function RunCallback($args)
    {
        if ($this->callback !== null)
            return call_user_func($this->callback, $args);
        
        return null;
    }

    public function Init($name='')
    {
        return new FunctionCall($name);
    }

    /* **********************************************  */
    /* "With" methods for more readable initialization */
    /* **********************************************  */
    public function WithName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function WithDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function WithProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    public function WithAttributes($attributes)
    {
        $this->property_descriptions = $attributes['descriptions'] ?? [];
        $this->property_required = $attributes['required'] ?? [];
        return $this;
    }

    public function WithCallback($callback)
    {
        $this->callback = $callback;
        return $this;
    }
}