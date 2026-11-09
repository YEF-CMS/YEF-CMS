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
use Yef\Application\User\FindUser;
use Yef\Domain\User\User;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

use function is_string;

/**
 * Renders admin pages from the Twig templates of the core.
 *
 * It is a separate environment from the one that builds the public site: those templates belong to the site owner,
 * these to the admin. Templates get `t()` for texts, `url()` for links to named routes, and the variables
 * `csrfToken` and `assetsUrl`.
 *
 * @psalm-api Wired through config/web/di/admin.php.
 */
final readonly class AdminView
{
    private const string TRANSLATION_CATEGORY = 'admin';

    private Environment $twig;

    public function __construct(
        string $templatesDirectory,
        string $cacheDirectory,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        private CsrfTokenInterface $csrfToken,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
        private FindUser $findUser,
        private ResponseFactoryInterface $responseFactory,
    ) {
        $this->twig = new Environment(new FilesystemLoader($templatesDirectory), [
            'cache' => $cacheDirectory,
            'auto_reload' => true,
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $this->twig->addFunction(new TwigFunction(
            't',
            static fn(string $id, array $parameters = []): string => $translator->translate($id, $parameters, self::TRANSLATION_CATEGORY),
        ));
        $this->twig->addFunction(new TwigFunction('url', $urlGenerator->generate(...)));
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @throws TwigError
     */
    public function render(ServerRequestInterface $request, string $template, array $variables = [], int $status = 200): ResponseInterface
    {
        $variables += [
            'csrfToken' => $this->csrfToken->getValue(),
            'assetsUrl' => $this->directoryOf($request) . '/assets',
            'flash' => $this->flashMessages(),
            'login' => $this->signedInLogin(),
        ];

        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($this->twig->render($template . '.twig', $variables));

        return $response;
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
