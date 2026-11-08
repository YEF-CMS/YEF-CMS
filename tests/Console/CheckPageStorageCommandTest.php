<?php

declare(strict_types=1);

namespace Yef\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yef\Application\Page\CheckPageStorage;
use Yef\Application\Page\RepairPageStorage;
use Yef\Console\CheckPageStorageCommand;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageId;
use Yef\Tests\Support\RecordingPageStorageIntegrity;

#[CoversClass(CheckPageStorageCommand::class)]
final class CheckPageStorageCommandTest extends TestCase
{
    public function testHealthyStorageSucceeds(): void
    {
        $integrity = new RecordingPageStorageIntegrity(new IntegrityReport(0, []), new IntegrityReport(0, []));

        $tester = $this->runCommand($integrity, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('healthy', $tester->getDisplay());
        $this->assertSame(['check'], $integrity->calls);
    }

    public function testLeftoversAndDamagedPagesFailTheCheck(): void
    {
        $integrity = new RecordingPageStorageIntegrity(
            new IntegrityReport(3, [new PageId('01ARZ3NDEKTSV4RRFFQ69G5001')]),
            new IntegrityReport(0, []),
        );

        $tester = $this->runCommand($integrity, []);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('3 leftover(s)', $tester->getDisplay());
        $this->assertStringContainsString('01ARZ3NDEKTSV4RRFFQ69G5001', $tester->getDisplay());
    }

    public function testRepairOptionRepairsInsteadOfChecking(): void
    {
        $integrity = new RecordingPageStorageIntegrity(new IntegrityReport(3, []), new IntegrityReport(0, []));

        $tester = $this->runCommand($integrity, ['--repair' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['repair'], $integrity->calls);
    }

    /**
     * @param array<string, bool> $options
     */
    private function runCommand(RecordingPageStorageIntegrity $integrity, array $options): CommandTester
    {
        $tester = new CommandTester(
            new CheckPageStorageCommand(new CheckPageStorage($integrity), new RepairPageStorage($integrity)),
        );
        $tester->execute($options);

        return $tester;
    }
}
