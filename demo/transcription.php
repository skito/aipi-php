<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;

$my_openai_key = 'my_openai_key';

/* ************************************* */
/* Generate transcription with Whisper-1 */
/* ************************************* */
$thread = new Thread('openai-whisper-1', $my_openai_key);
$thread->AddMessage(new Message(file_get_contents('speech.mp3'), ['type' => MessageType::FILE]));

$message = $thread->Run();
if ($message) 
{
    // Transcription of the audio
    echo $thread->GetLastMessage()->content; 

    // Usage
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();





