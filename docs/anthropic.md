# Using Anthropic models
This document contains details on how to use Anthropic models. It's important to understand that every AI model has its own capabilities and limitations.

- [Chat completitions and vision](#chat-completitions-and-vision)
- [Further reading](#further-reading)
<br>

## Chat completitions and vision
With the chat completitions you can communicate with the model using natural language. In addition you can set behaviour parameters, use tools and send files.

**API Endpoint** <br>
``https://api.anthropic.com/v1/messages``

**Supported message roles** <br>
``system``, ``user``, ``assistant``

**Supported message types** <br>
``text``, ``file`` (images only)

**Supported tools** <br>
``function calling``

**Supported models:**
- ``anthropic-claude-3-5-sonnet-latest``
- ``anthropic-claude-3-5-haiku-latest``
- ``anthropic-claude-3-opus-latest``
<br>

**Simple chat**
```php
$thread = new AIpi\Thread('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
$thread->AddMessage(new AIpi\Message('Hi, who are you?'), ['temperature' => 0.5]);

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Hello! I'm Claude, an AI created by Anthropic.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Role play**
```php
$thread = new AIpi\Thread('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
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
$thread = new AIpi\Thread('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);
$thread->AddMessage(new Message('What\'s on the photo?'));

// Claude work with file uploads
$src = file_get_contents('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg');
$thread->AddMessage(new Message($src, ['type' => MessageType::FILE]));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // This is a photo of a fluffy orange/ginger cat that appears to be covering its face...
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
$thread = new AIpi\Thread('anthropic-claude-3-5-sonnet-latest', $my_anthropic_key);

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
- [Anthropic API reference](https://docs.anthropic.com/en/api-reference)
- [Anthropic API keys](https://console.anthropic.com/keys)
