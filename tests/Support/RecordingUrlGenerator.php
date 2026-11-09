<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Stringable;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Makes links like "<prefix>/route/<name>", so tests can see which route a link is for and which prefix it got.
 */
final class RecordingUrlGenerator implements UrlGeneratorInterface
{
    private string $prefix = '';

    /**
     * @param array<string, mixed> $arguments
     * @param array<array-key, mixed> $queryParameters
     */
    #[Override]
    public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
    {
        return $this->prefix . '/route/' . $name;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<array-key, mixed> $queryParameters
     */
    #[Override]
    public function generateAbsolute(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null, ?string $scheme = null, ?string $host = null): string
    {
        return 'http://localhost' . $this->generate($name);
    }

    /**
     * @param array<string, mixed> $replacedArguments
     * @param array<array-key, mixed> $queryParameters
     */
    #[Override]
    public function generateFromCurrent(array $replacedArguments, array $queryParameters = [], ?string $hash = null, ?string $fallbackRouteName = null): string
    {
        return $this->prefix . '/current';
    }

    #[Override]
    public function getUriPrefix(): string
    {
        return $this->prefix;
    }

    #[Override]
    public function setUriPrefix(string $name): void
    {
        $this->prefix = $name;
    }

    #[Override]
    public function setDefaultArgument(string $name, bool|float|int|string|Stringable|null $value): void {}
}
