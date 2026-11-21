<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Yef\Admin\Modules\CheckUpdatesAction;
use Yef\Admin\Modules\InstallFailures;
use Yef\Admin\Modules\InstallFormAction;
use Yef\Admin\Modules\ListAction;
use Yef\Admin\Modules\ModuleListRenderer;
use Yef\Admin\Modules\ModuleRow;
use Yef\Admin\Modules\OfferAction;
use Yef\Admin\Modules\PrepareUpdateAction;
use Yef\Admin\Modules\RemoveAction;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Module\ListModules;
use Yef\Application\ModuleInstall\OfferProblemKind;
use Yef\Application\ModuleInstall\UpdateCheckFailure;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Module\ModuleState;
use Yef\Domain\Module\ModuleStatus;
use Yef\Tests\Support\AdminHttpScenario;
use Yef\Tests\Support\InMemoryModuleCatalog;
use Yef\Tests\Support\ModuleInstallScenario;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

#[CoversClass(InstallFormAction::class)]
#[CoversClass(OfferAction::class)]
#[CoversClass(PrepareUpdateAction::class)]
#[CoversClass(RemoveAction::class)]
#[CoversClass(CheckUpdatesAction::class)]
#[CoversClass(ModuleListRenderer::class)]
#[CoversClass(ModuleRow::class)]
#[CoversClass(ListAction::class)]
#[CoversClass(InstallFailures::class)]
final class ModuleInstallScreensTest extends TestCase
{
    private AdminHttpScenario $admin;

    private ModuleInstallScenario $site;

    #[Override]
    protected function setUp(): void
    {
        $this->admin = new AdminHttpScenario();
        $this->admin->signIn('admin', 'secret1');

        $this->site = new ModuleInstallScenario();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->admin->cleanUp();
        $this->site->cleanUp();
    }

    // The address form

    public function testFormAsksForTheAddressAndSaysNothingIsInstalledYet(): void
    {
        $html = $this->body($this->installForm()($this->admin->request('GET', '/modules/install')));

        $this->assertStringContainsString('name="address"', $html);
        $this->assertStringContainsString('nothing is installed until you confirm', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
    }

    public function testGoodAddressDownloadsTheModuleAndGoesOnToTheReview(): void
    {
        $this->site->publish('acme/news', '1.2.0');

        $response = $this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/news']));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.modules.offer', $response->getHeaderLine('Location'));
        $this->assertCount(1, $this->site->waiting());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
    }

    public function testBadAddressIsExplainedAndKeptInTheForm(): void
    {
        $response = $this->installForm()($this->post('/modules/install', ['address' => 'https://evil.example/"><script>x</script>']));
        $html = $this->body($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('This is not a GitHub repository address', $html);
        $this->assertStringContainsString('value="https://evil.example/&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function testRepositoryWithoutAReleaseIsExplained(): void
    {
        $this->site->source->fail('acme/news', NoRelease::in(GitHubRepository::fromAddress('https://github.com/acme/news')));

        $response = $this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/news']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('The repository has no release. Only published releases can be installed.', $this->body($response));
        $this->assertSame([], $this->site->waiting());
    }

    public function testRateLimitAndArchiveProblemsAreExplained(): void
    {
        $this->site->source->fail('acme/news', SourceUnavailable::because(SourceUnavailable::RATE_LIMITED));
        $limited = $this->body($this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/news'])));

        $this->site->publish('acme/bad', '1.0.0');
        $this->site->source->fail('acme/bad', UnsafeArchive::because(UnsafeArchive::BAD_PATH));

        $unsafe = $this->body($this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/bad'])));

        $this->assertStringContainsString('GitHub is limiting requests', $limited);
        $this->assertStringContainsString('unsafe path', $unsafe);
    }

    public function testManifestProblemIsShownToTheAdminAsWrittenForTheAuthor(): void
    {
        $zip = \Yef\Tests\Support\ModuleZip::create($this->site->directory->path . '/plain.zip', ['repo-1/README.md' => 'x']);
        $this->site->source->release('acme/plain', '1.0.0', $zip);

        $html = $this->body($this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/plain'])));

        $this->assertStringContainsString('not a usable module', $html);
        $this->assertStringContainsString('module.json', $html);
    }

    public function testFolderThatCannotBeWrittenToIsExplained(): void
    {
        $this->site->publish('acme/news', '1.0.0');
        rmdir($this->site->modules());

        $html = $this->body($this->installForm()($this->post('/modules/install', ['address' => 'https://github.com/acme/news'])));

        $this->assertStringContainsString('cannot be written to', $html);
    }

    // The review

    public function testReviewShowsWhatIsAboutToBeInstalled(): void
    {
        $this->site->publish('acme/news', '1.2.0', manifest: ['description' => 'Latest news']);
        $token = $this->site->inspect->handle('https://github.com/acme/news')->token;

        $html = $this->body($this->offerAction($token)($this->admin->request('GET', '/modules/offer/' . $token)));

        $this->assertStringContainsString('Install the module', $html);
        $this->assertStringContainsString('Module acme/news', $html);
        $this->assertStringContainsString('<code>acme/news</code>', $html);
        $this->assertStringContainsString('Latest news', $html);
        $this->assertStringContainsString('1.2.0', $html);
        $this->assertStringContainsString('href="https://github.com/acme/news"', $html);
        $this->assertStringContainsString('<code>^0.1</code>', $html);
        $this->assertStringContainsString('runs on your site with full rights', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringNotContainsString(' disabled', $html);
    }

    public function testReviewEscapesWhatTheAuthorOfTheModuleWrote(): void
    {
        $this->site->publish('acme/news', '1.0.0', manifest: ['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>']);
        $token = $this->site->inspect->handle('https://github.com/acme/news')->token;

        $html = $this->body($this->offerAction($token)($this->admin->request('GET', '/modules/offer/' . $token)));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testReviewOfAModuleWithProblemsExplainsThemAndWillNotConfirm(): void
    {
        $this->site->publish('acme/future', '1.0.0', manifest: ['requires' => ['yef' => '^9.0']]);
        $token = $this->site->inspect->handle('https://github.com/acme/future')->token;

        $html = $this->body($this->offerAction($token)($this->admin->request('GET', '/modules/offer/' . $token)));

        $this->assertStringContainsString('needs Yef ^9.0', $html);
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn btn-primary" disabled>/', $html);
    }

    public function testReviewOfAModuleThatIsNotWaitingSendsBackToTheForm(): void
    {
        $response = $this->offerAction(str_repeat('a', 32))($this->admin->request('GET', '/modules/offer/' . str_repeat('a', 32)));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.modules.install', $response->getHeaderLine('Location'));
        $this->assertSame('modules.install.error.expired', $this->admin->flash->get('danger'));
    }

    // Confirming

    public function testRightPasswordInstallsTheModule(): void
    {
        $this->site->publish('acme/news', '1.2.0');
        $token = $this->site->inspect->handle('https://github.com/acme/news')->token;

        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, ['password' => 'secret1']));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertSame('modules.installed', $this->admin->flash->get('success'));
        $this->assertFileExists($this->site->modules() . '/acme__news/module.json');
        $this->assertSame('1.2.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
        $this->assertSame([], $this->site->enabled->all());
    }

    public function testWrongPasswordInstallsNothingAndTheModuleStaysWaiting(): void
    {
        $this->site->publish('acme/news', '1.2.0');
        $token = $this->site->inspect->handle('https://github.com/acme/news')->token;

        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, ['password' => 'wrong']));
        $html = $this->body($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('The password is wrong.', $html);
        $this->assertStringNotContainsString('value="wrong"', $html);
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
        $this->assertCount(1, $this->site->waiting());
    }

    public function testNoPasswordIsAWrongPassword(): void
    {
        $this->site->publish('acme/news', '1.2.0');
        $token = $this->site->inspect->handle('https://github.com/acme/news')->token;

        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, []));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
    }

    public function testConfirmingAModuleWithProblemsIsRefusedEvenWithTheRightPassword(): void
    {
        $this->site->publish('acme/future', '1.0.0', manifest: ['requires' => ['yef' => '^9.0']]);
        $token = $this->site->inspect->handle('https://github.com/acme/future')->token;

        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, ['password' => 'secret1']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('cannot be applied', $this->body($response));
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__future');
    }

    // Updating

    public function testUpdateButtonDownloadsTheNewReleaseAndGoesOnToTheReview(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0');

        $response = $this->prepareUpdate('acme__news')($this->post('/modules/acme__news/update'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.modules.offer', $response->getHeaderLine('Location'));
    }

    public function testReviewOfAnUpdateShowsWhatIsInstalledAndConfirmingReplacesIt(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0', extraFiles: ['new.txt' => 'new']);
        $token = $this->site->prepareUpdate->handle('acme/news')->token;

        $html = $this->body($this->offerAction($token)($this->admin->request('GET', '/modules/offer/' . $token)));
        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, ['password' => 'secret1']));

        $this->assertStringContainsString('Update the module', $html);
        $this->assertStringContainsString('Installed now', $html);
        $this->assertStringContainsString('Update module', $html);
        $this->assertSame('modules.updated', $this->admin->flash->get('success'));
        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertFileExists($this->site->modules() . '/acme__news/new.txt');
        $this->assertSame('1.1.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
    }

    public function testUpdateWithAWrongPasswordChangesNothing(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->site->publish('acme/news', '1.1.0', extraFiles: ['new.txt' => 'new']);
        $token = $this->site->prepareUpdate->handle('acme/news')->token;

        $response = $this->offerAction($token)($this->post('/modules/offer/' . $token, ['password' => 'nope']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFileDoesNotExist($this->site->modules() . '/acme__news/new.txt');
        $this->assertSame('1.0.0', $this->site->installed->find(new ModuleName('acme/news'))?->version->value);
    }

    public function testUpdateOfAModuleThatIsUpToDateIsExplained(): void
    {
        $this->installed('acme/news', '1.0.0');

        $response = $this->prepareUpdate('acme__news')($this->post('/modules/acme__news/update'));

        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertSame('modules.install.error.up_to_date', $this->admin->flash->get('danger'));
    }

    public function testUpdateOfAModuleThatIsNotFromTheInstallerIsExplained(): void
    {
        $this->prepareUpdate('acme__news')($this->post('/modules/acme__news/update'));

        $this->assertSame('modules.install.error.not_installed_by_installer', $this->admin->flash->get('danger'));
    }

    public function testUpdateOfSomethingThatIsNotAModuleNameIsNotFound(): void
    {
        $this->assertSame(404, $this->prepareUpdate('nope')($this->post('/modules/nope/update'))->getStatusCode());
    }

    // Removing

    public function testRemoveButtonRemovesTheModule(): void
    {
        $this->installed('acme/news', '1.0.0');

        $response = $this->removeAction('acme__news')($this->post('/modules/acme__news/remove'));

        $this->assertSame('/route/admin.modules', $response->getHeaderLine('Location'));
        $this->assertSame('modules.removed', $this->admin->flash->get('success'));
        $this->assertDirectoryDoesNotExist($this->site->modules() . '/acme__news');
        $this->assertNull($this->site->installed->find(new ModuleName('acme/news')));
    }

    public function testRemovingAModuleThatIsNotFromTheInstallerIsExplainedAndLeavesIt(): void
    {
        mkdir($this->site->modules() . '/acme__news');

        $this->removeAction('acme__news')($this->post('/modules/acme__news/remove'));

        $this->assertSame('modules.install.error.not_installed_by_installer', $this->admin->flash->get('danger'));
        $this->assertDirectoryExists($this->site->modules() . '/acme__news');
    }

    public function testRemovingSomethingThatIsNotAModuleNameIsNotFound(): void
    {
        $this->assertSame(404, $this->removeAction('nope')($this->post('/modules/nope/remove'))->getStatusCode());
    }

    // The list

    public function testListOffersToRemoveOnlyWhatTheInstallerBroughtAndTellsWhereItCameFrom(): void
    {
        $this->installed('acme/news', '1.0.0');
        $html = $this->body($this->listAction($this->statuses())($this->admin->request('GET', '/modules')));

        $this->assertStringContainsString('From GitHub: acme/news, release 1.0.0', $html);
        $this->assertStringContainsString('data-bs-target="#remove-acme__news"', $html);
        $this->assertStringNotContainsString('data-bs-target="#remove-yef-cms__text"', $html);
        $this->assertStringContainsString('action="/route/admin.modules.check_updates"', $html);
        $this->assertStringContainsString('href="/route/admin.modules.install"', $html);
    }

    public function testListHasNoCheckForUpdatesWhenNothingCanBeUpdated(): void
    {
        $html = $this->body($this->listAction($this->statuses())($this->admin->request('GET', '/modules')));

        $this->assertStringNotContainsString('check_updates', $html);
        $this->assertStringContainsString('href="/route/admin.modules.install"', $html);
    }

    public function testRemoveDialogWarnsThatThePagesOfTheModuleBecomeUnavailable(): void
    {
        $this->installed('acme/news', '1.0.0');
        $html = $this->body($this->listAction($this->statuses())($this->admin->request('GET', '/modules')));

        $this->assertStringContainsString('Remove the module &ldquo;Module acme/news&rdquo;?', str_replace(['“', '”'], ['&ldquo;', '&rdquo;'], $html));
        $this->assertStringContainsString('their data is kept', $html);
    }

    // Checking for updates

    public function testCheckShowsWhichModulesCanBeUpdated(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->installed('acme/blog', '2.0.0');
        $this->site->publish('acme/news', '1.1.0');
        $this->site->publish('acme/blog', '2.0.0');

        $html = $this->body($this->checkAction($this->statuses('acme/news', 'acme/blog'))($this->post('/modules/check-updates')));

        $this->assertStringContainsString('Update available: 1.0.0 → 1.1.0', $html);
        $this->assertStringContainsString('action="/route/admin.modules.update"', $html);
        $this->assertStringContainsString('Up to date', $html);
        $this->assertSame(1, substr_count($html, 'action="/route/admin.modules.update"'));
    }

    public function testCheckSaysWhyAModuleCouldNotBeChecked(): void
    {
        $this->installed('acme/news', '1.0.0');
        $this->site->source->fail('acme/news', SourceUnavailable::because(SourceUnavailable::RATE_LIMITED));

        $html = $this->body($this->checkAction($this->statuses())($this->post('/modules/check-updates')));

        $this->assertStringContainsString('GitHub request limit reached', $html);
        $this->assertStringNotContainsString('action="/route/admin.modules.update"', $html);
    }

    public function testListDoesNotCallGithubUntilAskedTo(): void
    {
        $this->installed('acme/news', '1.0.0');
        $calls = $this->site->source->latestReleaseCalls;

        $this->listAction($this->statuses())($this->admin->request('GET', '/modules'));

        $this->assertSame($calls, $this->site->source->latestReleaseCalls);
    }

    // The texts

    public function testEveryReasonTheInstallerCanGiveHasATextInEveryLanguage(): void
    {
        $keys = [];
        foreach (OfferProblemKind::cases() as $kind) {
            $keys[] = 'modules.problem.' . $kind->value;
        }

        foreach (UpdateCheckFailure::cases() as $failure) {
            $keys[] = 'modules.check_failed.' . $failure->value;
        }

        foreach ([SourceUnavailable::UNREACHABLE, SourceUnavailable::RATE_LIMITED, SourceUnavailable::BAD_ANSWER, SourceUnavailable::TOO_LARGE, SourceUnavailable::NO_CLIENT] as $reason) {
            $keys[] = 'modules.install.error.' . $reason;
        }

        foreach ([UnsafeArchive::NOT_AN_ARCHIVE, UnsafeArchive::TOO_MANY_FILES, UnsafeArchive::TOO_LARGE, UnsafeArchive::BAD_PATH, UnsafeArchive::LINK, UnsafeArchive::EMPTY, UnsafeArchive::WRITE_FAILED] as $reason) {
            $keys[] = 'modules.install.error.archive.' . $reason;
        }

        foreach (['en', 'ru', 'de', 'fr', 'it'] as $language) {
            /**
             * @var array<string, string> $messages
             *
             * @psalm-suppress UnresolvableInclude The file is chosen by language.
             */
            $messages = require __DIR__ . '/../../src/Admin/messages/' . $language . '/admin.php';
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $messages, $language . ': ' . $key);
            }
        }
    }

    // Helpers

    private function installed(string $repository, string $version): void
    {
        $this->site->publish($repository, $version);
        $this->site->install->handle($this->site->inspect->handle('https://github.com/' . $repository)->token);
    }

    /**
     * @return list<ModuleStatus>
     */
    private function statuses(string ...$installedNames): array
    {
        $names = $installedNames === [] ? ['acme/news'] : $installedNames;
        $statuses = [new ModuleStatus('yef-cms/text', 'Text pages', '0.1.0', ModuleState::Loaded)];
        foreach ($names as $name) {
            $statuses[] = new ModuleStatus($name, 'Module ' . $name, '1.0.0', ModuleState::Disabled);
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function post(string $path, array $form = ['_' => 'x']): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->admin->request('POST', $path, $form === [] ? ['_' => 'x'] : $form);
    }

    private function installForm(): InstallFormAction
    {
        return new InstallFormAction($this->site->inspect, new InstallFailures(), $this->admin->view, $this->admin->redirect);
    }

    private function offerAction(string $token): OfferAction
    {
        return new OfferAction(
            $this->site->getOffer,
            $this->site->install,
            $this->site->update,
            $this->admin->authenticate,
            $this->admin->currentUser,
            $this->admin->findUser,
            new InstallFailures(),
            $this->admin->view,
            $this->admin->redirect,
            $this->admin->flash,
            $this->route('/modules/offer/{token}', ['token' => $token]),
        );
    }

    private function prepareUpdate(string $slug): PrepareUpdateAction
    {
        return new PrepareUpdateAction(
            $this->site->prepareUpdate,
            new InstallFailures(),
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $this->route('/modules/{name}/update', ['name' => $slug]),
        );
    }

    private function removeAction(string $slug): RemoveAction
    {
        return new RemoveAction(
            $this->site->remove,
            new InstallFailures(),
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
            $this->route('/modules/{name}/remove', ['name' => $slug]),
        );
    }

    /**
     * @param list<ModuleStatus> $statuses
     */
    private function renderer(array $statuses): ModuleListRenderer
    {
        return new ModuleListRenderer(new ListModules(new InMemoryModuleCatalog($statuses)), $this->site->list, $this->admin->view);
    }

    /**
     * @param list<ModuleStatus> $statuses
     */
    private function listAction(array $statuses): ListAction
    {
        return new ListAction($this->renderer($statuses));
    }

    /**
     * @param list<ModuleStatus> $statuses
     */
    private function checkAction(array $statuses): CheckUpdatesAction
    {
        return new CheckUpdatesAction($this->site->checkUpdates, $this->renderer($statuses));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function route(string $pattern, array $arguments): CurrentRoute
    {
        $route = new CurrentRoute();
        /** @psalm-suppress InternalMethod The router fills this in itself; tests have to do it by hand. */
        $route->setRouteWithArguments(Route::get($pattern), $arguments);

        return $route;
    }

    private function body(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
