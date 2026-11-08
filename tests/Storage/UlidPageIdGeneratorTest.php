<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Page\PageId;
use Yef\Storage\UlidPageIdGenerator;

#[CoversClass(UlidPageIdGenerator::class)]
final class UlidPageIdGeneratorTest extends TestCase
{
    public function testIssuesValidIdentifiers(): void
    {
        $id = new UlidPageIdGenerator()->next();

        $this->assertInstanceOf(PageId::class, $id);
        $this->assertSame(26, strlen($id->value));
    }

    public function testIdentifiersAreUniqueAndKeepCreationOrder(): void
    {
        $generator = new UlidPageIdGenerator();

        $values = [];
        for ($i = 0; $i < 1000; ++$i) {
            $values[] = $generator->next()->value;
        }

        $sorted = $values;
        sort($sorted, SORT_STRING);
        $this->assertSame($values, $sorted);
        $this->assertCount(1000, array_unique($values));
    }
}
