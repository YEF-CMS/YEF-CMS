<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Yef\Admin\Modules\DisableAction;
use Yef\Admin\Modules\EnableAction;
use Yef\Admin\Modules\ListAction;
use Yef\Admin\Modules\ModuleRow;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Module\DisableModule;
use Yef\Application\Module\EnableModule;
use Yef\Application\Module\ListModules;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;
use Yef\Tests\Support\AdminHttpScenario;
use Yef\Tests\Support\InMemoryEnabledModules;
use Yef\Tests\Support\InMemoryModuleCatalog;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

#[CoversClass(ListAction::class)]
#[CoversClass(EnableAction::class)]
#[CoversClass(DisableAction::class)]
#[CoversClass(ModuleRow::class)]
final class ModuleScreensTest extends TestCase
{
    private AdminHttpScenario $admin;

    #[Override]
    protected function setUp(): void
    {
        $this->admin = new AdminHttpScenario();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->admin->cleanUp();
    }

    public function testEmptyListSaysNoModulesAreInstalled(): void
    {
        $html = $this->body($this->list([]));

        $this->assertStringContainsString('No modules are installed.', $html);
    }

    public function testListShowsTitleNameVersionAndStateOfEveryModule(): void
    {
        $html = $this->body($this->list([
            new ModuleStatus('acme/gallery', 'Photo gallery', '1.2.0', ModuleState::Loaded),
            new ModuleStatus('acme/news', 'News', '0.3.0', ModuleState::Disabled),
            new ModuleStatus('acme/old', 'Old module', '0.1.0', ModuleState::Failed, 'Needs Yef ^9.0'),
        ]));

        $this->assertStringContainsString('Photo gallery', $html);
        $this->assertStringContainsString('<code>acme/gallery</code>', $html);
        $this->assertStringContainsString('1.2.0', $html);
        $this->assertStringContainsString('text-bg-success">Enabled<', $html);
        $this->assertStringContainsString('text-bg-secondary">Disabled<', $html);
        $this->assertStringContainsString('text-bg-danger">Failed<', $html);
        $this->assertStringContainsString('Needs Yef ^9.0', $html);
    }

    public function testListOffersToEnableADisabledModuleAndToDisableAnEnabledOne(): void
    {
        $html = $this->body($this->list([
            new ModuleStatus('acme/gallery', 'Photo gallery', '1.2.0', ModuleState::Loaded),
            new ModuleStatus('acme/news', 'News', '0.3.0', ModuleState::Disabled),
        ]));

        $this->assertStringContainsString('action="/route/admin.modules.enable"', $html);
        $this->assertStringContainsString('action="/route/admin.modules.disable"', $html);
        $this->assertSame(1, substr_count($html, 'class="btn btn-sm btn-outline-success"'));
        $this->assertStringContainsString('data-bs-target="#disable-acme__gallery"', $html);
    }

    public function testFailedModuleCanBeDisabledButNotEnabled(): void
    {
        $html = $this->body($this->list([new ModuleStatus('acme/old', 'Old module', '0.1.0', ModuleState::Failed, 'Broken')]));

        $this->assertStringContainsString('data-bs-target="#disable-acme__old"', $html);
        $this->assertStringNotContainsString('btn-outline-success', $html);
    }

    public function testModuleWithoutAUsableNameHasNoButtons(): void
    {
        $html = $this->body($this->list([new ModuleStatus('Broken Dir', 'Broken Dir', '', ModuleState::Failed, 'No manifest')]));

        $this->assertStringContainsString('No manifest', $html);
        $this->assertStringNotContainsString('data-bs-toggle="modal"', $html);
        $this->assertStringNotContainsString('btn-outline-success', $html);
    }

    public function testDisableDialogWarnsAboutThePagesOfTheModule(): void
    {
        $html = $this->body($this->list([new ModuleStatus('acme/gallery', 'Photo gallery', '1.2.0', ModuleState::Loaded)]));

        $this->assertStringContainsString('Disable the module &quot;Photo gallery&quot;?', $html);
        $this->assertStringContainsString('their data is kept', $html);
    }

    public function testListEscapesTitleAndReason(): void
    {
        $html = $this->body($this->list([new ModuleStatus('acme/x', '<b>Bold</b>', '1', ModuleState::Failed, '<script>x</script>')]));

        $this->assertStringNotContainsString('<b>Bold</b>', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function testEnablingSwitchesTheModuleOnAndGoesBackToTheList(): void
    {
        $enabled = new InMemoryEnabledModules();

        $response = $this->enable('acme__gallery', $this->catalog(ModuleState::Disabled), $enabled);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertEquals([new ModuleName('acme/gallery')], $enabled->all());
        $this->assertSame('modules.enabled', $this->admin->flash->get('success'));
    }

    public function testEnablingAnUnknownModuleIsNotFound(): void
    {
        $response = $this->enable('acme__nothing', $this->catalog(ModuleState::Disabled), new InMemoryEnabledModules());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEnablingSomethingThatIsNotAModuleNameIsNotFound(): void
    {
        $response = $this->enable('Not A Name', $this->catalog(ModuleState::Disabled), new InMemoryEnabledModules());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEnablingABrokenModuleIsRefusedWithAMessage(): void
    {
        $enabled = new InMemoryEnabledModules();

        $response = $this->enable('acme__gallery', $this->catalog(ModuleState::Failed, 'Needs Yef ^9.0'), $enabled);

        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertSame('modules.error.cannot_enable', $this->admin->flash->get('danger'));
        $this->assertSame([], $enabled->all());
    }

    public function testDisablingSwitchesTheModuleOff(): void
    {
        $enabled = new InMemoryEnabledModules(['acme/gallery', 'acme/news']);

        $response = $this->disable('acme__gallery', $enabled);

        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertEquals([new ModuleName('acme/news')], $enabled->all());
        $this->assertSame('modules.disabled', $this->admin->flash->get('success'));
    }

    public function testDisablingSomethingThatIsNotAModuleNameIsNotFound(): void
    {
        $response = $this->disable('nope', new InMemoryEnabledModules());

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * @param list<ModuleStatus> $statuses
     */
    private function list(array $statuses): ResponseInterface
    {
        return new ListAction(new ListModules(new InMemoryModuleCatalog($statuses)), $this->admin->view)(
            $this->admin->request('GET', '/modules'),
        );
    }

    private function enable(string $slug, InMemoryModuleCatalog $catalog, InMemoryEnabledModules $enabled): ResponseInterface
    {
        $action = new EnableAction(
            new EnableModule($catalog, $enabled),
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $this->route($slug),
        );

        return $action($this->admin->request('POST', '/modules/' . $slug . '/enable'));
    }

    private function disable(string $slug, InMemoryEnabledModules $enabled): ResponseInterface
    {
        $action = new DisableAction(
            new DisableModule($enabled),
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $this->route($slug),
        );

        return $action($this->admin->request('POST', '/modules/' . $slug . '/disable'));
    }

    private function catalog(ModuleState $state, string $reason = ''): InMemoryModuleCatalog
    {
        return new InMemoryModuleCatalog([new ModuleStatus('acme/gallery', 'Photo gallery', '1.0.0', $state, $reason)]);
    }

    /**
     * What the router knows about a request for the module with this address part.
     */
    private function route(string $slug): CurrentRoute
    {
        $route = new CurrentRoute();
        /** @psalm-suppress InternalMethod The router fills this in itself; tests have to do it by hand. */
        $route->setRouteWithArguments(Route::post('/modules/{name}/enable'), ['name' => $slug]);

        return $route;
    }

    private function body(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
