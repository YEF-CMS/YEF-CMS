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
use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;

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
    private const string TITLE_KEY = 'module.title';

    private ?LoadedModules $loaded = null;

    public function __construct(
        private readonly string $modulesDirectory,
        private readonly EnabledModules $enabledModules,
        private readonly ManifestReader $manifests,
        private readonly ModuleAutoloader $autoloader,
        private readonly LoggerInterface $logger,
        private readonly Texts $coreTexts,
        private readonly ModuleTexts $moduleTexts,
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
                $statuses[] = $this->failed(basename($directory), basename($directory), '', $invalidManifest->getMessage(), $invalidManifest->getMessage());
                continue;
            }

            if (!$manifest->supports($this->coreVersion)) {
                $statuses[] = $this->failedBecause(
                    $manifest,
                    ModuleLoadFailed::because('modules.reason.incompatible', [
                        'needs' => $manifest->coreConstraint,
                        'current' => $this->coreVersion,
                    ]),
                );
                continue;
            }

            if (!in_array($manifest->name->value, $enabled, true)) {
                $statuses[] = new ModuleStatus($manifest->name->value, $this->titleOf($manifest), $manifest->version, ModuleState::Disabled);
                continue;
            }

            try {
                $added = $this->register($manifest, $directory, $owners);
            } catch (ModuleLoadFailed $moduleLoadFailed) {
                $statuses[] = $this->failedBecause($manifest, $moduleLoadFailed);
                continue;
            }

            foreach ($added as $definition) {
                $owners[$definition->id->value] = $manifest->name->value;
                $contentTypes[] = $definition;
            }

            $statuses[] = new ModuleStatus($manifest->name->value, $this->titleOf($manifest), $manifest->version, ModuleState::Loaded);
        }

        foreach ($enabled as $name) {
            if (!in_array(new ModuleName($name)->directoryName(), $installedDirectories, true)) {
                $statuses[] = $this->failed($name, $name, '', $this->coreTexts->t('modules.reason.not_installed'), 'Enabled, but not installed.');
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
            throw ModuleLoadFailed::because('modules.reason.class_missing', ['class' => $entry]);
        }

        if (!is_subclass_of($entry, ModuleInterface::class)) {
            throw ModuleLoadFailed::because('modules.reason.not_a_module', ['class' => $entry]);
        }

        $context = new CollectingModuleContext($this->moduleTexts->of($manifest->name));
        try {
            new $entry()->register($context);
        } catch (Throwable $throwable) {
            throw ModuleLoadFailed::because('modules.reason.registration_failed', ['reason' => $throwable->getMessage()], $throwable);
        }

        foreach ($context->contentTypes() as $definition) {
            if (isset($owners[$definition->id->value])) {
                throw ModuleLoadFailed::because('modules.reason.type_taken', [
                    'type' => $definition->id->value,
                    'module' => $owners[$definition->id->value],
                ]);
            }
        }

        return $context->contentTypes();
    }

    private function failedBecause(ModuleManifest $manifest, ModuleLoadFailed $failure): ModuleStatus
    {
        return $this->failed(
            $manifest->name->value,
            $this->titleOf($manifest),
            $manifest->version,
            $this->coreTexts->t($failure->key, $failure->parameters),
            $failure->getMessage(),
        );
    }

    /**
     * @param string $reason What the admin shows, in the language of the site.
     * @param string $technicalReason What goes to the log.
     */
    private function failed(string $id, string $title, string $version, string $reason, string $technicalReason): ModuleStatus
    {
        $this->logger->warning(sprintf('Module "%s" is not available: %s', $id, $technicalReason));

        return new ModuleStatus($id, $title, $version, ModuleState::Failed, $reason);
    }

    /**
     * The title of the module in the language of the site: the text "module.title" of the module,
     * or the title of the manifest, which is in English, when the module has no such text.
     */
    private function titleOf(ModuleManifest $manifest): string
    {
        $translated = $this->moduleTexts->of($manifest->name)->t(self::TITLE_KEY);

        return $translated === self::TITLE_KEY ? $manifest->title : $translated;
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
