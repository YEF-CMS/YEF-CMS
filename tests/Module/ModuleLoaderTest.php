<?php

declare(strict_types=1);

namespace Yef\Tests\Module;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;
use Yef\Module\CollectingModuleContext;
use Yef\Module\LoadedModules;
use Yef\Module\ManifestReader;
use Yef\Module\ModuleAutoloader;
use Yef\Module\ModuleLoader;
use Yef\Module\ModuleLoadFailed;
use Yef\Tests\Support\InMemoryEnabledModules;
use Yef\Tests\Support\ModuleFixtures;
use Yef\Tests\Support\RecordingLogger;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(ModuleLoader::class)]
#[CoversClass(ModuleAutoloader::class)]
#[CoversClass(CollectingModuleContext::class)]
#[CoversClass(LoadedModules::class)]
#[CoversClass(ModuleLoadFailed::class)]
final class ModuleLoaderTest extends TestCase
{
    private TemporaryDirectory $directory;

    private ModuleFixtures $fixtures;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->fixtures = new ModuleFixtures($this->directory->path);
        $this->logger = new RecordingLogger();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testEnabledModuleAddsItsContentType(): void
    {
        $this->fixtures->install('acme/gallery', ModuleFixtures::addsContentType('gallery'));

        $loader = $this->loader(['acme/gallery']);

        $this->assertSame(['gallery'], $this->typeIds($loader));
        $this->assertSame([ModuleState::Loaded], $this->states($loader));
    }

    public function testStatusCarriesTheTitleForUsers(): void
    {
        $this->fixtures->install('acme/gallery', manifest: ['title' => 'Photo gallery', 'version' => '2.1.0']);

        $status = $this->statusAt($this->loader(['acme/gallery']), 0);

        $this->assertSame('Photo gallery', $status->title);
        $this->assertSame('acme/gallery', $status->id);
        $this->assertSame('2.1.0', $status->version);
    }

    public function testModuleThatIsNotEnabledIsListedButNotRegistered(): void
    {
        $this->fixtures->install('acme/gallery', ModuleFixtures::addsContentType('gallery'));

        $loader = $this->loader([]);

        $this->assertSame([], $this->typeIds($loader));
        $this->assertSame([ModuleState::Disabled], $this->states($loader));
        $this->assertSame('Fixture acme/gallery', $this->statusAt($loader, 0)->title);
    }

    public function testModuleThatIsNotEnabledIsNotLoadedAtAll(): void
    {
        $directory = $this->fixtures->install('acme/gallery', 'throw new \RuntimeException("must not run");');

        $this->loader([])->statuses();

        $this->assertFileExists($directory . '/src/Module.php');
        $this->assertSame([], $this->logger->messages);
    }

    public function testNothingIsFoundInAMissingModulesDirectory(): void
    {
        $loader = new ModuleLoader(
            $this->directory->path . '/nowhere',
            new InMemoryEnabledModules([]),
            new ManifestReader(),
            new ModuleAutoloader(),
            $this->logger,
        );

        $this->assertSame([], $loader->statuses());
        $this->assertSame([], $loader->contentTypes());
    }

    public function testFailingRegistrationDisablesOnlyThatModule(): void
    {
        $this->fixtures->install('acme/broken', 'throw new \RuntimeException("boom");');
        $this->fixtures->install('acme/gallery', ModuleFixtures::addsContentType('gallery'));

        $loader = $this->loader(['acme/broken', 'acme/gallery']);

        $this->assertSame(['gallery'], $this->typeIds($loader));
        $this->assertSame([ModuleState::Failed, ModuleState::Loaded], $this->states($loader));
        $this->assertStringContainsString('boom', $this->statusAt($loader, 0)->reason);
        $this->assertCount(1, $this->logger->messages);
        $this->assertStringContainsString('acme/broken', implode(' | ', $this->logger->messages));
    }

    public function testNothingOfAModuleThatFailedHalfwayStaysRegistered(): void
    {
        $this->fixtures->install(
            'acme/half',
            ModuleFixtures::addsContentType('half') . ' throw new \RuntimeException("later");',
        );

        $loader = $this->loader(['acme/half']);

        $this->assertSame([], $this->typeIds($loader));
        $this->assertSame([ModuleState::Failed], $this->states($loader));
    }

    public function testContentTypeOfAnotherModuleIsNotTakenOver(): void
    {
        $this->fixtures->install('acme/first', ModuleFixtures::addsContentType('shared'));
        $this->fixtures->install('acme/second', ModuleFixtures::addsContentType('shared'));

        $loader = $this->loader(['acme/first', 'acme/second']);

        $this->assertSame([ModuleState::Loaded, ModuleState::Failed], $this->states($loader));
        $this->assertStringContainsString('acme/first', $this->statusAt($loader, 1)->reason);
        $this->assertCount(1, $loader->contentTypes());
    }

    public function testModuleAddingATypeTwiceFails(): void
    {
        $this->fixtures->install(
            'acme/twice',
            ModuleFixtures::addsContentType('dup') . ModuleFixtures::addsContentType('dup'),
        );

        $this->assertSame([ModuleState::Failed], $this->states($this->loader(['acme/twice'])));
    }

    public function testModuleWithoutItsEntryClassFails(): void
    {
        $this->fixtures->install('acme/empty', withEntryClass: false);

        $loader = $this->loader(['acme/empty']);

        $this->assertSame([ModuleState::Failed], $this->states($loader));
        $this->assertStringContainsString('was not found', $this->statusAt($loader, 0)->reason);
    }

    public function testEntryClassThatIsNotAModuleFails(): void
    {
        $this->fixtures->install('acme/odd', entrySource: '<?php

namespace {namespace};

final class Module {}
');

        $loader = $this->loader(['acme/odd']);

        $this->assertSame([ModuleState::Failed], $this->states($loader));
        $this->assertStringContainsString('ModuleInterface', $this->statusAt($loader, 0)->reason);
    }

    public function testBrokenManifestFailsWithTheDirectoryAsItsName(): void
    {
        $directory = $this->fixtures->install('acme/news');
        file_put_contents($directory . '/module.json', '{ nope');

        $loader = $this->loader(['acme/news']);

        $status = $this->statusAt($loader, 0);
        $this->assertSame(ModuleState::Failed, $status->state);
        $this->assertSame('acme__news', $status->id);
        $this->assertSame('acme__news', $status->title);
        $this->assertCount(1, $this->logger->messages);
        $this->assertCount(1, $loader->statuses());
    }

    public function testModuleForAnotherCoreVersionIsNotLoaded(): void
    {
        $this->fixtures->install('acme/future', ModuleFixtures::addsContentType('future'), ['requires' => ['yef' => '^9.0']]);

        $loader = $this->loader(['acme/future']);

        $this->assertSame([], $this->typeIds($loader));
        $this->assertSame([ModuleState::Failed], $this->states($loader));
        $this->assertStringContainsString('^9.0', $this->statusAt($loader, 0)->reason);
    }

    public function testEnabledButMissingModuleIsReported(): void
    {
        $loader = $this->loader(['acme/gone']);

        $status = $this->statusAt($loader, 0);
        $this->assertSame(ModuleState::Failed, $status->state);
        $this->assertSame('acme/gone', $status->id);
        $this->assertStringContainsString('not installed', $status->reason);
    }

    public function testDirectoriesWithoutAModuleAreReportedAndHiddenOnesIgnored(): void
    {
        mkdir($this->directory->path . '/stray');
        mkdir($this->directory->path . '/.git');
        file_put_contents($this->directory->path . '/README.md', 'not a module');

        $loader = $this->loader([]);

        $this->assertSame(['stray'], array_map(static fn(ModuleStatus $status): string => $status->id, $loader->statuses()));
    }

    public function testModulesAreListedInDirectoryOrder(): void
    {
        $this->fixtures->install('acme/zebra');
        $this->fixtures->install('acme/apple');

        $ids = array_map(static fn(ModuleStatus $status): string => $status->id, $this->loader([])->statuses());

        $this->assertSame(['acme/apple', 'acme/zebra'], $ids);
    }

    public function testBundledTextModuleRegistersTheTextType(): void
    {
        $loader = new ModuleLoader(
            dirname(__DIR__, 2) . '/modules',
            new InMemoryEnabledModules(['yef-cms/text']),
            new ManifestReader(),
            new ModuleAutoloader(),
            $this->logger,
        );

        $this->assertSame(['text'], $this->typeIds($loader));
        $this->assertSame('Text pages', $this->statusAt($loader, 0)->title);
        $this->assertSame([], $this->logger->messages);
    }

    private function statusAt(ModuleLoader $loader, int $index): ModuleStatus
    {
        return $loader->statuses()[$index] ?? throw new LogicException(sprintf('There is no module status #%d.', $index));
    }

    /**
     * @param list<string> $enabled
     */
    private function loader(array $enabled): ModuleLoader
    {
        return new ModuleLoader(
            $this->directory->path,
            new InMemoryEnabledModules($enabled),
            new ManifestReader(),
            new ModuleAutoloader(),
            $this->logger,
        );
    }

    /**
     * @return list<string>
     */
    private function typeIds(ModuleLoader $loader): array
    {
        return array_map(static fn(ContentTypeDefinition $definition): string => $definition->id->value, $loader->contentTypes());
    }

    /**
     * @return list<ModuleState>
     */
    private function states(ModuleLoader $loader): array
    {
        return array_map(static fn(ModuleStatus $status): ModuleState => $status->state, $loader->statuses());
    }
}
