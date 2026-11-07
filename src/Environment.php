<?php

declare(strict_types=1);

namespace Yef;

use InvalidArgumentException;

use function in_array;
use function sprintf;

/**
 * Application environment, read from process environment variables.
 *
 * On shared hosting variables usually cannot be set, so the defaults are the production ones:
 * `APP_ENV=prod`, `APP_DEBUG=false`. Local development (Docker) sets them explicitly.
 */
final readonly class Environment
{
    public const string DEV = 'dev';

    public const string TEST = 'test';

    public const string PROD = 'prod';

    public const array ENVIRONMENTS = [
        self::DEV,
        self::TEST,
        self::PROD,
    ];

    /**
     * @param non-empty-string $name
     */
    private function __construct(
        public string $name,
        public bool $debug,
    ) {}

    public static function fromProcess(): self
    {
        return self::fromValues(self::readVariable('APP_ENV'), self::readVariable('APP_DEBUG'));
    }

    /**
     * @throws InvalidArgumentException When the environment name is not one of {@see self::ENVIRONMENTS}.
     */
    public static function fromValues(?string $name, ?string $debug): self
    {
        $name = $name === null || $name === '' ? self::PROD : $name;

        if (!in_array($name, self::ENVIRONMENTS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'APP_ENV="%s" is invalid. Valid values are "%s".',
                    $name,
                    implode('", "', self::ENVIRONMENTS),
                ),
            );
        }

        return new self($name, self::parseBoolean($debug));
    }

    private static function parseBoolean(?string $value): bool
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function readVariable(string $key): ?string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
