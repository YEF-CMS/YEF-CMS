<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use RuntimeException;
use ZipArchive;

/**
 * Builds zip archives for tests, including the nasty ones.
 */
final class ModuleZip
{
    /**
     * @param array<string, string> $files Contents by path; a path ending with "/" is a directory.
     * @param array<string, string> $links Link targets by path (stored as symbolic links, which a module must not have).
     */
    public static function create(string $file, array $files, array $links = []): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The test archive could not be made.');
        }

        foreach ($files as $path => $contents) {
            if (str_ends_with($path, '/')) {
                $zip->addEmptyDir(rtrim($path, '/'));
            } else {
                $zip->addFromString($path, $contents);
            }
        }

        foreach ($links as $path => $target) {
            $zip->addFromString($path, $target);
            $zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        }

        $zip->close();

        return $file;
    }

    /**
     * What GitHub makes of a release: everything in one folder named after the repository and the commit.
     *
     * @param array<string, mixed> $manifest Replaces fields of the default manifest; a null value drops the field.
     * @param array<string, string> $extraFiles
     */
    public static function module(string $file, string $name, array $manifest = [], array $extraFiles = [], string $folder = 'acme-module-1a2b3c4/'): string
    {
        $namespace = 'Installed' . (preg_replace('/[^A-Za-z]/', '', ucwords($name, '/-_')) ?? '') . '\\';
        $manifest = [...[
            'schema' => 1,
            'name' => $name,
            'title' => 'Module ' . $name,
            'version' => '1.0.0',
            'namespace' => $namespace,
            'entry' => $namespace . 'Module',
            'requires' => ['yef' => '^0.1'],
        ], ...$manifest];

        $files = [
            $folder => '',
            $folder . 'module.json' => json_encode(array_filter($manifest, static fn(mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR),
            $folder . 'src/Module.php' => "<?php\n",
        ];
        foreach ($extraFiles as $path => $contents) {
            $files[$folder . $path] = $contents;
        }

        return self::create($file, $files);
    }
}
