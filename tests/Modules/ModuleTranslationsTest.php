<?php

declare(strict_types=1);

namespace Yef\Tests\Modules;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_keys;
use function basename;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function preg_match_all;
use function sort;
use function trim;

/**
 * The texts of the bundled modules, which are the example other authors follow: English is complete,
 * every other language has the same keys and placeholders, and every text the code asks for exists.
 */
#[CoversNothing]
final class ModuleTranslationsTest extends TestCase
{
    private const string MODULES = __DIR__ . '/../../modules';

    public function testEveryModuleHasEnglishTextsWithATitle(): void
    {
        foreach ($this->modules() as $module) {
            $english = $this->messages($module, 'en');
            $this->assertArrayHasKey('module.title', $english, $module);
            $this->assertNotSame('', trim($english['module.title'] ?? ''), $module);
        }
    }

    public function testEveryLanguageOfAModuleHasExactlyTheKeysOfEnglish(): void
    {
        foreach ($this->modules() as $module) {
            $english = array_keys($this->messages($module, 'en'));
            foreach ($this->languages($module) as $language) {
                $other = array_keys($this->messages($module, $language));
                $this->assertSame([], array_diff($english, $other), $module . ' is missing keys in ' . $language);
                $this->assertSame([], array_diff($other, $english), $module . ' has keys that are not in English: ' . $language);
            }
        }
    }

    public function testEveryLanguageOfAModuleKeepsThePlaceholdersOfEnglish(): void
    {
        foreach ($this->modules() as $module) {
            foreach ($this->languages($module) as $language) {
                $translated = $this->messages($module, $language);
                foreach ($this->messages($module, 'en') as $key => $english) {
                    $this->assertSame($this->placeholders($english), $this->placeholders($translated[$key] ?? ''), $module . ' ' . $language . ': ' . $key);
                }
            }
        }
    }

    public function testNoTextOfAModuleIsEmpty(): void
    {
        foreach ($this->modules() as $module) {
            foreach ($this->languages($module) as $language) {
                foreach ($this->messages($module, $language) as $key => $text) {
                    $this->assertNotSame('', trim($text), $module . ' ' . $language . ': ' . $key);
                }
            }
        }
    }

    public function testEveryTextTheCodeOfAModuleAsksForIsDefined(): void
    {
        $asked = 0;
        foreach ($this->modules() as $module) {
            $english = $this->messages($module, 'en');
            foreach ($this->glob(self::MODULES . '/' . $module . '/src/*.php') as $file) {
                preg_match_all("/->t\\('([^']+)'/", (string) file_get_contents($file), $found);
                foreach ($found[1] ?? [] as $key) {
                    ++$asked;
                    $this->assertArrayHasKey($key, $english, $key . ' is used in ' . $file);
                }
            }
        }

        $this->assertGreaterThan(0, $asked);
    }

    public function testTheBundledModuleSpeaksEveryLanguageOfTheAdmin(): void
    {
        /** @var array{yef: array{admin: array{languages: array<string, string>}}} $params */
        $params = require __DIR__ . '/../../config/common/params.php';

        foreach (array_keys($params['yef']['admin']['languages']) as $language) {
            $this->assertFileExists(self::MODULES . '/yef-cms__text/messages/' . $language . '/yef-cms__text.php', $language);
        }
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern, bool $directoriesOnly = false): array
    {
        $found = $directoriesOnly ? glob($pattern, \GLOB_ONLYDIR) : glob($pattern);

        return $found === false ? [] : $found;
    }

    /**
     * @return list<string> Directory names of the modules that have texts.
     */
    private function modules(): array
    {
        $modules = [];
        foreach ($this->glob(self::MODULES . '/*', true) as $directory) {
            if (is_dir($directory . '/messages')) {
                $modules[] = basename($directory);
            }
        }

        $this->assertNotSame([], $modules);

        return $modules;
    }

    /**
     * @return list<string>
     */
    private function languages(string $module): array
    {
        $languages = [];
        foreach ($this->glob(self::MODULES . '/' . $module . '/messages/*', true) as $directory) {
            if (is_file($directory . '/' . $module . '.php')) {
                $languages[] = basename($directory);
            }
        }

        return $languages;
    }

    /**
     * @return array<string, string>
     */
    private function messages(string $module, string $language): array
    {
        /** @psalm-suppress UnresolvableInclude The file is chosen by module and language. */
        $messages = require self::MODULES . '/' . $module . '/messages/' . $language . '/' . $module . '.php';
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
}
