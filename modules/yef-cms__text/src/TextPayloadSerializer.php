<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use InvalidArgumentException;
use Override;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\PayloadSerializer;
use Yef\Domain\Text\Texts;

final readonly class TextPayloadSerializer implements PayloadSerializer
{
    public const string BODY = 'body';

    public function __construct(private Texts $texts) {}

    #[Override]
    public function toData(Payload $payload): PayloadData
    {
        if (!$payload instanceof TextPayload) {
            throw new InvalidArgumentException('Expected a text payload.');
        }

        return new PayloadData([self::BODY => $payload->html]);
    }

    #[Override]
    public function fromData(PayloadData $data): Payload
    {
        return new TextPayload($data->part(self::BODY) ?? throw new InvalidPayload($this->texts->t('error.body_missing')));
    }
}
