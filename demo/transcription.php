<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageType;

$my_openai_key = 'my_openai_key';

/* ************************************* */
/* Generate transcription with Whisper-1 */
/* ************************************* */
$thread = new Thread('openai-whisper-1', $my_openai_key);
$audio = file_get_contents('https://audio-samples.github.io/samples/mp3/blizzard_unconditional/sample-3.mp3');
$thread->AddMessage(new Message($audio, ['type' => MessageType::FILE]));

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





