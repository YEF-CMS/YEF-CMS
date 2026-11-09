<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\CreatePageCommand;
use Yef\Application\Page\ListContentTypes;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yiisoft\Session\Flash\FlashInterface;

use function count;
use function is_string;

/**
 * Creates a page: asks which type it is when the site has several, then shows the form of that type.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class NewAction
{
    public function __construct(
        private ListContentTypes $listContentTypes,
        private CreatePage $createPage,
        private PageFormRenderer $formRenderer,
        private PageFormErrors $errors,
        private AdminView $view,
        private Redirect $redirect,
        private FlashInterface $flash,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->listContentTypes->handle();
        if ($types === []) {
            return $this->view->render($request, 'pages/no_types');
        }

        $type = $this->chosenType($types, $request);
        if (!$type instanceof ContentTypeDefinition) {
            return $this->view->render($request, 'pages/choose_type', ['types' => $types]);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->formRenderer->render($request, $type, SubmittedPage::blank($type), [], null);
        }

        $submitted = SubmittedPage::fromRequest($request, $type);
        try {
            $this->createPage->handle(new CreatePageCommand(
                $type->id->value,
                $submitted->path,
                $submitted->title,
                $submitted->fields,
                $submitted->metaTitle,
                $submitted->metaDescription,
                $submitted->published,
            ));
        } catch (InvalidArgumentException|PathAlreadyTaken|InvalidPayload $failure) {
            return $this->formRenderer->render($request, $type, $submitted, $this->errors->of($failure) ?? [], null);
        }

        $this->flash->set('success', 'pages.created');

        return $this->redirect->to('admin.pages');
    }

    /**
     * @param list<ContentTypeDefinition> $types
     */
    private function chosenType(array $types, ServerRequestInterface $request): ?ContentTypeDefinition
    {
        if (count($types) === 1) {
            return $types[0];
        }

        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $asked = $request->getQueryParams()['type'] ?? null;
        foreach ($types as $type) {
            if (is_string($asked) && $type->id->value === $asked) {
                return $type;
            }
        }

        return null;
    }
}
