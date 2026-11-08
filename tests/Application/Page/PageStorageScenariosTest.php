<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\CheckPageStorage;
use Yef\Application\Page\RepairPageStorage;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageId;
use Yef\Tests\Support\RecordingPageStorageIntegrity;

#[CoversClass(CheckPageStorage::class)]
#[CoversClass(RepairPageStorage::class)]
#[CoversClass(IntegrityReport::class)]
final class PageStorageScenariosTest extends TestCase
{
    public function testCheckOnlyLooks(): void
    {
        $integrity = new RecordingPageStorageIntegrity(new IntegrityReport(2, []), new IntegrityReport(0, []));

        $report = new CheckPageStorage($integrity)->handle();

        $this->assertSame(2, $report->leftovers);
        $this->assertSame(['check'], $integrity->calls);
    }

    public function testRepairReportsWhatRemains(): void
    {
        $damaged = new PageId('01ARZ3NDEKTSV4RRFFQ69G5001');
        $integrity = new RecordingPageStorageIntegrity(new IntegrityReport(2, []), new IntegrityReport(0, [$damaged]));

        $report = new RepairPageStorage($integrity)->handle();

        $this->assertSame([$damaged], $report->damagedPages);
        $this->assertFalse($report->isHealthy());
        $this->assertSame(['repair'], $integrity->calls);
    }

    public function testReportIsHealthyOnlyWithoutProblems(): void
    {
        $this->assertTrue(new IntegrityReport(0, [])->isHealthy());
        $this->assertFalse(new IntegrityReport(1, [])->isHealthy());
        $this->assertFalse(new IntegrityReport(0, [new PageId('01ARZ3NDEKTSV4RRFFQ69G5001')])->isHealthy());
    }
}
