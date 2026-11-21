<?php

declare(strict_types=1);

namespace Yef\Admin\View;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Yef\Admin\Http\StripScriptName;
use Yef\Application\Settings\AdminLanguages;
use Yef\Application\Settings\CurrentAdminLanguage;
use Yef\Application\User\FindUser;
use Yef\Domain\User\User;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

use function dirname;
use function is_string;
use function rtrim;

/**
 * Renders admin pages from the Twig templates of the core.
 *
 * It is a separate environment from the one that builds the public site: those templates belong to the site owner,
 * these to the admin. Templates get `t()` for texts in the language of the admin, `url()` for links to named routes,
 * and the variables `csrfToken`, `assetsUrl` (the admin's own files), `siteUrl` (the address of the public site),
 * `language` (code of the language of the admin) and `languages` (their native names by code).
 *
 * The Twig environment is made for each page, because the texts depend on the language and the language
 * may be changed between two pages.
 *
 * @psalm-api Wired through config/web/di/admin.php.
 */
final readonly class AdminView
{
    private const string TRANSLATION_CATEGORY = 'admin';

    public function __construct(
        private string $templatesDirectory,
        private string $cacheDirectory,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenInterface $csrfToken,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
        private FindUser $findUser,
        private ResponseFactoryInterface $responseFactory,
        private CurrentAdminLanguage $currentLanguage,
        private AdminLanguages $languages,
    ) {}

    /**
     * @param array<string, mixed> $variables
     *
     * @throws TwigError
     */
    public function render(ServerRequestInterface $request, string $template, array $variables = [], int $status = 200): ResponseInterface
    {
        $language = $this->currentLanguage->handle()->code;
        $variables += [
            'csrfToken' => $this->csrfToken->getValue(),
            'assetsUrl' => $this->directoryOf($request) . '/assets',
            'siteUrl' => rtrim(dirname($this->directoryOf($request)), '/.'),
            'flash' => $this->flashMessages(),
            'login' => $this->signedInLogin(),
            'language' => $language,
            'languages' => $this->languages->names(),
        ];

        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($this->twig($language)->render($template . '.twig', $variables));

        return $response;
    }

    private function twig(string $language): Environment
    {
        $twig = new Environment(new FilesystemLoader($this->templatesDirectory), [
            'cache' => $this->cacheDirectory,
            'auto_reload' => true,
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $twig->addFunction(new TwigFunction(
            't',
            fn(string $id, array $parameters = []): string => $this->translator->translate($id, $parameters, self::TRANSLATION_CATEGORY, $language),
        ));
        $twig->addFunction(new TwigFunction('url', $this->urlGenerator->generate(...)));

        return $twig;
    }

    /**
     * @return string The login of the signed-in user, empty for a guest.
     */
    private function signedInLogin(): string
    {
        if ($this->currentUser->isGuest()) {
            return '';
        }

        $user = $this->findUser->handle($this->currentUser->getId() ?? '');

        return $user instanceof User ? $user->login->value : '';
    }

    /**
     * The one-time messages waiting for this visitor, by kind ("success", "danger"), each a translation key.
     *
     * @return array<string, string>
     */
    private function flashMessages(): array
    {
        $messages = [];
        foreach (['success', 'danger'] as $kind) {
            /** @psalm-suppress MixedAssignment The type is checked right below. */
            $message = $this->flash->get($kind);
            if (is_string($message)) {
                $messages[$kind] = $message;
            }
        }

        return $messages;
    }

    private function directoryOf(ServerRequestInterface $request): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $directory = $request->getAttribute(StripScriptName::DIRECTORY_ATTRIBUTE);

        return is_string($directory) ? $directory : '';
    }
}
