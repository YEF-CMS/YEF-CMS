<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * Payload of a page whose content type is not registered (for example, its module is disabled).
 *
 * The data is kept untouched, so the page survives until the type is available again.
 */
final readonly class UnavailablePayload implements Payload
{
    public function __construct(
        public ContentTypeId $type,
        public PayloadData $data,
    ) {}
}
