<?php

declare(strict_types=1);

namespace Yef\Setup;

use function in_array;
use function is_dir;
use function is_writable;
use function sprintf;

/**
 * Checks that a (shared) hosting meets the requirements of YEF CMS.
 *
 * Pure PHP with no framework dependencies, so it can be reused by the web installer.
 */
final readonly class EnvironmentChecker
{
    public const string MIN_PHP_VERSION = '8.4.0';

    /** Extensions without which the system cannot work. */
    private const array REQUIRED_EXTENSIONS = ['ctype', 'json', 'mbstring'];

    /** Extensions the system works without, with reduced functionality. */
    private const array RECOMMENDED_EXTENSIONS = [
        'fileinfo' => 'media type detection for uploads',
        'intl' => 'transliteration of slugs',
        'zip' => 'installing modules from GitHub',
        'gd' => 'image thumbnails',
    ];

    /**
     * @param list<string> $loadedExtensions
     * @param list<string> $writableDirectories Directories that must exist and be writable.
     */
    public function __construct(
        private string $phpVersion,
        private array $loadedExtensions,
        private bool $allowUrlFopen,
        private array $writableDirectories,
    ) {}

    /**
     * @param list<string> $writableDirectories
     */
    public static function fromRuntime(array $writableDirectories): self
    {
        return new self(
            PHP_VERSION,
            array_map(strtolower(...), get_loaded_extensions()),
            (bool) ini_get('allow_url_fopen'),
            $writableDirectories,
        );
    }

    /**
     * @return list<CheckResult>
     */
    public function check(): array
    {
        return [
            $this->checkPhpVersion(),
            ...$this->checkRequiredExtensions(),
            ...$this->checkRecommendedExtensions(),
            $this->checkHttpClient(),
            ...$this->checkWritableDirectories(),
        ];
    }

    /**
     * @param list<CheckResult> $results
     */
    public static function hasFailures(array $results): bool
    {
        return array_any($results, static fn(CheckResult $r): bool => $r->status === CheckStatus::Fail);
    }

    private function checkPhpVersion(): CheckResult
    {
        return version_compare($this->phpVersion, self::MIN_PHP_VERSION, '>=')
            ? new CheckResult('PHP version', CheckStatus::Ok, $this->phpVersion)
            : new CheckResult(
                'PHP version',
                CheckStatus::Fail,
                sprintf('%s found, %s or newer is required', $this->phpVersion, self::MIN_PHP_VERSION),
            );
    }

    /**
     * @return list<CheckResult>
     */
    private function checkRequiredExtensions(): array
    {
        $results = [];
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $results[] = $this->hasExtension($extension)
                ? new CheckResult("ext-$extension", CheckStatus::Ok, 'loaded')
                : new CheckResult("ext-$extension", CheckStatus::Fail, 'required extension is missing');
        }

        return $results;
    }

    /**
     * @return list<CheckResult>
     */
    private function checkRecommendedExtensions(): array
    {
        $results = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $extension => $purpose) {
            $results[] = $this->hasExtension($extension)
                ? new CheckResult("ext-$extension", CheckStatus::Ok, 'loaded')
                : new CheckResult("ext-$extension", CheckStatus::Warning, "missing, needed for: $purpose");
        }

        return $results;
    }

    private function checkHttpClient(): CheckResult
    {
        return $this->hasExtension('curl') || $this->allowUrlFopen
            ? new CheckResult('HTTP client', CheckStatus::Ok, 'curl or allow_url_fopen is available')
            : new CheckResult(
                'HTTP client',
                CheckStatus::Warning,
                'neither ext-curl nor allow_url_fopen is available, needed for installing modules from GitHub',
            );
    }

    /**
     * @return list<CheckResult>
     */
    private function checkWritableDirectories(): array
    {
        $results = [];
        foreach ($this->writableDirectories as $directory) {
            $name = "writable $directory";
            $results[] = match (true) {
                !is_dir($directory) => new CheckResult($name, CheckStatus::Fail, 'directory does not exist'),
                !is_writable($directory) => new CheckResult($name, CheckStatus::Fail, 'directory is not writable'),
                default => new CheckResult($name, CheckStatus::Ok, 'writable'),
            };
        }

        return $results;
    }

    private function hasExtension(string $extension): bool
    {
        return in_array($extension, $this->loadedExtensions, true);
    }
}
