# Add custom models
You can add custom models by creating a new class that implements the `IModel` interface and extends the `ModelBase` class.

## Filename
The filename should be the name of the vendor followed by the model name, and it should be placed in the `Models` folder.
Example:
- `openai-gpt.php` for all GPT models from OpenAI
- `openai-embeddings.php` for the OpenAI embeddings model
- `anthropic-claude.php` for the Claude family of models from Anthropic

## Structure
You can use either separate classes for each model, or a single common class for multiple models from the same vendor. Either way you must register each model separately.

### Separate classes for each model
```php
namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;

class OpenAI_GPT4o extends ModelBase implements IModel
{
    public function GetName() { return 'gpt-4o'; }    

    public function Call($apikey, $messages, $tools = [], $options=[], &$tokens=null)
    {
        $options = (object)$options;
        $tokens = ['input' => 0, 'output' => 0];

        /* 
            [INPUT]
            $apikey: string - The API key to use for the request
            $messages: array - An array of AIpi\Message objects
            $tools: array - An array of AIpi\Tool objects
            $options: object - An object containing any additional options
            $tokens: array - An array to store the number of tokens used in the request.

            [OUTPUT]
            The response from the model as an AIpi\Message object or NULL on error.
            => MessageRole::ASSISTANT if the model returned a regular response
            => MessageRole::TOOL if the model returned a tool call
            
            When the model returns a tool call, the message content must be in the following JSON format:
            {
                "calls": [
                    {
                        "name": "tool_name",
                        "args": { 
                            "arg_name": "arg_value" 
                        }
                    }
                ]
            }
        */
    }

    public static function Register() { parent::Register(new OpenAI_GPT4o()); }
}
OpenAI_GPT4o::Register();

```

### Common class for all vendor models
```php
namespace AIpi\Models;

use AIpi\ModelBase;
use AIpi\IModel;

class OpenAI_GPT extends ModelBase implements IModel
{
    private $_name = '';
    private static $_supported = ['gpt-4o', 'gpt-4o-mini'];

    public function GetName() { return $this->_name; }
    public static function GetSupported() { return self::$_supported; }    

    public function __construct($name = 'gpt-4o')
    {
        $this->_name = in_array($name, self::$_supported) ? $name : 'gpt-4o';
    }

    public function Call($apikey, $messages, $tools = [], $options=[], &$tokens=null)
    {
        $options = (object)$options;
        $tokens = ['input' => 0, 'output' => 0];
        
        // ...
    }

    public static function Register()
    {
        // THE KEY MOMENT
        // Register each supported model
        foreach (self::$_supported as $modelName) {
            parent::Register(new OpenAI_GPT($modelName));
        }
    }
}

OpenAI_GPT::Register();
```
