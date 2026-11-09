<?php

declare(strict_types=1);

use Yef\Admin\Http\SecurityHeaders;
use Yef\Admin\Http\StripScriptName;
use Yef\Admin\NotFound\NotFoundHandler;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Session\SessionMiddleware;
use Yiisoft\Yii\Http\Application;

return [
    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        ErrorCatcher::class,
                        SecurityHeaders::class,
                        StripScriptName::class,
                        SessionMiddleware::class,
                        CsrfTokenMiddleware::class,
                        Router::class,
                    ],
                ],
            ]),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],
];
