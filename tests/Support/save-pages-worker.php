<?php

declare(strict_types=1);

/**
 * Separate process for the concurrency test: saves pages through its own repository instance.
 *
 * Usage: php save-pages-worker.php <pages directory> <state directory> <worker number> <page count>
 */

use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Storage\FilePageRepository;
use Yef\Storage\StorageLayout;
use Yef\Storage\UlidPageIdGenerator;
use YefCms\TextPages\TextContentType;
use YefCms\TextPages\TextPayload;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** @var list<string> $argv */
$pagesDirectory = $argv[1] ?? throw new InvalidArgumentException('Pages directory is missing.');
$stateDirectory = $argv[2] ?? throw new InvalidArgumentException('State directory is missing.');
$worker = $argv[3] ?? throw new InvalidArgumentException('Worker number is missing.');
$count = $argv[4] ?? throw new InvalidArgumentException('Page count is missing.');

$repository = new FilePageRepository(new StorageLayout($pagesDirectory, $stateDirectory), TextContentType::registry());
$ids = new UlidPageIdGenerator();
$now = new DateTimeImmutable('2026-11-08 12:00:00 UTC');

for ($number = 1; $number <= (int) $count; ++$number) {
    $path = sprintf('worker-%s/page-%d', $worker, $number);
    $repository->save(new Page(
        PageMetadata::draft(
            $ids->next(),
            new ContentTypeId(TextContentType::ID),
            new PageDetails(new PagePath($path), 'Page ' . $path),
            $now,
        ),
        new TextPayload('<p>' . $path . '</p>'),
    ));
}
