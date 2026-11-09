<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Domain\Publishing\PublishedPages;

/**
 * Behavior every PublishedPages implementation has to provide.
 */
abstract class PublishedPagesContract extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5001';

    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5002';

    public function testNothingIsPublishedAtFirst(): void
    {
        $published = $this->createPublishedPages();

        $this->assertSame([], $published->all());
        $this->assertNotInstanceOf(PublishedPage::class, $published->find(new PageId(self::FIRST_ID)));
    }

    public function testRecordedPageCanBeFound(): void
    {
        $published = $this->createPublishedPages();
        $entry = $this->entry(self::FIRST_ID, 'blog/hello', 'a');

        $published->record($entry);

        $this->assertEquals($entry, $published->find(new PageId(self::FIRST_ID)));
        $this->assertEquals([$entry], $published->all());
    }

    public function testRecordingAgainReplacesTheEntry(): void
    {
        $published = $this->createPublishedPages();
        $published->record($this->entry(self::FIRST_ID, 'about', 'a'));

        $newer = $this->entry(self::FIRST_ID, 'about-us', 'b');

        $published->record($newer);

        $this->assertEquals([$newer], $published->all());
    }

    public function testForgottenPageIsGone(): void
    {
        $published = $this->createPublishedPages();
        $published->record($this->entry(self::FIRST_ID, 'about', 'a'));

        $kept = $this->entry(self::SECOND_ID, 'contacts', 'b');
        $published->record($kept);

        $published->forget(new PageId(self::FIRST_ID));

        $this->assertEquals([$kept], $published->all());
    }

    public function testForgettingUnknownPageDoesNothing(): void
    {
        $published = $this->createPublishedPages();

        $published->forget(new PageId(self::FIRST_ID));

        $this->assertSame([], $published->all());
    }

    abstract protected function createPublishedPages(): PublishedPages;

    private function entry(string $id, string $path, string $versionSeed): PublishedPage
    {
        return new PublishedPage(new PageId($id), new PagePath($path), new ContentVersion(hash('sha256', $versionSeed)));
    }
}
