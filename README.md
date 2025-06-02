# AIpi - Universal API client for common AI models
![GitHub release (latest by date)](https://img.shields.io/badge/php-%3E%3D7.4-blue)
![GitHub release (latest by date)](https://img.shields.io/badge/-OpenAI-16a180)
![GitHub release (latest by date)](https://img.shields.io/badge/-Anthropic-ebdbbc)
![GitHub release (latest by date)](https://img.shields.io/badge/-Google%20DeepMind-4286f5)
![GitHub release (latest by date)](https://img.shields.io/badge/-xAI-050505)

Simple lightweight PHP library for interacting with common AI models, that provides universal interface.

🤖 Multimodel support. Unified API for all models.<br>
🔧 Tools support & agentic - autoresolve tool calls with callbacks.<br>
🚀 Extendable - easy to add your own models and tools.<br>
⚡ No dependencies, just native PHP. cURL required.<br>
📦 No composer required! But composer compatible.<br>
<br>

**Quick links**
- [Setup](#setup)
- [Quick start](#quick-start)
- [Tools and Toolbox](#tools-and-toolbox)
- [Constructors and common options](#constructors-and-common-options)
- [Supported models](#supported-models)
- [Requests and support](#requests-and-support)

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

// Send photo link
$url = 'https://onlinejpgtools.com/images/examples-onlinejpgtools/orange-tabby-cat.jpg';
$thread->AddMessage(new Message($url, ['type' => MessageType::LINK]));

// or alternatively send as binary data
// $src = file_get_contents($url);
// $thread->AddMessage(new Message($src, ['type' => MessageType::FILE, 'media_type' => 'image/jpeg']));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // The photo features a fluffy orange tabby cat sitting ...
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

### [Embeddings]
```php
$thread = new AIpi\Thread('openai-text-embedding-3-large', 'my_openai_key');
$thread->AddMessage(new AIpi\Message('The quick brown fox jumps over the lazy dog.'));

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

### [MORE EXAMPLES]
For more examples, please refer to the models docs and the [demo](demo) folder.<br>
Currently supported: ``chat/completitions``, ``vision``, ``image generation``, ``audio generation``, ``audio transcription``, ``document vision``, ``embeddings``, ``moderations``.

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

**OpenAI** [(examples)](docs/openai.md)
- openai-gpt-4
- openai-gpt-4-turbo
- openai-gpt-4-turbo-preview
- openai-gpt-4.1
- openai-gpt-4o
- openai-chatgpt-4o-latest
- openai-gpt-4o-mini
- openai-gpt-4.1-mini
- openai-gpt-4.1-nano
- openai-gpt-4.1-nano
- openai-o1
- openai-o1-pro
- openai-o1-mini
- openai-o1-preview
- openai-o3
- openai-o3-mini
- openai-o4-mini
- openai-gpt-4o-mini-search-preview
- openai-gpt-4o-search-preview
- openai-text-embedding-3-large
- openai-text-embedding-3-small
- openai-text-embedding-ada-002
- openai-dall-e-3
- openai-dall-e-2
- openai-gpt-image-1
- openai-tts-1
- openai-tts-1-hd
- openai-gpt-4o-mini-tts
- openai-whisper-1
- openai-gpt-4o-transcribe
- openai-gpt-4o-mini-transcribe
- openai-omni-moderation-latest
- openai-text-moderation-latest
- openai-text-moderation-stable

**Anthropic** [(examples)](docs/anthropic.md)
- anthropic-claude-3-opus-latest
- anthropic-claude-3-5-haiku-latest
- anthropic-claude-3-5-sonnet-latest
- anthropic-claude-3-7-sonnet-latest
- anthropic-claude-opus-4-0
- anthropic-claude-sonnet-4-0

**Google DeepMind** [(examples)](docs/google.md)
- google-gemini-1.5-flash
- google-gemini-1.5-flash-8b
- google-gemini-1.5-pro
- google-text-embedding-004
- google-gemini-2.0-flash
- google-veo-2.0-generate-001
- google-imagen-3.0-generate-002
- google-gemini-2.0-flash-lite
- google-gemini-2.0-flash-preview-image-generation
- google-gemini-2.0-flash
- google-gemini-2.5-pro-preview-tts
- google-gemini-2.5-pro-preview-05-06
- google-gemini-2.5-flash-preview-tts
- google-gemini-2.5-flash-preview-native-audio-dialog
- google-gemini-2.5-flash-exp-native-audio-thinking-dialog
- google-gemini-2.5-flash-preview-05-20

**xAI** [(examples)](docs/xai.md)
- xai-embedding-beta
- xai-grok-beta
- xai-grok-vision-beta
- xai-grok-2-latest
- xai-grok-2-vision-latest
- xai-grok-3-mini-fast-latest
- xai-grok-3-mini-latest
- xai-grok-3-fast-latest
- xai-grok-3-latest
<br>

### Add Models
You can add your own models by creating a new class that extends `AIpi\ModelBase` and implementing the `Call` method.
Learn more here: [Add Models](docs/add_models.md)

**NOTE** Most models are using common vendor API. If there is new generation model not yet updated in this library you can manually add it to the list with supported models inside the `Models` folder.

<br>

## Requests and support
For any feedback, requests, questions or issues, please open an issue on GitHub.<br>
For DMs [dimita7atanasov](https://x.com/dimita7atanasov)
