<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_keys;
use function glob;
use function preg_match_all;
use function sort;
use function trim;

/**
 * The texts of the admin in every language: the same set of keys as in English, the same placeholders,
 * nothing empty, and every key the templates ask for exists.
 */
#[CoversNothing]
final class TranslationsTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../../src/Admin';

    /**
     * @return iterable<string, array{string}>
     */
    public static function languages(): iterable
    {
        foreach (self::offeredCodes() as $code) {
            if ($code !== 'en') {
                yield $code => [$code];
            }
        }
    }

    #[DataProvider('languages')]
    public function testEveryLanguageHasExactlyTheKeysOfEnglish(string $code): void
    {
        $english = array_keys($this->messages('en'));
        $other = array_keys($this->messages($code));

        $this->assertSame([], array_diff($english, $other), 'Missing in ' . $code);
        $this->assertSame([], array_diff($other, $english), 'Not in English: ' . $code);
    }

    #[DataProvider('languages')]
    public function testEveryLanguageKeepsThePlaceholdersOfEnglish(string $code): void
    {
        $translated = $this->messages($code);

        foreach ($this->messages('en') as $key => $english) {
            $this->assertSame($this->placeholders($english), $this->placeholders($translated[$key] ?? ''), $code . ': ' . $key);
        }
    }

    #[DataProvider('languages')]
    public function testNoTextIsEmpty(string $code): void
    {
        foreach ($this->messages($code) as $key => $text) {
            $this->assertNotSame('', trim($text), $code . ': ' . $key);
        }
    }

    public function testNoTextOfEnglishIsEmpty(): void
    {
        foreach ($this->messages('en') as $key => $text) {
            $this->assertNotSame('', trim($text), $key);
        }
    }

    public function testEveryTextTheTemplatesAskForIsDefined(): void
    {
        $defined = $this->messages('en');
        $asked = [];
        foreach ($this->templates() as $template) {
            preg_match_all("/\\bt\\('([^']+)'/", (string) file_get_contents($template), $found);
            foreach ($found[1] ?? [] as $key) {
                $asked[$key] = $template;
            }
        }

        $this->assertNotSame([], $asked);
        foreach ($asked as $key => $template) {
            // A key that ends with a dot is a prefix, completed by the template ("modules.problem." . kind).
            if (str_ends_with($key, '.')) {
                $this->assertNotSame([], array_filter(array_keys($defined), static fn(string $known): bool => str_starts_with($known, $key)), $key . ' is used in ' . $template);

                continue;
            }

            $this->assertArrayHasKey($key, $defined, $key . ' is used in ' . $template);
        }
    }

    public function testEveryOfferedLanguageHasItsTexts(): void
    {
        foreach (self::offeredCodes() as $code) {
            $this->assertFileExists(self::ROOT . '/messages/' . $code . '/admin.php', $code);
        }
    }

    public function testEveryOfferedLanguageHasTheEditorTranslation(): void
    {
        foreach (self::offeredCodes() as $code) {
            if ($code !== 'en') {
                $this->assertFileExists(__DIR__ . '/../../public/admin/assets/vendor/ckeditor5/translations/' . $code . '.umd.js', $code);
            }
        }
    }

    public function testEveryLanguageHasAnOwnNameInTheConfiguration(): void
    {
        foreach (self::offeredNames() as $code => $name) {
            $this->assertNotSame('', trim($name), $code);
        }
    }

    /**
     * @return list<string>
     */
    private static function offeredCodes(): array
    {
        return array_map(strval(...), array_keys(self::offeredNames()));
    }

    /**
     * @return array<string, string>
     */
    private static function offeredNames(): array
    {
        /** @var array{yef: array{admin: array{languages: array<string, string>}}} $params */
        $params = require __DIR__ . '/../../config/common/params.php';

        return $params['yef']['admin']['languages'];
    }

    /**
     * @return array<string, string>
     */
    private function messages(string $code): array
    {
        /** @psalm-suppress UnresolvableInclude The file is chosen by language code. */
        $messages = require self::ROOT . '/messages/' . $code . '/admin.php';
        $this->assertIsArray($messages);

        $texts = [];
        /** @psalm-suppress MixedAssignment The shape is checked right below. */
        foreach ($messages as $key => $text) {
            $this->assertIsString($text);
            $texts[(string) $key] = $text;
        }

        return $texts;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $found);
        $names = $found[1] ?? [];
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function templates(): array
    {
        $files = [];
        foreach ([self::ROOT . '/templates/*.twig', self::ROOT . '/templates/*/*.twig'] as $pattern) {
            $matches = glob($pattern);
            $files = [...$files, ...($matches === false ? [] : $matches)];
        }

        return $files;
    }
}
