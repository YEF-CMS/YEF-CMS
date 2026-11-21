<?php

declare(strict_types=1);

namespace Yef\Admin\Apply;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Http\Form;
use Yef\Application\Publishing\ApplyPageChange;
use Yef\Application\Publishing\StepOutcome;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Publishing\Exception\PublishingFailed;

use function json_encode;

/**
 * Applies the change of one page and answers with what came of it, for the script of the apply screen.
 *
 * @psalm-api Wired through config/common/routes.php.
 */
final readonly class StepAction
{
    public function __construct(
        private ApplyPageChange $applyPageChange,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $outcome = $this->applyPageChange->handle(Form::of($request)->text('page'));
        } catch (InvalidArgumentException) {
            return $this->answer(400, 'invalid');
        } catch (PageStorageFailed|PublishingFailed) {
            return $this->answer(200, StepOutcome::Failed->value);
        }

        return $this->answer(200, $outcome->value);
    }

    private function answer(int $status, string $outcome): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['outcome' => $outcome], \JSON_THROW_ON_ERROR));

        return $response;
    }
}
