<?php

declare(strict_types=1);

namespace Yef\Tests\Support\Text;

use InvalidArgumentException;
use Override;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadSerializer;

final readonly class TextPayloadSerializer implements PayloadSerializer
{
    public const string FILE = 'body.html';

    #[Override]
    public function toFiles(Payload $payload): array
    {
        if (!$payload instanceof TextPayload) {
            throw new InvalidArgumentException('Expected a text payload.');
        }

        return [self::FILE => $payload->html];
    }

    #[Override]
    public function fromFiles(array $files): Payload
    {
        return new TextPayload($files[self::FILE] ?? throw new InvalidPayload('body.html is missing.'));
    }
}
