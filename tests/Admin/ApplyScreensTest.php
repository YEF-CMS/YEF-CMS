<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Yef\Admin\Apply\ScreenAction;
use Yef\Admin\Apply\StepAction;
use Yef\Admin\Dashboard\Action as DashboardAction;
use Yef\Admin\Pages\ListAction;
use Yef\Application\Page\CreatePageCommand;
use Yef\Tests\Support\AdminHttpScenario;

#[CoversClass(ScreenAction::class)]
#[CoversClass(StepAction::class)]
#[CoversClass(ListAction::class)]
#[CoversClass(DashboardAction::class)]
final class ApplyScreensTest extends TestCase
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

    public function testScreenSaysTheSiteIsUpToDateWhenNothingChanged(): void
    {
        $html = $this->body($this->screen()($this->admin->request('GET', '/apply')));

        $this->assertStringContainsString('The public site is up to date', $html);
        $this->assertStringNotContainsString('apply.js', $html);
    }

    public function testScreenListsEveryChangeWithItsAddressAndAction(): void
    {
        $about = $this->createPublished('about', 'About us');
        $this->createPublished('', 'Home');

        $html = $this->body($this->screen()($this->admin->request('GET', '/apply')));

        $this->assertStringContainsString('data-page="' . $about . '"', $html);
        $this->assertStringContainsString('About us', $html);
        $this->assertStringContainsString('<code>/about/</code>', $html);
        $this->assertStringContainsString('<code>/</code>', $html);
        $this->assertStringContainsString('Build the page', $html);
        $this->assertStringContainsString('data-step-url="/route/admin.apply.step"', $html);
        $this->assertStringContainsString('/admin/assets/apply.js', $html);
    }

    public function testScreenNamesAPageThatIsAlreadyDeletedByItsAddress(): void
    {
        $id = $this->createPublished('about', 'About us');
        $this->apply($id);
        $this->admin->deletePage->handle($id);

        $html = $this->body($this->screen()($this->admin->request('GET', '/apply')));

        $this->assertStringContainsString('Deleted page', $html);
        $this->assertStringContainsString('Take the page down', $html);
        $this->assertStringContainsString('<code>/about/</code>', $html);
    }

    public function testStepBuildsThePageAndAnswersWithTheOutcome(): void
    {
        $id = $this->createPublished('about', 'About us');

        $response = $this->step($id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"outcome":"built"}', $this->body($response));
        $this->assertArrayHasKey('about/index.html', $this->admin->publisher->artifacts);
    }

    public function testStepTwiceDoesNotBuildAgain(): void
    {
        $id = $this->createPublished('about', 'About us');
        $this->step($id);

        $this->assertSame('{"outcome":"nothing_to_do"}', $this->body($this->step($id)));
    }

    public function testStepDoesNotPublishADraft(): void
    {
        $draft = $this->admin->createPage
            ->handle(new CreatePageCommand('text', 'secret', 'Secret', ['body' => 'x']))
            ->metadata->id->value;

        $this->assertSame('{"outcome":"nothing_to_do"}', $this->body($this->step($draft)));
        $this->assertSame([], $this->admin->publisher->artifacts);
    }

    public function testStepRejectsSomethingThatIsNotAPageId(): void
    {
        $response = $this->step('nope');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"outcome":"invalid"}', $this->body($response));
    }

    public function testStepWithoutAPageIsRejected(): void
    {
        $response = new StepAction($this->admin->applyPageChange, new ResponseFactory())(
            $this->admin->request('POST', '/apply/step', ['other' => 'x']),
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testPagesListAndDashboardTellHowManyChangesWait(): void
    {
        $this->createPublished('about', 'About us');
        $this->createPublished('contacts', 'Contacts');

        $list = $this->body(new ListAction($this->admin->listPages, $this->admin->listContentTypes, $this->admin->planChanges, $this->admin->view)(
            $this->admin->request('GET', '/pages'),
        ));
        $dashboard = $this->body(new DashboardAction($this->admin->summarizePages, $this->admin->planChanges, $this->admin->view)(
            $this->admin->request(),
        ));

        $this->assertStringContainsString('Changes wait to be applied to the public site: 2.', $list);
        $this->assertStringContainsString('href="/route/admin.apply"', $list);
        $this->assertStringContainsString('Changes wait to be applied to the public site: 2.', $dashboard);
    }

    public function testNoNoticeWhenTheSiteIsUpToDate(): void
    {
        $list = $this->body(new ListAction($this->admin->listPages, $this->admin->listContentTypes, $this->admin->planChanges, $this->admin->view)(
            $this->admin->request('GET', '/pages'),
        ));

        $this->assertStringNotContainsString('Changes wait', $list);
    }

    private function screen(): ScreenAction
    {
        return new ScreenAction($this->admin->planChanges, $this->admin->view);
    }

    private function step(string $page): ResponseInterface
    {
        return new StepAction($this->admin->applyPageChange, new ResponseFactory())(
            $this->admin->request('POST', '/apply/step', ['page' => $page]),
        );
    }

    private function apply(string $page): void
    {
        $this->step($page);
    }

    private function createPublished(string $path, string $title): string
    {
        return $this->admin->createPage
            ->handle(new CreatePageCommand('text', $path, $title, ['body' => 'x'], published: true))
            ->metadata->id->value;
    }

    private function body(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }
}
