<?php

namespace AIpi;

interface IModel
{
    public function Call($apikey, $messages, $tools = [], $options=[], &$tokens=null);
    public function GetName();
    public static function GetSupported();

}

