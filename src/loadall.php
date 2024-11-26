<?php

$baseDir = dirname(__FILE__);

// Load base classes
require_once $baseDir.'/IModel.php';
require_once $baseDir.'/ITool.php';
require_once $baseDir.'/Message.php';
require_once $baseDir.'/MessageRole.php';
require_once $baseDir.'/ModelBase.php';
require_once $baseDir.'/Thread.php';
require_once $baseDir.'/ToolCall.php';

// Load tools
require_once $baseDir.'/Tools/FunctionCall.php';

// Load toolbox
require_once $baseDir.'/Toolbox/OpenMeteo.php';
