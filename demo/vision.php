<?php

require_once '../src/autoloader.php';

use AIpi\Message;
use AIpi\MessageType;

$my_openai_key = 'my_openai_key';
$my_anthropic_key = 'my_anthropic_key';
$my_google_key = 'my_google_key';

/* ************/
/* GPT vision */
/* ************/
$thread = new AIpi\Thread('openai-gpt-4o', $my_openai_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// GPT work with links
$thread->AddMessage(new Message('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // The photo shows an orange tabby cat.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();



/* ***************/
/* Claude vision */
/* ***************/
$thread = new AIpi\Thread('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Claude work with file uploads
$src = file_get_contents('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg');
$thread->AddMessage(new Message($src, ['type' => MessageType::FILE]));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // This is a photo of a fluffy orange/ginger cat that appears to be covering its face...
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();



/* ***************/
/* Gemini vision */
/* ***************/
$thread = new AIpi\Thread('google-gemini-1.5-flash', $my_google_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Gemini work with file uploads
$src = file_get_contents('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg');
$thread->AddMessage(new Message($src, ['type' => MessageType::FILE]));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // That's a fluffy ginger cat grooming itself...
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
