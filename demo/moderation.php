<?php

require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;

$my_openai_key = 'my_openai_key';

/* ****************************** */
/* Message moderation with OpenAI */
/* ****************************** */
$thread = new Thread('openai-omni-moderation-latest', $my_openai_key, []);
$thread->AddMessage(new Message('I hate you.'));
if ($thread->Run())
{
    echo $thread->GetLastMessage()->content; // json object with evaluation score
}
else echo $thread->GetLastError();
