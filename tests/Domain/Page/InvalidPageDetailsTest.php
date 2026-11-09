<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Page;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\Exception\InvalidPageDetails;
use Yef\Domain\Page\PageDetails;
use Yef\Domain\Page\PagePath;

#[CoversClass(InvalidPageDetails::class)]
#[CoversClass(PagePath::class)]
#[CoversClass(PageDetails::class)]
final class InvalidPageDetailsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}> Path, rule broken, limit of the rule.
     */
    public static function badPaths(): iterable
    {
        yield 'upper case' => ['About', InvalidPageDetails::BAD_SEGMENT, 0];
        yield 'leading slash' => ['/about', InvalidPageDetails::BAD_SEGMENT, 0];
        yield 'trailing slash' => ['about/', InvalidPageDetails::BAD_SEGMENT, 0];
        yield 'space' => ['about us', InvalidPageDetails::BAD_SEGMENT, 0];
        yield 'dots' => ['../about', InvalidPageDetails::BAD_SEGMENT, 0];
        yield 'reserved first segment' => ['admin/users', InvalidPageDetails::RESERVED, 0];
        yield 'too long' => [str_repeat('a', 201), InvalidPageDetails::TOO_LONG, 200];
    }

    #[DataProvider('badPaths')]
    public function testPathErrorsSayWhichRuleIsBroken(string $path, string $rule, int $limit): void
    {
        try {
            new PagePath($path);
            $this->fail('The path must be refused.');
        } catch (InvalidPageDetails $invalidPageDetails) {
            $this->assertSame(InvalidPageDetails::PATH, $invalidPageDetails->field);
            $this->assertSame($rule, $invalidPageDetails->rule);
            $this->assertSame($limit, $invalidPageDetails->limit);
        }
    }

    public function testEmptyTitleSaysSo(): void
    {
        try {
            new PageDetails(new PagePath('about'), '   ');
            $this->fail('The title must be refused.');
        } catch (InvalidPageDetails $invalidPageDetails) {
            $this->assertSame(InvalidPageDetails::TITLE, $invalidPageDetails->field);
            $this->assertSame(InvalidPageDetails::EMPTY, $invalidPageDetails->rule);
        }
    }

    public function testStillAnInvalidArgumentForCodeThatDoesNotCareWhichField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved name');

        new PagePath('api');
    }
}
