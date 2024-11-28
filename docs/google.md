# Using Google DeepMind models
This document contains details on how to use Google DeepMind models. It's important to understand that every AI model has its own capabilities and limitations.

- [Chat completitions and vision](#chat-completitions-and-vision)
- [Embeddings](#embeddings)
- [Further reading](#further-reading)
<br>

## Chat completitions and vision
With the chat completitions you can communicate with the model using natural language. In addition you can set behaviour parameters, use tools and send files.

**API Endpoint** <br>
``https://api.deepmind.com/v1/messages``

**Supported message roles** <br>
``system``, ``user``, ``assistant``

**Supported message types** <br>
``text``, ``file`` (images, audio, video and documents)

**Supported tools** <br>
``function calling``

**Supported models:**
- ``google-gemini-1.5-flash``
- ``google-gemini-1.5-flash-8b``
- ``google-gemini-1.5-pro``
<br>

**Simple chat**
```php
$thread = new AIpi\Thread('google-gemini-1.5-flash', $my_google_key);
$thread->AddMessage(new AIpi\Message('Hi, who are you?'), ['temperature' => 0.5]);

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // I am a large language model, trained by Google.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Role play**
```php
$thread = new AIpi\Thread('google-gemini-1.5-flash', $my_google_key);
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
$thread = new AIpi\Thread('google-gemini-1.5-flash', $my_google_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Gemini work with file uploads
$src = file_get_contents('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg');
$thread->AddMessage(new Message($src, ['type' => MessageType::FILE]));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // That's a fluffy ginger cat grooming itself...
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
$thread = new AIpi\Thread('google-gemini-1.5-flash', $my_google_key);

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

## Embeddings
Generate text embeddings for building RAG applications.

**API Endpoint** <br>
``https://api.deepmind.com/v1/embeddings``    
 
**Supported message roles** <br>
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br>
``text``

**Supported tools** <br>
N/A

**Supported models**
- ``google-text-embedding-004``

**Example**
```php
$thread = new Thread('google-text-embedding-004', $my_google_key);
$thread->AddMessage(new Message('The red fox jumps over the lazy dog.'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Embeddings
}
else echo $thread->GetLastError();
```
<br><br>

## Further reading
- [Google AI documentation](https://ai.google.dev/docs)
- [Google AI API keys](https://makersuite.google.com/app/apikeys)

