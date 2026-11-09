<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\Page\ContentVersion;
use Yef\Tests\Support\TextPages;

#[CoversClass(ContentVersion::class)]
final class ContentVersionTest extends TestCase
{
    public function testRejectsAValueThatIsNotADigest(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentVersion('abc');
    }

    public function testSameContentGivesTheSameVersion(): void
    {
        $metadata = new TextPages()->page('01JC0000000000000000000001', 'about', 'x')->metadata;

        $first = ContentVersion::of($metadata, new PayloadData(['body' => 'x']));
        $second = ContentVersion::of($metadata, new PayloadData(['body' => 'x']));

        $this->assertTrue($first->equals($second));
    }

    public function testPartOrderDoesNotMatter(): void
    {
        $metadata = new TextPages()->page('01JC0000000000000000000001', 'about', 'x')->metadata;

        $first = ContentVersion::of($metadata, new PayloadData(['a' => '1', 'b' => '2']));
        $second = ContentVersion::of($metadata, new PayloadData(['b' => '2', 'a' => '1']));

        $this->assertTrue($first->equals($second));
    }

    public function testContentMovingBetweenPartsChangesTheVersion(): void
    {
        $metadata = new TextPages()->page('01JC0000000000000000000001', 'about', 'x')->metadata;

        $first = ContentVersion::of($metadata, new PayloadData(['a' => 'xy', 'b' => '']));
        $second = ContentVersion::of($metadata, new PayloadData(['a' => 'x', 'b' => 'y']));

        $this->assertFalse($first->equals($second));
    }
}
