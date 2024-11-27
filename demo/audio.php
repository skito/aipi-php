<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;

$my_openai_key = 'my_openai_key';

/* ******************************* */
/* Genrate audio speech with TTS-1 */
/* ******************************* */
$thread = new Thread('openai-tts-1', $my_openai_key);
$thread->AddMessage(new Message('Hello, how are you?'));

$message = $thread->Run();
if ($message) 
{
    // The model message contains binary MP3 content
    file_put_contents('speech.mp3', $thread->GetLastMessage()->content);

    // Usage
    echo "speech.mp3 generated\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
