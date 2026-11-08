<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Page\PageStatus;
use Yef\Tests\Support\Text\TextPayload;

#[CoversClass(Page::class)]
#[CoversClass(PageMetadata::class)]
#[CoversClass(PageDetails::class)]
final class PageTest extends TestCase
{
    private const string CREATED = '2026-11-08 10:00:00 UTC';

    private const string LATER = '2026-11-08 11:00:00 UTC';

    public function testNewPageIsDraft(): void
    {
        $metadata = $this->page()->metadata;

        $this->assertSame(PageStatus::Draft, $metadata->status);
        $this->assertFalse($metadata->isPublished());
        $this->assertEquals($metadata->createdAt, $metadata->updatedAt);
    }

    public function testPublishAndUnpublishReturnNewPageAndTouchUpdatedAt(): void
    {
        $draft = $this->page();
        $later = new DateTimeImmutable(self::LATER);

        $published = $draft->publish($later);

        $this->assertTrue($published->metadata->isPublished());
        $this->assertEquals($later, $published->metadata->updatedAt);
        $this->assertEquals($draft->metadata->createdAt, $published->metadata->createdAt);
        $this->assertSame($draft->payload, $published->payload);
        $this->assertFalse($draft->metadata->isPublished(), 'the original page must stay unchanged');
        $this->assertFalse($published->unpublish($later)->metadata->isPublished());
    }

    public function testEditReplacesDetailsAndPayloadButKeepsIdentityAndStatus(): void
    {
        $page = $this->page()->publish(new DateTimeImmutable(self::CREATED));
        $details = new PageDetails(new PagePath('blog/hello'), 'Hello', 'Meta', 'Description');
        $payload = new TextPayload('<p>New</p>');

        $edited = $page->edit($details, $payload, new DateTimeImmutable(self::LATER));

        $this->assertSame($details, $edited->metadata->details);
        $this->assertSame($payload, $edited->payload);
        $this->assertTrue($edited->metadata->id->equals($page->metadata->id));
        $this->assertTrue($edited->metadata->type->equals($page->metadata->type));
        $this->assertTrue($edited->metadata->isPublished());
        $this->assertEquals(new DateTimeImmutable(self::LATER), $edited->metadata->updatedAt);
    }

    public function testDetailsRequireTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageDetails(new PagePath('about'), '   ');
    }

    private function page(): Page
    {
        return new Page(
            PageMetadata::draft(
                new PageId('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
                new ContentTypeId('text'),
                new PageDetails(new PagePath('about'), 'About'),
                new DateTimeImmutable(self::CREATED),
            ),
            new TextPayload('<p>Hi</p>'),
        );
    }
}
