<?php

declare(strict_types=1);

namespace Yef\Module;

use Override;
use Psr\Log\LoggerInterface;
use Throwable;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Module\CoreVersion;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleCatalog;
use Yef\Domain\Module\ModuleInterface;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;

use function array_map;
use function basename;
use function class_exists;
use function in_array;
use function is_dir;
use function is_subclass_of;
use function scandir;
use function sort;
use function sprintf;
use function str_starts_with;

/**
 * Finds the installed modules, registers the enabled ones and reports the state of every module.
 *
 * A module that cannot be used (bad manifest, incompatible, failing registration) is reported and skipped:
 * the site and the other modules keep working. The work is done once per instance.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final class ModuleLoader implements ModuleCatalog
{
    private ?LoadedModules $loaded = null;

    public function __construct(
        private readonly string $modulesDirectory,
        private readonly EnabledModules $enabledModules,
        private readonly ManifestReader $manifests,
        private readonly ModuleAutoloader $autoloader,
        private readonly LoggerInterface $logger,
        private readonly string $coreVersion = CoreVersion::CURRENT,
    ) {}

    #[Override]
    public function statuses(): array
    {
        return $this->loaded()->statuses;
    }

    /**
     * @return list<ContentTypeDefinition> Content types of all enabled modules that registered successfully.
     */
    public function contentTypes(): array
    {
        return $this->loaded()->contentTypes;
    }

    private function loaded(): LoadedModules
    {
        return $this->loaded ??= $this->load();
    }

    private function load(): LoadedModules
    {
        $enabled = array_map(static fn(ModuleName $name): string => $name->value, $this->enabledModules->all());
        $directories = $this->moduleDirectories();
        $installedDirectories = array_map(basename(...), $directories);
        $statuses = [];
        $contentTypes = [];
        $owners = [];


        foreach ($directories as $directory) {
            try {
                $manifest = $this->manifests->read($directory);
            } catch (InvalidManifest $invalidManifest) {
                $statuses[] = $this->failed(basename($directory), basename($directory), '', $invalidManifest->getMessage());
                continue;
            }

            if (!$manifest->supports($this->coreVersion)) {
                $reason = sprintf('Needs Yef %s, but this is %s.', $manifest->coreConstraint, $this->coreVersion);
                $statuses[] = $this->failed($manifest->name->value, $manifest->title, $manifest->version, $reason);
                continue;
            }

            if (!in_array($manifest->name->value, $enabled, true)) {
                $statuses[] = new ModuleStatus($manifest->name->value, $manifest->title, $manifest->version, ModuleState::Disabled);
                continue;
            }

            try {
                $added = $this->register($manifest, $directory, $owners);
            } catch (ModuleLoadFailed $moduleLoadFailed) {
                $statuses[] = $this->failed($manifest->name->value, $manifest->title, $manifest->version, $moduleLoadFailed->getMessage());
                continue;
            }

            foreach ($added as $definition) {
                $owners[$definition->id->value] = $manifest->name->value;
                $contentTypes[] = $definition;
            }

            $statuses[] = new ModuleStatus($manifest->name->value, $manifest->title, $manifest->version, ModuleState::Loaded);
        }

        foreach ($enabled as $name) {
            if (!in_array(new ModuleName($name)->directoryName(), $installedDirectories, true)) {
                $statuses[] = $this->failed($name, $name, '', 'Enabled, but not installed.');
            }
        }

        return new LoadedModules($statuses, $contentTypes);
    }

    /**
     * @param array<string, string> $owners Content type id => the module that already provides it.
     *
     * @return list<ContentTypeDefinition>
     *
     * @throws ModuleLoadFailed
     */
    private function register(ModuleManifest $manifest, string $directory, array $owners): array
    {
        $this->autoloader->add($manifest->namespace, $directory . '/src');

        $entry = $manifest->entry;
        if (!class_exists($entry)) {
            throw ModuleLoadFailed::because(sprintf('Class %s was not found.', $entry));
        }

        if (!is_subclass_of($entry, ModuleInterface::class)) {
            throw ModuleLoadFailed::because(sprintf('Class %s does not implement ModuleInterface.', $entry));
        }

        $context = new CollectingModuleContext();
        try {
            new $entry()->register($context);
        } catch (Throwable $throwable) {
            throw ModuleLoadFailed::because(sprintf('Registration failed: %s', $throwable->getMessage()), $throwable);
        }

        foreach ($context->contentTypes() as $definition) {
            if (isset($owners[$definition->id->value])) {
                throw ModuleLoadFailed::because(sprintf(
                    'Content type "%s" is already provided by module "%s".',
                    $definition->id,
                    $owners[$definition->id->value],
                ));
            }
        }

        return $context->contentTypes();
    }

    private function failed(string $id, string $title, string $version, string $reason): ModuleStatus
    {
        $this->logger->warning(sprintf('Module "%s" is not available: %s', $id, $reason));

        return new ModuleStatus($id, $title, $version, ModuleState::Failed, $reason);
    }

    /**
     * @return list<string> Module directories in alphabetical order.
     */
    private function moduleDirectories(): array
    {
        $names = is_dir($this->modulesDirectory) ? scandir($this->modulesDirectory) : false;
        $directories = [];
        foreach ($names === false ? [] : $names as $name) {
            $path = $this->modulesDirectory . '/' . $name;
            if (!str_starts_with($name, '.') && is_dir($path)) {
                $directories[] = $path;
            }
        }

        sort($directories);

        return $directories;
    }
}
