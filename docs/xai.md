# Using xAI models
This document contains details on how to use xAI models. It's important to understand that every AI model has its own capabilities and limitations.

- [Chat completitions and vision](#chat-completitions-and-vision)
- [Further reading](#further-reading)
<br>

## Chat completitions and vision
With the chat completitions you can communicate with the model using natural language. In addition you can set behaviour parameters, use tools and send files.

**API Endpoint** <br>
``https://api.x.ai/v1/chat/completions``

**Supported message roles** <br>
``system``, ``user``, ``assistant``

**Supported message types** <br>
``text``, ``link`` (images only)

**Supported tools** <br>
``function calling``

**Supported models:**
- xai-grok-beta
- xai-grok-vision-beta
<br>

**Simple chat**
```php
$thread = new AIpi\Thread('xai-grok-beta', $my_xai_key);
$thread->AddMessage(new AIpi\Message('Hi, who are you?'), ['temperature' => 0.5]);

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Well, hello there! I'm Grok, the AI with a touch of humor by xAI.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Role play**
```php
$thread = new AIpi\Thread('xai-grok-beta', $my_xai_key);
$thread->AddMessage(new AIpi\Message('You are helpfull assitant of ecommerce shop?', MessageRole::SYSTEM));
$thread->AddMessage(new AIpi\Message('Hi, I have problem with my order.', MessageRole::USER));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n\r\n";
    print_r($thread->GetUsage());
    print_r($thread->messages);
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Vision**
```php
// xai-grok-vision-beta has vision capabilities
$thread = new AIpi\Thread('xai-grok-vision-beta', $my_xai_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Send photo link
$url = 'https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg';
$thread->AddMessage(new Message($url, ['type' => MessageType::LINK]));

// or alternatively send as binary data
// $src = file_get_contents($url);
// $thread->AddMessage(new Message($src, ['type' => MessageType::FILE, 'media_type' => 'image/jpeg']));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // The photo shows an orange tabby cat.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Documents**
```php
/* ************************************************/
/* Analysing files with Grok **********************/
/* It supports documents, images, audio and video */
/* ************************************************/
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
```
<br>

**Tools (function calling)**
```php

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
// Tools are only supported by xai-grok-beta
$thread = new AIpi\Thread('xai-grok-beta', $my_xai_key);

// Load OpenMeto tool from the toolbox
$thread->AddTool($weatherInfo);

// Or you can get one from the toolbox
//$thread->AddTool(new AIpi\Toolbox\OpenMeteo());

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
```
<br><br>

## Further reading
- [xAI API reference](https://docs.x.ai/api/)
- [xAI API keys](https://console.x.ai/)
