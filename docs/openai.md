# Using OpenAI models
This document contains details on how to use OpenAI models. It's important to understand that every AI model has its own capabilities and limitations.

- [Chat completitions and vision](#chat-completitions-and-vision)
- [Image generation](#image-generation)
- [Speech generation](#speech-generation)
- [Speech transcription and translation](#speech-transcription-and-translation)
- [Embeddings](#embeddings)
- [Moderation](#moderation)
- [Further reading](#further-reading)
<br>

## Chat completitions and vision
With the chat completitions you can communicate with the model using natural language. In addition you can set behaviour parameters, use tools and send files.

**API Endpoint** <br>
``https://api.openai.com/v1/chat/completions``

**Supported message roles** <br>
``system``, ``user``, ``assistant``

**Supported message types** <br>
``text``, ``link`` (images only)

**Supported tools** <br>
``function calling``

**Supported models:**
- openai-gpt-4o 
- openai-gpt-4o-mini
- openai-chatgpt-4o-latest
- openai-o1-preview
- openai-o1-mini
- openai-gpt-4
- openai-gpt-4-turbo
- openai-gpt-4-turbo-preview
<br>

**Simple chat**
```php
$thread = new AIpi\Thread('openai-gpt-4o', $my_openai_key);
$thread->AddMessage(new AIpi\Message('Hi, who are you?'), ['temperature' => 0.5]);

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Hello! I'm an AI language model created by OpenAI.
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Role play**
```php
$thread = new AIpi\Thread('openai-gpt-4o', $my_openai_key);
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
$thread = new Thread('openai-gpt-4o', $my_openai_key);
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
$thread = new Thread('openai-gpt4o', 'my_openai_key');

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

## Image generation
With the image models you can create or edit images from a text prompt.

**API Endpoint** <br>
- ``https://api.openai.com/v1/images/variations``
- ``https://api.openai.com/v1/images/edits``

**Supported message roles** <br>
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br>
``text``

**Supported tools** <br>
N/A

**Supported models**
- openai-dall-e-3
- openai-dall-e-2
<br>

**Variation**
```php
$thread = new Thread('openai-dall-e-2', $my_openai_key, ['n' => 1]);
$thread->AddMessage(new Message('Make me a photo of cute little kity.'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Contains a link for download
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Edit**
```php
$thread = new Thread('openai-dall-e-2', $my_openai_key, ['n' => 1]);

$photo = file_get_contents('https://png.pngtree.com/png-vector/20220704/ourmid/pngtree-pool-decorative-design-vector-clipart-transparent-background-png-image_5684983.png');
$thread->AddMessage(new Message('Make it rooftop pool. Add cityvew on the background.'));
$thread->AddMessage(new Message($photo, ['type' => MessageType::FILE, 'edit' => true]));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Contains a link for download
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br><br>

## Speech generation
With the audio models you can create speech from a text prompt.

**API Endpoint** <br> 
``https://api.openai.com/v1/audio/speech``

**Supported message roles** <br> 
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br> 
``text``

**Supported tools** <br> 
N/A

**Supported models**
- openai-tts-1
- openai-tts-1-hd
<br>

**Example**
```php
$thread = new Thread('openai-tts-1', $my_openai_key);
$thread->AddMessage(new Message('Hello, how are you?'));

$message = $thread->Run();
if ($message) 
{
    // The model message contains binary MP3 content
    file_put_contents('speech.mp3', $thread->GetLastMessage()->content);

    // Usage
    echo "speech.mp3 generated\r\n";
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br><br>

## Speech transcription and translation
With the audio models you can transcribe speech from audio file to text. In addition you can translate the speech directly to English.

**API Endpoint** <br>
- ``https://api.openai.com/v1/audio/transcriptions``
- ``https://api.openai.com/v1/audio/translations``

**Supported message roles** <br>
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br>
 ``file`` (mp3, mp4, mpeg, mpga, m4a, wav, and webm)

**Supported tools** <br>
 N/A

**Supported models**
- openai-whisper-1
<br>

**Transcription**
```php
$thread = new Thread('openai-whisper-1', $my_openai_key);

$audio = file_get_contents('https://audio-samples.github.io/samples/mp3/blizzard_unconditional/sample-3.mp3');
$thread->AddMessage(new Message($audio, ['type' => MessageType::FILE]));
//$thread->AddMessage(new Message('Prompt for improving the transcription.')); // optional

$message = $thread->Run();
if ($message) 
{
    // Transcription of the audio
    echo $thread->GetLastMessage()->content; 

    // Usage
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br>

**Translation**
```php
$thread = new Thread('openai-whisper-1', $my_openai_key);

$audio = file_get_contents('speech_german.mp3');
$thread->AddMessage(new Message($audio, ['type' => MessageType::FILE, 'translation' => true]));
//$thread->AddMessage(new Message('Prompt for improving the transcription.')); // optional

$message = $thread->Run();
if ($message) 
{
    // Transcription of the audio
    echo $thread->GetLastMessage()->content; 

    // Usage
    print_r($thread->GetUsage());
    echo "\r\n\r\n";
}
else echo $thread->GetLastError();
```
<br><br>


## Embeddings
Generate text embeddings for building RAG applications.

**API Endpoint** <br>
``https://api.openai.com/v1/embeddings``
 
**Supported message roles** <br>
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br>
``text``

**Supported tools** <br>
N/A

**Supported models**
- openai-text-embedding-3-small
- openai-text-embedding-3-large

**Example**
```php
$thread = new Thread('openai-text-embedding-3-large', $my_openai_key);
$thread->AddMessage(new Message('The red fox jumps over the lazy dog.'));

$message = $thread->Run();
if ($message) 
{
    echo $message->content."\r\n"; // Embeddings
}
else echo $thread->GetLastError();
```
<br><br>

## Moderation
Evaluate if content is safe and compliant.

**API Endpoint** <br>
``https://api.openai.com/v1/moderations``

**Supported message roles** <br>
``system`` and ``user`` (both will be merged into one single input)

**Supported message types** <br>
``text``

**Supported tools:** <br>
N/A

**Supported models**
- openai-omni-moderation-latest
- openai-text-moderation-latest
- openai-text-moderation-stable

**Example**
```php
$thread = new Thread('openai-omni-moderation-latest', $my_openai_key, []);
$thread->AddMessage(new Message('I hate you.'));
if ($thread->Run())
{
    echo $thread->GetLastMessage()->content; // json object with evaluation score
}
else echo $thread->GetLastError();
```
<br><br>

## Further reading
- [OpenAI API reference](https://platform.openai.com/docs/api-reference)
- [OpenAI API keys](https://platform.openai.com/api-keys)
