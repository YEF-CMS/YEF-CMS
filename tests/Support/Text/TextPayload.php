<?php

declare(strict_types=1);

namespace Yef\Tests\Support\Text;

use Yef\Domain\ContentType\Payload;

final readonly class TextPayload implements Payload
{
    public function __construct(public string $html) {}
}
