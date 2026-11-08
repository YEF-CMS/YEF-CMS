<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\ContentType;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\PayloadData;

#[CoversClass(PayloadData::class)]
final class PayloadDataTest extends TestCase
{
    public function testExposesParts(): void
    {
        $data = new PayloadData(['body' => '<p>Hi</p>', 'summary' => 'Hi']);

        $this->assertTrue($data->has('body'));
        $this->assertSame('<p>Hi</p>', $data->part('body'));
        $this->assertSame(['body' => '<p>Hi</p>', 'summary' => 'Hi'], $data->parts());
    }

    public function testMissingPartIsNull(): void
    {
        $data = new PayloadData([]);

        $this->assertFalse($data->has('body'));
        $this->assertNull($data->part('body'));
    }

    public function testEmptyContentsAreKept(): void
    {
        $data = new PayloadData(['body' => '']);

        $this->assertTrue($data->has('body'));
        $this->assertSame('', $data->part('body'));
    }

    /**
     * @param array<array-key, mixed> $parts
     */
    #[DataProvider('invalidParts')]
    public function testRejectsInvalidParts(array $parts): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PayloadData($parts);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidParts(): iterable
    {
        yield 'file name instead of a logical name' => [['body.html' => 'x']];
        yield 'path' => [['../body' => 'x']];
        yield 'uppercase' => [['Body' => 'x']];
        yield 'empty name' => [['' => 'x']];
        yield 'list instead of named parts' => [['x']];
        yield 'non-string contents' => [['body' => 42]];
    }
}
