<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType\Exception;

use DomainException;
use Yef\Domain\ContentType\ContentTypeId;

use function sprintf;

final class UnknownContentType extends DomainException
{
    public static function withId(ContentTypeId $id): self
    {
        return new self(sprintf('Content type "%s" is not available.', $id));
    }
}
