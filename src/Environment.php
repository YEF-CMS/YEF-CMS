<?php

declare(strict_types=1);

namespace Yef;

use RuntimeException;

use function in_array;
use function sprintf;

/**
 * Application environment, read from process environment variables.
 *
 * On shared hosting variables usually cannot be set, so the defaults are the production ones:
 * `APP_ENV=prod`, `APP_DEBUG=false`. Local development (Docker) sets them explicitly.
 */
final class Environment
{
    public const string DEV = 'dev';

    public const string TEST = 'test';

    public const string PROD = 'prod';

    public const array ENVIRONMENTS = [
        self::DEV,
        self::TEST,
        self::PROD,
    ];

    /** @var non-empty-string */
    private static string $appEnv = self::PROD;

    private static bool $appDebug = false;

    public static function prepare(): void
    {
        self::$appEnv = self::resolveEnvironment();
        self::$appDebug = self::resolveBoolean('APP_DEBUG', false);
    }

    /**
     * @return non-empty-string
     */
    public static function appEnv(): string
    {
        return self::$appEnv;
    }

    public static function appDebug(): bool
    {
        return self::$appDebug;
    }

    /**
     * @return non-empty-string
     */
    private static function resolveEnvironment(): string
    {
        $raw = self::getRawValue('APP_ENV');
        $environment = $raw === null || $raw === '' ? self::PROD : $raw;

        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new RuntimeException(
                sprintf(
                    'APP_ENV="%s" is invalid. Valid values are "%s".',
                    $environment,
                    implode('", "', self::ENVIRONMENTS),
                ),
            );
        }

        return $environment;
    }

    private static function resolveBoolean(string $key, bool $default): bool
    {
        $value = self::getRawValue($key);

        return $value === null
            ? $default
            : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }

    private static function getRawValue(string $key): ?string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
