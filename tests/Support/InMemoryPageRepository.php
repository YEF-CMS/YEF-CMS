<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PathAlreadyTaken;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageVersions;
use YefCms\TextPages\TextContentType;

final class InMemoryPageRepository implements PageRepository, PageVersions
{
    /** @var array<string, Page> */
    private array $pages = [];

    private readonly ContentTypeRegistry $types;

    public function __construct(?ContentTypeRegistry $types = null)
    {
        $this->types = $types ?? TextContentType::registry();
    }

    #[Override]
    public function save(Page $page): void
    {
        $owner = $this->findMetadataByPath($page->metadata->details->path);
        if ($owner instanceof PageMetadata && !$owner->id->equals($page->metadata->id)) {
            throw PathAlreadyTaken::byPath($page->metadata->details->path);
        }

        $this->pages[$page->metadata->id->value] = $page;
    }

    #[Override]
    public function get(PageId $id): Page
    {
        return $this->pages[$id->value] ?? throw PageNotFound::withId($id);
    }

    #[Override]
    public function findMetadataByPath(PagePath $path): ?PageMetadata
    {
        foreach ($this->pages as $page) {
            if ($page->metadata->details->path->equals($path)) {
                return $page->metadata;
            }
        }

        return null;
    }

    #[Override]
    public function allMetadata(): array
    {
        return array_values(array_map(static fn(Page $page): PageMetadata => $page->metadata, $this->pages));
    }

    #[Override]
    public function delete(PageId $id): void
    {
        $this->get($id);
        unset($this->pages[$id->value]);
    }

    #[Override]
    public function versionOf(PageId $id): ContentVersion
    {
        $page = $this->get($id);
        $payload = $page->payload;
        $data = $payload instanceof UnavailablePayload
            ? $payload->data
            : $this->types->get($page->metadata->type)->serializer->toData($payload);

        return ContentVersion::of($page->metadata, $data);
    }

    #[Override]
    public function all(): array
    {
        $versions = [];
        foreach (array_keys($this->pages) as $id) {
            $versions[$id] = $this->versionOf(new PageId($id));
        }

        return $versions;
    }
}
