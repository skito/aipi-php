<?php

require_once '../src/autoloader.php';

$my_openai_key = 'my_openai_key';
$my_anthropic_key = 'my_anthropic_key';

/* ******************** */
/* Let's start with GPT */
/* ******************** */
$thread = new AIpi\Thread('openai-gpt-4o', $my_openai_key);
$thread->AddMessage(new AIpi\Message('Hi, who are you?'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Hello! I'm an AI language model created by OpenAI.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();


/* ************************************* */
/* Now let's switch the Thread to Sonnet */
/* ************************************* */
$thread->ChangeModel('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
$thread->AddMessage(new AIpi\Message('Is that true?'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // I want to be direct with you. I'm Claude, an AI created by Anthropic.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();


// Debug full communication
// print_r($thread->messages);
