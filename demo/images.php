<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;

$my_openai_key = 'my_openai_key';

/* ************************* */
/* Genrate photo with DALL-E */
/* ************************* */
$thread = new Thread('openai-dall-e-2', $my_openai_key, ['n' => 1]);
$thread->AddMessage(new Message('Make me a photo of cute little kity.'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Contains a link for download
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
