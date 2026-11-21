<?php

declare(strict_types=1);

namespace Yef\Admin\Language;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\Redirect;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Settings\ChangeAdminLanguage;
use Yef\Application\Settings\LanguageNotOffered;
use Yef\Domain\Settings\Exception\SettingsFailed;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * Switches the language of the admin for the whole site and returns to the page the switch was made on.
 * Only reachable by POST.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class Action
{
    public function __construct(
        private ChangeAdminLanguage $changeLanguage,
        private Redirect $redirect,
        private FlashInterface $flash,
        private NotFoundHandler $notFound,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->changeLanguage->handle(Form::of($request)->text('language'));
        } catch (LanguageNotOffered) {
            return $this->notFound->handle($request);
        } catch (SettingsFailed) {
            $this->flash->set('danger', 'language.error.save');
        }

        return $this->redirect->backOr($request, 'admin.dashboard');
    }
}
