<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Module;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\CoreVersion;
use Yef\Domain\Module\ModuleName;

#[CoversClass(ModuleName::class)]
#[CoversClass(CoreVersion::class)]
final class ModuleNameTest extends TestCase
{
    public function testDirectoryNameReplacesTheSlash(): void
    {
        $name = new ModuleName('yef-cms/gallery');

        $this->assertSame('yef-cms__gallery', $name->directoryName());
        $this->assertSame('yef-cms/gallery', (string) $name);
    }

    public function testDirectoryNameLeadsBackToTheName(): void
    {
        $this->assertTrue(ModuleName::fromDirectoryName('yef-cms__gallery')->equals(new ModuleName('yef-cms/gallery')));
    }

    public function testDirectoryNameWithoutVendorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModuleName::fromDirectoryName('gallery');
    }

    public function testNamesAreComparedByValue(): void
    {
        $this->assertTrue(new ModuleName('acme/news')->equals(new ModuleName('acme/news')));
        $this->assertFalse(new ModuleName('acme/news')->equals(new ModuleName('acme/blog')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'no vendor' => ['gallery'];
        yield 'uppercase' => ['Acme/Gallery'];
        yield 'spaces' => ['acme/photo gallery'];
        yield 'three parts' => ['acme/photo/gallery'];
        yield 'dots' => ['acme/../gallery'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsAnInvalidName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleName($name);
    }
}
