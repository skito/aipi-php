<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;

$my_openai_key = 'my_openai_key';

/* ****************************** */
/* Generate emeddings with OpenAI */
/* ****************************** */
$thread = new Thread('openai-text-embedding-3-large', $my_openai_key, []);
$thread->AddMessage(new Message('The red fox jumps over the lazy dog.'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Embeddings
}
else echo $thread->GetLastError();
