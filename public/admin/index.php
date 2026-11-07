<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Yef\Environment;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

// Application root (the "core" directory). Lives above the document root when the hosting allows it.
$root = dirname(__DIR__, 2);

require_once $root . '/src/bootstrap.php';

$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new ErrorHandler(
        new Logger(
            [
                new StreamTarget()->setLevels([
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                    LogLevel::WARNING,
                ]),
            ],
        ),
        new HtmlRenderer(),
    ),
);
$runner->run();
