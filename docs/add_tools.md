# Add custom tools
Most common tool is `FunctionCall` which represents a callback function that can be called by the AI model. That's already included in this package. However with the rapid development of AI technologies, you might need to create your own tool to work with specific models.

You can add custom tools by creating a new class that implements the `ITool` interface.

## Filename
The filename should be the name of the tool, e.g. ``MyTool.php`` and placed in the ``Tools`` folder.


# Predifine tools configurations
Usually you will use and define your tools inline with the code. However if you want to make reusable tools configurations, you can predefine and place them in the ``Toolbox`` folder.

## Skeleton
```php
namespace AIpi\Toolbox;

class MyFooBarTool extends \AIpi\Tools\FunctionCall
{
    public function __construct()
    {
        parent::__construct(
            'MyFooBarTool', 
            'My tool makes something great with foo and bar.', 
            [   
                'foo' => 'number',
                'bar' => 'number',
            ], 
            [
                'required' => ['foo', 'bar'],
                'descriptions' => [
                    'foo' => '...',
                    'bar' => '...'
                ]
            ],
            [$this, 'DoSomethingGreat']   
        );
    }

    public function DoSomethingGreat($args)
    {
        $foo = $args->foo ?? '';
        $bar = $args->bar ?? '';

        return $foo.' and '.$bar;
    }
}

```

## Filename
The filename should be the name of the tool definition, e.g. ``MyFooBarTool.php`` and placed in the ``Toolbox`` folder.