<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Yef\Admin\Authentication\SessionAuthentication;
use Yef\Admin\Authentication\UserIdentityRepository;
use Yef\Admin\Http\CsrfFailureHandler;
use Yef\Admin\Http\StripScriptName;
use Yef\Admin\View\AdminView;
use Yef\Application\User\FindUser;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\Session;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\RateLimiter\Counter;
use Yiisoft\Yii\RateLimiter\CounterInterface;
use Yiisoft\Yii\RateLimiter\Storage\SimpleCacheStorage;

/** @var array $params */

$admin = $params['yef']['admin'];
$https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
$script = is_string($_SERVER['SCRIPT_NAME'] ?? null) ? $_SERVER['SCRIPT_NAME'] : '';
$cookiePath = in_array(dirname($script), ['/', '.', ''], true) ? '/' : dirname($script);

return [
    // The cookie lives for the browser session, is invisible to scripts and is sent only to the admin's own address.
    SessionInterface::class => [
        'class' => Session::class,
        '__construct()' => [
            [
                'name' => 'yef_admin',
                'save_path' => dirname(__DIR__, 3) . '/var/sessions',
                'use_strict_mode' => 1,
                'gc_maxlifetime' => $admin['sessionIdleTimeout'],
                'cookie_httponly' => 1,
                'cookie_samesite' => 'Lax',
                'cookie_path' => $cookiePath,
                'cookie_secure' => $https ? 1 : 0,
            ],
            null,
        ],
        'reset' => function (): void {
            $this->sessionId = null;
            $this->close();
        },
    ],
    CurrentUser::class => [
        'withSession()' => [Reference::to(SessionInterface::class)],
        'withAuthTimeout()' => [$admin['sessionIdleTimeout']],
    ],
    IdentityRepositoryInterface::class => UserIdentityRepository::class,
    Authentication::class => [
        '__construct()' => ['authenticationMethod' => Reference::to(SessionAuthentication::class)],
    ],
    StripScriptName::class => [
        '__construct()' => ['scriptNameInUrls' => $admin['scriptNameInUrls']],
    ],
    CsrfTokenMiddleware::class => [
        '__construct()' => ['failureHandler' => Reference::to(CsrfFailureHandler::class)],
    ],
    // Attempts at the sign-in and setup forms are limited per address; see "yef.admin" in config/common/params.php.
    CounterInterface::class => static fn(FileCache $cache): Counter => new Counter(
        new SimpleCacheStorage($cache),
        // The counter lets limit - 1 requests through, so one more is asked for.
        $admin['attempts'] + 1,
        $admin['attemptPeriod'],
    ),
    AdminView::class => static fn(
        Aliases $aliases,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenInterface $csrfToken,
        FlashInterface $flash,
        CurrentUser $currentUser,
        FindUser $findUser,
        ResponseFactoryInterface $responseFactory,
    ): AdminView => new AdminView(
        $aliases->get('@src/Admin/templates'),
        $aliases->get('@runtime/twig-admin'),
        $translator,
        $urlGenerator,
        $csrfToken,
        $flash,
        $currentUser,
        $findUser,
        $responseFactory,
    ),
];
