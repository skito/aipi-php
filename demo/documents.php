<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageType;

$my_google_key = 'my_google_key';

/* ************************************************/
/* Analysing files with Gemini ********************/
/* It supports documents, images, audio and video */
/* ************************************************/
$thread = new Thread('google-gemini-1.5-flash', $my_google_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Send PDF file for understanding
$pdf = file_get_contents('https://www.irs.gov/pub/irs-pdf/f4506.pdf');
$thread->AddMessage(new Message($pdf, ['type' => MessageType::FILE, 'media_type' => 'application/pdf']));
$thread->AddMessage(new Message('How to fill out this form for my personal tax return? ', ['type' => MessageType::TEXT]));


$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();