<?php

/*
-----COMMUNICATION SEQUENCE-----
1. [APP] (message)--> [AI MODEL]
2. [APP] <-----(call) [AI MODEL]
3. [APP] (result)---> [AI MODEL]
4. [APP] <--(message) [AI MODEL]
*/


require_once '../src/autoloader.php';

use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageRole;
use AIpi\Tools\FunctionCall;

$my_openai_key = 'my-openai-key';

/** ************************** */
/** Define simple weather tool */
/** ************************** */
$weatherInfo = new FunctionCall(
    // Name
    'get_weather_info',
    // Description
    'Get weather info by city name and country code.',
    // Accepted properties
    [   
        'city' => 'string',
        'countryCode' => 'string',
    ],
    // Property attributes
    [
        'required' => ['city', 'countryCode'],
        'descriptions' => [
            'city' => 'The city name.',
            'countryCode' => 'The country code.',
        ]
    ],
    // Callback function
    function($args) {
        return ['weather' => 'sunny'];
    }
);

/** ********************************** */
/** Create a new thread with the tool */
/** ********************************** */
$thread = new Thread('openai-gpt4o', $my_openai_key);
$thread->AddTool($weatherInfo);
$thread->AddMessage(new Message('You are a helpful assistant that can get weather info.', MessageRole::SYSTEM));
$thread->AddMessage(new Message('What is the weather right now in LA?', MessageRole::USER));
$message = $thread->Run();
if ($message)
{
    echo 'ASSISTANT: '.$message->content."\r\n";
    print_r($thread->GetUsage());
}
else 
{
    echo $thread->GetLastError();
}


// Debug full communication
// print_r($thread->messages);
