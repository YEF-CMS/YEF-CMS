<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\ContentType;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;

#[CoversClass(ContentTypeId::class)]
final class ContentTypeIdTest extends TestCase
{
    public function testAcceptsLowercaseWordsWithHyphens(): void
    {
        $id = new ContentTypeId('photo-gallery');

        $this->assertSame('photo-gallery', (string) $id);
        $this->assertTrue($id->equals(new ContentTypeId('photo-gallery')));
        $this->assertFalse($id->equals(new ContentTypeId('text')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Text'];
        yield 'starts with a digit' => ['1text'];
        yield 'underscore' => ['my_type'];
        yield 'slash' => ['vendor/type'];
        yield 'trailing hyphen' => ['text-'];
        yield 'too long' => [str_repeat('a', 51)];
    }

    #[DataProvider('invalidIds')]
    public function testRejectsInvalidId(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentTypeId($value);
    }
}
