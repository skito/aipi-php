<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageType;

$my_google_key = 'my_google_key';
$my_xai_key = 'my_xai_key';

/* ************************************************/
/* Analysing files with Gemini ********************/
/* It supports documents, images, audio and video */
/* ************************************************/
$thread = new Thread('google-gemini-1.5-flash', $my_google_key);

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



/* **********************************/
/* Analysing files with Grok ********/
/* It supports documents and images */
/* **********************************/
$thread = new Thread('xai-grok-vision-beta', $my_xai_key);

// Send PDF file as URL for understanding. Grok work only with URLs for documents.
$pdfURL = 'https://www.irs.gov/pub/irs-pdf/f4506.pdf';
$thread->AddMessage(new Message('What are the required fields in this form? '.$pdfURL, ['type' => MessageType::TEXT]));


$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
