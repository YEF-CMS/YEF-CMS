<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Publishing;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\PublicPath;

#[CoversClass(PublicPath::class)]
final class PublicPathTest extends TestCase
{
    public function testHomePageIsPublishedAsRootIndex(): void
    {
        $this->assertSame('index.html', PublicPath::forPage(PagePath::home())->value);
    }

    public function testNestedPageIsPublishedAsIndexInItsDirectory(): void
    {
        $this->assertSame('blog/hello/index.html', PublicPath::forPage(new PagePath('blog/hello'))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent segment' => ['blog/../index.php'];
        yield 'current segment' => ['./index.html'];
        yield 'empty segment' => ['blog//index.html'];
        yield 'backslash' => ['blog\\index.html'];
        yield 'trailing slash' => ['blog/'];
        yield 'hidden file' => ['.htaccess'];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsPathsThatCouldLeaveTheDocumentRoot(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicPath($path);
    }
}
