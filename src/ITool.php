<?php

namespace AIpi;

interface ITool
{
    public function GetType();
    public function GetName();
    public function RunCallback($args);
}