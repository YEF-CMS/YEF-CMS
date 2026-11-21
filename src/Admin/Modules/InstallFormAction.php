<?php

declare(strict_types=1);

namespace Yef\Admin\Modules;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\ModuleInstall\InspectModule;

/**
 * Asks for the address of a module on GitHub, downloads the latest release and goes on to show it.
 * Nothing is installed yet.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class InstallFormAction
{
    public function __construct(
        private InspectModule $inspectModule,
        private InstallFailures $failures,
        private AdminView $view,
        private Redirect $redirect,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->view->render($request, 'modules/install', ['address' => '', 'error' => null]);
        }

        $address = trim(Form::of($request)->text('address'));

        try {
            $offer = $this->inspectModule->handle($address);
        } catch (Throwable $throwable) {
            $error = $this->failures->explain($throwable);
            if ($error === null) {
                throw $throwable;
            }

            return $this->view->render($request, 'modules/install', ['address' => $address, 'error' => $error], 422);
        }

        return $this->redirect->toWith('admin.modules.offer', ['token' => $offer->token]);
    }
}
