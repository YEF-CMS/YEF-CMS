<?php

declare(strict_types=1);

namespace Yef\Application\Settings;

use InvalidArgumentException;
use Yef\Domain\Settings\Language;

use function array_key_exists;
use function array_key_first;

/**
 * The languages the admin is available in, with their names written in the language itself,
 * and the one used until the owner chooses another.
 *
 * @psalm-api Built from the configuration; read by the admin adapter.
 */
final readonly class AdminLanguages
{
    public Language $default;

    /**
     * @param array<string, string> $names Native names by language code ("ru" => "Русский").
     * @param string $default Code of the language used until one is chosen; the first listed when it is not offered.
     *
     * @throws InvalidArgumentException When no language is listed or a code is not a language code.
     */
    public function __construct(
        private array $names,
        string $default,
    ) {
        $first = array_key_first($names);
        if ($first === null) {
            throw new InvalidArgumentException('At least one admin language is needed.');
        }

        foreach (array_keys($names) as $code) {
            new Language($code);
        }

        $this->default = new Language(array_key_exists($default, $names) ? $default : $first);
    }

    public function offers(Language $language): bool
    {
        return array_key_exists($language->code, $this->names);
    }

    /**
     * @return array<string, string> Native names by language code, in the order they were listed.
     */
    public function names(): array
    {
        return $this->names;
    }
}
