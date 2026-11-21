<?php

declare(strict_types=1);

namespace Yef\Module;

use RuntimeException;
use Throwable;

use function json_encode;
use function sprintf;

/**
 * An enabled module could not be registered. Only the loader sees it: it becomes the reason in the module's status.
 *
 * The reason is a text of the core (a key with its values), so the admin can show it in its language.
 * The message of the exception is the technical form of the same thing, for the log.
 */
final class ModuleLoadFailed extends RuntimeException
{
    /**
     * @param array<string, string|int> $parameters
     */
    private function __construct(
        public readonly string $key,
        public readonly array $parameters,
        ?Throwable $previous,
    ) {
        parent::__construct(sprintf('%s %s', $key, json_encode($parameters, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)), 0, $previous);
    }

    /**
     * @param string $key Key of a text of the core, see "modules.reason.*" in the admin texts.
     * @param array<string, string|int> $parameters
     */
    public static function because(string $key, array $parameters = [], ?Throwable $previous = null): self
    {
        return new self($key, $parameters, $previous);
    }
}
