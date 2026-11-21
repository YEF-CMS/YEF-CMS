<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Yef\Storage\Files;

use function bin2hex;
use function file_put_contents;
use function json_encode;
use function random_bytes;
use function sprintf;
use function str_replace;

/**
 * Builds module directories for loader tests. Every module gets a namespace of its own, because a class
 * loaded in one test stays declared for the rest of the run.
 */
final readonly class ModuleFixtures
{
    private Files $files;

    public function __construct(public string $root)
    {
        $this->files = new Files();
        $this->files->ensureDirectory($root);
    }

    /**
     * A body for `register()` that adds a content type with the given id.
     */
    public static function addsContentType(string $id): string
    {
        return sprintf(
            '$context->addContentType(new \Yef\Domain\ContentType\ContentTypeDefinition('
            . 'new \Yef\Domain\ContentType\ContentTypeId(\'%s\'), $context->texts()->t(\'type.title\'), new \YefCms\TextPages\TextPageForm($context->texts()), '
            . 'new \YefCms\TextPages\TextPayloadFactory($context->texts()), '
            . 'new \YefCms\TextPages\TextPayloadSerializer($context->texts()), '
            . 'new \YefCms\TextPages\TextContentRenderer()));',
            $id,
        );
    }

    /**
     * @param array<string, mixed> $manifest Replaces fields of the default manifest; a null value drops the field.
     * @param string|null $directory Directory name when it should differ from the module name.
     * @param bool $withEntryClass Whether the entry class file is written.
     * @param string|null $entrySource Full source of the entry class file, with `{namespace}` for the module namespace.
     *
     * @return string The module directory.
     */
    public function install(
        string $name,
        string $registerBody = '',
        array $manifest = [],
        ?string $directory = null,
        bool $withEntryClass = true,
        ?string $entrySource = null,
    ): string {
        $namespace = 'FixtureModule' . bin2hex(random_bytes(4)) . '\\';
        $directory = $this->root . '/' . ($directory ?? str_replace('/', '__', $name));
        $this->files->ensureDirectory($directory . '/src');

        $manifest = [...[
            'schema' => 1,
            'name' => $name,
            'title' => 'Fixture ' . $name,
            'version' => '1.0.0',
            'namespace' => $namespace,
            'entry' => $namespace . 'Module',
            'requires' => ['yef' => '^0.1'],
        ], ...$manifest];
        file_put_contents($directory . '/module.json', json_encode(array_filter($manifest, static fn(mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR));

        if ($entrySource !== null) {
            file_put_contents($directory . '/src/Module.php', str_replace('{namespace}', rtrim($namespace, '\\'), $entrySource));
        } elseif ($withEntryClass) {
            file_put_contents($directory . '/src/Module.php', sprintf(
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\nfinal class Module implements \\Yef\\Domain\\Module\\ModuleInterface\n{\n    public function register(\\Yef\\Domain\\Module\\ModuleContext \$context): void\n    {\n        %s\n    }\n}\n",
                rtrim($namespace, '\\'),
                $registerBody,
            ));
        }

        return $directory;
    }
}
