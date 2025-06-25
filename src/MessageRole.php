<?php

namespace AIpi;

abstract class MessageRole
{
    const USER = "user";
    const ASSISTANT = "assistant"; 
    const SYSTEM = "system";
    const TOOL = "tool";
    const RESULT = "result";
    const HUMAN = "human";
}