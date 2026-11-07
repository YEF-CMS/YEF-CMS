<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\PagePath;

#[CoversClass(PagePath::class)]
final class PagePathTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validPaths(): iterable
    {
        yield 'single segment' => ['about'];
        yield 'nested' => ['blog/hello-world'];
        yield 'digits' => ['2026/11/news-1'];
        yield 'reserved word deeper in the path' => ['blog/admin'];
    }

    #[DataProvider('validPaths')]
    public function testAcceptsValidPath(string $value): void
    {
        $this->assertSame($value, (string) new PagePath($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'leading slash' => ['/about'];
        yield 'trailing slash' => ['about/'];
        yield 'empty segment' => ['blog//hello'];
        yield 'uppercase' => ['About'];
        yield 'space' => ['about us'];
        yield 'dot segment' => ['blog/../secret'];
        yield 'leading hyphen' => ['-about'];
        yield 'double hyphen' => ['a--b'];
        yield 'reserved admin' => ['admin'];
        yield 'reserved assets nested' => ['assets/logo'];
        yield 'too long' => [str_repeat('a', 201)];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidPath(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PagePath($value);
    }

    public function testHomePageHasEmptyPath(): void
    {
        $home = PagePath::home();

        $this->assertTrue($home->isHome());
        $this->assertSame('', $home->value);
        $this->assertTrue($home->equals(new PagePath('')));
        $this->assertFalse(new PagePath('about')->isHome());
    }
}
