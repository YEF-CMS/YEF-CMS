<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Module\Install;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\ReleaseVersion;

#[CoversClass(ReleaseVersion::class)]
#[CoversClass(InvalidReleaseVersion::class)]
final class ReleaseVersionTest extends TestCase
{
    public function testTheNameOfAReleaseMayStartWithV(): void
    {
        $this->assertSame('1.2.0', new ReleaseVersion('v1.2.0')->value);
        $this->assertSame('1.2.0', (string) new ReleaseVersion('1.2.0'));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'patch' => ['1.0.1', '1.0.0', true];
        yield 'minor beats patch' => ['1.1.0', '1.0.9', true];
        yield 'numbers, not text' => ['1.10.0', '1.9.0', true];
        yield 'major' => ['2.0.0', '1.99.99', true];
        yield 'equal' => ['1.0.0', '1.0.0', false];
        yield 'older' => ['1.0.0', '1.0.1', false];
        yield 'v makes no difference' => ['v1.0.0', '1.0.0', false];
        yield 'a release beats its release candidate' => ['1.0.0', '1.0.0-rc.1', true];
        yield 'a release candidate does not beat its release' => ['1.0.0-rc.1', '1.0.0', false];
    }

    #[DataProvider('comparisons')]
    public function testVersionsAreComparedByNumbers(string $newer, string $older, bool $expected): void
    {
        $this->assertSame($expected, new ReleaseVersion($newer)->isNewerThan(new ReleaseVersion($older)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notVersions(): iterable
    {
        yield 'a word' => ['latest'];
        yield 'two numbers' => ['1.0'];
        yield 'a date' => ['2026-11-21'];
        yield 'letters inside' => ['1.0.x'];
        yield 'empty' => [''];
        yield 'a path' => ['../1.0.0'];
        yield 'a space' => ['1.0.0 beta'];
    }

    #[DataProvider('notVersions')]
    public function testRefusesAnythingThatIsNotASemanticVersion(string $name): void
    {
        $this->expectException(InvalidReleaseVersion::class);

        new ReleaseVersion($name);
    }
}
