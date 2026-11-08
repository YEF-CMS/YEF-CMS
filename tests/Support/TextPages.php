<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use DateTimeImmutable;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Tests\Support\Text\TextContentType;
use Yef\Tests\Support\Text\TextPayload;

/**
 * Builds pages of the reference text type for tests.
 */
final readonly class TextPages
{
    public function page(string $id, string $path, string $html): Page
    {
        return new Page(
            PageMetadata::draft(
                new PageId($id),
                new ContentTypeId(TextContentType::ID),
                new PageDetails(new PagePath($path), 'Title of ' . ($path === '' ? 'home' : $path)),
                new DateTimeImmutable('2026-11-08 12:00:00 UTC'),
            ),
            new TextPayload($html),
        );
    }
}
