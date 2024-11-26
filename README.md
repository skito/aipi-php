# AIpi - Universal API client for common AI models
Simple lightweight PHP library for interacting with common AI models, that provides universal interface for all models.

⚡ No dependencies, just native PHP. cURL required.<br>
🤖 Multimodel support. Unified API for all models.<br>
🔧 Tools support & agentic - autoresolve tools calls with callbacks.<br>
🚀 Extendable - easy to add your own models and tools.<br>
📦 No composer required! But composer compatible.<br>

<br>

## Setup
You can choose to use the autoloader or include the full source at once.

Using the autoloader:
```php
require_once 'src/autoload.php';
```
Or include full source at once:
```php
require_once 'src/loadall.php';
```
Or using composer:
```bash
composer require skito/aipi
```

<br>

## Quick start

### [BASIC USAGE]
```php
/* ******************** */
/* Let's start with GPT */
/* ******************** */
$thread = new AIpi\Thread('openai-gpt-4o', 'my_openai_key');
$thread->AddMessage(new AIpi\Message('Hi, who are you?'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Hello! I'm an AI language model created by OpenAI.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();


/* ************************************* */
/* Now let's switch the Thread to Sonnet */
/* ************************************* */
$thread->ChangeModel('anthropic-claude-3-5-sonnet-latest', 'my_anthropic_key');
$thread->AddMessage(new AIpi\Message('Is that true?'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // I want to be direct with you. I'm Claude, an AI created by Anthropic.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();


// Debug full communication
// print_r($thread->messages);
```

**COMMUNICATION SEQUENCE**
```
1. [APP] (message)--> [AI MODEL]
2. [APP] <--(message) [AI MODEL]
```
<br>

### [WITH TOOLS]
Use tools for agentic behaviour.

#### Custom tool example
```php
use AIpi\Thread;
use AIpi\Message;
use AIpi\Tools\FunctionCall;

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
$thread = new Thread('openai-gpt4o', 'my_openai_key');
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
```

#### Toolbox example
```php
use AIpi\Thread;
use AIpi\Message;
use AIpi\Tools\FunctionCall;

/** ********************************** */
/** Create a new thread with the tool */
/** ********************************** */
$thread = new Thread('openai-gpt4o', 'my_openai_key');

// Load OpenMeto tool from the toolbox
$thread->AddTool(new AIpi\Toolbox\OpenMeteo());

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
```

**COMMUNICATION SEQUENCE**
```
1. [APP] (message)--> [AI MODEL]
2. [APP] <-----(call) [AI MODEL]
3. [APP] (result)---> [AI MODEL]
4. [APP] <--(message) [AI MODEL]
```
<br>

### [VISION]
Depending on the model, you can work with binary data or links.

#### GPT
```php
/* ************/
/* GPT vision */
/* ************/
use AIpi\Thread;
use AIpi\Message;

$thread = new Thread('openai-gpt-4o', 'my_openai_key');
$thread->AddMessage(new Message('What\'s on the photo?'));

// GPT work with links
$thread->AddMessage(new Message('https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // The photo shows an orange tabby cat.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```

#### Claude
```php
/* ***************/
/* Claude vision */
/* ***************/
use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageType;

$thread = new Thread('anthropic-claude-3-5-sonnet-latest', 'my_anthropic_key');
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

#### Gemini
```php
/* ***************/
/* Gemini vision */
/* ***************/
use AIpi\Thread;
use AIpi\Message;
use AIpi\MessageType;

$thread = new AIpi\Thread('google-gemini-1.5-flash', 'my_google_key');
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

### [MORE EXAMPLES]
For more examples, please refer to the models docs and the [demo](demo) folder.

<br>

## Tools and Toolbox
Tools are definitions of functions that can be called by the AI model. You can make your own tool definitions such as ``Tools/FunctionCall`` to be used in the thread.

Toolbox is a collection of predefined tool configurations. You can use the ones that come with this package ormake your own.

Learn more here: [Add Tools](docs/add_tools.md)

<br>

## Constructors and common options
You have different options to create a new thread and message.

### Thread
```php

/***************/
/* Constructor */
/***************/
$thread = new AIpi\Thread($model, $key, [$options]);
$thread = new AIpi\Thread('openai-gpt-4o', $key, ['temperature' => 0.5, 'top_p' => 1]); // According to the model options


/***********/
/* Running */
/***********/

// Returns Message object or null if error
$thread->Run(); 

// If there are tools and the model is requesting additional input, 
// the thread will call the tools and continue with the iteration,
// until the model is ready with the response
$thread->Run($autocomplete=true); 

```

### Message
```php
/***************/
/* Constructors */
/***************/
$message = new AIpi\Message($content, [$roleOrAttributes]); // default role is user
$message = new AIpi\Message('Hello!', AIpi\MessageRole::USER);
$message = new AIpi\Message('Hello!', ['type' => AIpi\MessageType::TEXT]);
$message = new AIpi\Message('Hello!', ['role' => AIpi\MessageRole::USER, 'type' => AIpi\MessageType::TEXT]);
```

<br>

## Supported models
Supported models by vendors.

**OpenAI**
- gpt-4o
- gpt-4o-mini
- chatgpt-4o-latest
- o1-preview
- o1-mini
- gpt-4
- gpt-4-turbo
- gpt-4-turbo-preview
- text-embedding-3-large
- text-embedding-3-small
- text-embedding-ada-002
- dall-e-3
- dall-e-2
- tts-1
- tts-1-hd
- whisper-1
- omni-moderation-latest
- text-moderation-latest
- text-moderation-stable

**Anthropic**
- claude-3-5-sonnet-latest
- claude-3-5-haiku-latest
- claude-3-opus-latest

**Google**
- gemini-1.5-flash
- gemini-1.5-flash-8b
- gemini-1.5-pro
<br>

### Add Models
You can add your own models by creating a new class that extends `AIpi\ModelBase` and implementing the `Call` method.
Learn more here: [Add Models](docs/add_models.md)

<br>

## Requests and support
For any feedback, requests, questions or issues, please open an issue on GitHub.
