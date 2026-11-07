<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\PageId;

#[CoversClass(PageId::class)]
final class PageIdTest extends TestCase
{
    public function testAcceptsCanonicalUlid(): void
    {
        $id = new PageId('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', (string) $id);
        $this->assertTrue($id->equals(new PageId('01ARZ3NDEKTSV4RRFFQ69G5FAV')));
        $this->assertFalse($id->equals(new PageId('01ARZ3NDEKTSV4RRFFQ69G5FAW')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['01ARZ3NDEKTSV4RRFFQ69G5F'];
        yield 'lowercase' => ['01arz3ndektsv4rrffq69g5fav'];
        yield 'forbidden letter' => ['01ARZ3NDEKTSV4RRFFQ69G5FAU'];
        yield 'overflowing timestamp' => ['81ARZ3NDEKTSV4RRFFQ69G5FAV'];
    }

    #[DataProvider('invalidIds')]
    public function testRejectsInvalidUlid(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageId($value);
    }
}
