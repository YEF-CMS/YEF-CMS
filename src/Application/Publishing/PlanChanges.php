<?php

declare(strict_types=1);

namespace Yef\Application\Publishing;

use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PageRepository;
use Yef\Domain\Page\PageVersions;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;

/**
 * Compares the pages with what is published and lists what has to be built or taken down.
 * Pages that are in line with the public site are not in the plan.
 *
 * @psalm-api Used by the admin and console adapters.
 */
final readonly class PlanChanges
{
    public function __construct(
        private PageRepository $pages,
        private PageVersions $versions,
        private PublishedPages $published,
        private ContentTypeRegistry $contentTypes,
    ) {}

    /**
     * @throws PageStorageFailed
     * @throws PublishingFailed
     */
    public function handle(): ChangePlan
    {
        $published = [];
        foreach ($this->published->all() as $entry) {
            $published[$entry->id->value] = $entry;
        }

        $versions = $this->versions->all();
        $removals = [];
        $builds = [];
        $existing = [];

        foreach ($this->pages->allMetadata() as $metadata) {
            $existing[$metadata->id->value] = true;
            if (!$this->contentTypes->has($metadata->type)) {
                continue;
            }

            $entry = $published[$metadata->id->value] ?? null;
            if ($metadata->isPublished()) {
                if ($this->needsBuild($metadata, $entry, $versions)) {
                    $builds[] = new Change(ChangeAction::Build, $metadata->id, $metadata->details->path, $metadata->details->title);
                }
            } elseif ($entry instanceof PublishedPage) {
                $removals[] = new Change(ChangeAction::Remove, $metadata->id, $entry->path, $metadata->details->title);
            }
        }

        foreach ($published as $id => $entry) {
            if (!isset($existing[$id])) {
                $removals[] = new Change(ChangeAction::Remove, $entry->id, $entry->path);
            }
        }

        return new ChangePlan([...$removals, ...$builds]);
    }

    /**
     * @param array<string, ContentVersion> $versions
     */
    private function needsBuild(PageMetadata $metadata, ?PublishedPage $entry, array $versions): bool
    {
        $version = $versions[$metadata->id->value] ?? null;

        return !$entry instanceof PublishedPage
            || $version === null
            || !$entry->version->equals($version)
            || !$entry->path->equals($metadata->details->path);
    }
}
