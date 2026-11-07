<?php

declare(strict_types=1);

namespace Yef\Tests\Setup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Setup\CheckReport;
use Yef\Setup\CheckResult;
use Yef\Setup\CheckStatus;
use Yef\Setup\EnvironmentChecker;

#[CoversClass(EnvironmentChecker::class)]
final class EnvironmentCheckerTest extends TestCase
{
    private const array ALL_EXTENSIONS = ['ctype', 'json', 'mbstring', 'fileinfo', 'intl', 'zip', 'gd', 'curl'];

    public function testEverythingAvailable(): void
    {
        $results = $this->checker()->check();

        $this->assertFalse($results->hasFailures());
        $this->assertSame([], $this->withStatus($results, CheckStatus::Warning));
    }

    public function testOldPhpVersionFails(): void
    {
        $results = $this->checker(phpVersion: '8.3.12')->check();

        $this->assertTrue($results->hasFailures());
        $this->assertSame(CheckStatus::Fail, $this->find($results, 'PHP version')->status);
    }

    public function testMissingRequiredExtensionFails(): void
    {
        $results = $this->checker(extensions: ['ctype', 'json'])->check();

        $this->assertSame(CheckStatus::Fail, $this->find($results, 'ext-mbstring')->status);
    }

    public function testMissingRecommendedExtensionOnlyWarns(): void
    {
        $results = $this->checker(extensions: ['ctype', 'json', 'mbstring'])->check();

        $this->assertFalse($results->hasFailures());
        $this->assertSame(CheckStatus::Warning, $this->find($results, 'ext-zip')->status);
    }

    public function testHttpClientIsAvailableViaAllowUrlFopenWithoutCurl(): void
    {
        $extensions = array_values(array_diff(self::ALL_EXTENSIONS, ['curl']));

        $this->assertSame(CheckStatus::Ok, $this->find($this->checker(extensions: $extensions, allowUrlFopen: true)->check(), 'HTTP client')->status);
        $this->assertSame(CheckStatus::Warning, $this->find($this->checker(extensions: $extensions, allowUrlFopen: false)->check(), 'HTTP client')->status);
    }

    public function testDirectoryChecks(): void
    {
        $missing = sys_get_temp_dir() . '/yef-does-not-exist-' . uniqid();

        $results = $this->checker(directories: [sys_get_temp_dir(), $missing])->check();

        $this->assertSame(CheckStatus::Ok, $this->find($results, 'writable ' . sys_get_temp_dir())->status);
        $this->assertSame(CheckStatus::Fail, $this->find($results, 'writable ' . $missing)->status);
    }

    /**
     * @param list<string>|null $extensions
     * @param list<string> $directories
     */
    private function checker(
        string $phpVersion = '8.4.1',
        ?array $extensions = null,
        bool $allowUrlFopen = false,
        array $directories = [],
    ): EnvironmentChecker {
        return new EnvironmentChecker(
            $phpVersion,
            $extensions ?? self::ALL_EXTENSIONS,
            $allowUrlFopen,
            $directories,
        );
    }

    private function find(CheckReport $report, string $name): CheckResult
    {
        foreach ($report->results as $result) {
            if ($result->name === $name) {
                return $result;
            }
        }

        self::fail("Check \"$name\" not found.");
    }

    /**
     * @return list<CheckResult>
     */
    private function withStatus(CheckReport $report, CheckStatus $status): array
    {
        return array_values(array_filter($report->results, static fn(CheckResult $r): bool => $r->status === $status));
    }
}
