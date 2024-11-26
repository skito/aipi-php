<?php

require_once '../src/autoloader.php';

use AIpi\Message;
use AIpi\MessageRole;

$my_openai_key = 'my-openai-key';
$my_anthropic_key = 'my-anthropic-key';

/* ******************** */
/* Let's start with GPT */
/* ******************** */
$thread = new AIpi\Thread('openai-gpt-4o', $my_openai_key);
$thread->AddMessage(new Message('You are helpful finance assitant?', MessageRole::SYSTEM));
$thread->AddMessage(new Message('I need a financial advice?', MessageRole::USER));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();


/* ************************************* */
/* Now let's switch the Thread to Sonnet */
/* ************************************* */
$thread->ChangeModel('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
$thread->AddMessage(new Message('I need to start saving money for my retirement. What should I do?', MessageRole::USER));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();

// Debug full communication
// print_r($thread->messages);