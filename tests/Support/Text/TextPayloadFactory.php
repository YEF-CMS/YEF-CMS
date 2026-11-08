<?php

declare(strict_types=1);

namespace Yef\Tests\Support\Text;

use Override;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadFactory;

final readonly class TextPayloadFactory implements PayloadFactory
{
    #[Override]
    public function fromInput(array $input): Payload
    {
        $body = $input['body'] ?? '';
        if (!is_string($body)) {
            throw new InvalidPayload('The body must be a string.');
        }

        return new TextPayload($body);
    }
}
