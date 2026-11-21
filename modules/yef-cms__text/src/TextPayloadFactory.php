<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use Override;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadFactory;
use Yef\Domain\Text\Texts;

final readonly class TextPayloadFactory implements PayloadFactory
{
    public function __construct(private Texts $texts) {}

    #[Override]
    public function fromInput(array $input): Payload
    {
        $body = $input['body'] ?? '';
        if (!is_string($body)) {
            throw new InvalidPayload($this->texts->t('error.body_not_text'));
        }

        return new TextPayload($body);
    }
}
