<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use Yef\Domain\ContentType\Payload;

final readonly class TextPayload implements Payload
{
    public function __construct(public string $html) {}
}
