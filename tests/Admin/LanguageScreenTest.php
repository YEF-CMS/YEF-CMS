<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yef\Admin\Http\Redirect;
use Yef\Admin\Language\Action;
use Yef\Admin\NotFound\NotFoundHandler;
use Yef\Application\Settings\ChangeAdminLanguage;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Exception\SettingsFailed;
use Yef\Domain\Settings\Language;
use Yef\Tests\Support\AdminHttpScenario;

#[CoversClass(Action::class)]
#[CoversClass(Redirect::class)]
final class LanguageScreenTest extends TestCase
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

    public function testSwitchingTheLanguageRemembersItAndGoesBackToThePage(): void
    {
        $response = $this->switchTo('ru', 'http://localhost/admin/pages?x=1');

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/admin/pages?x=1', $response->getHeaderLine('Location'));
        $this->assertEquals(new Language('ru'), $this->admin->languageSetting->chosen());
    }

    public function testWithoutAReferrerItGoesToTheDashboard(): void
    {
        $this->assertSame('/route/admin.dashboard', $this->switchTo('ru')->getHeaderLine('Location'));
    }

    public function testAReferrerOnAnotherSiteIsIgnored(): void
    {
        $response = $this->switchTo('ru', 'https://evil.example/phish?x=1');

        $this->assertSame('/route/admin.dashboard', $response->getHeaderLine('Location'));
    }

    public function testAReferrerThatLooksLikeADifferentHostByItsPathIsIgnored(): void
    {
        $this->assertSame('/route/admin.dashboard', $this->switchTo('ru', 'http://localhost:9999/admin')->getHeaderLine('Location'));
    }

    public function testAProtocolRelativeReferrerPathCannotLeaveTheSite(): void
    {
        $response = $this->switchTo('ru', 'http://localhost//evil.example/x');

        $this->assertSame('/route/admin.dashboard', $response->getHeaderLine('Location'));
    }

    public function testALanguageThatIsNotOfferedIsNotFound(): void
    {
        $response = $this->switchTo('pl');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertNull($this->admin->languageSetting->chosen());
    }

    public function testMissingLanguageIsNotFound(): void
    {
        $this->assertSame(404, $this->action()($this->admin->request('POST', '/language'))->getStatusCode());
    }

    public function testFailureToSaveIsReportedAndTheVisitorStillGoesBack(): void
    {
        $failing = new class implements AdminLanguageSetting {
            #[Override]
            public function chosen(): ?Language
            {
                return null;
            }

            #[Override]
            public function choose(Language $language): void
            {
                throw SettingsFailed::whileSaving(new RuntimeException('disk'));
            }
        };
        $action = new Action(
            new ChangeAdminLanguage($failing, $this->admin->languages),
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
        );

        $response = $action($this->admin->request('POST', '/language', ['language' => 'ru']));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('language.error.save', $this->admin->flash->get('danger'));
    }

    public function testTheAdminSpeaksTheChosenLanguage(): void
    {
        $this->admin->signIn();
        $this->admin->changeLanguage->handle('ru');

        $html = (string) $this->admin->view->render($this->admin->request(), 'dashboard', [
            'summary' => $this->admin->summarizePages->handle(),
            'pending' => 0,
        ])->getBody();

        $this->assertStringContainsString('<html lang="ru">', $html);
        $this->assertStringContainsString('Панель', $html);
        $this->assertStringContainsString('<option value="ru" selected>Русский</option>', $html);
        $this->assertStringNotContainsString('Welcome', $html);
    }

    public function testTheAdminIsInEnglishByDefaultAndOffersEveryLanguage(): void
    {
        $this->admin->signIn();

        $html = (string) $this->admin->view->render($this->admin->request(), 'dashboard', [
            'summary' => $this->admin->summarizePages->handle(),
            'pending' => 0,
        ])->getBody();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('Welcome', $html);
        foreach (['English', 'Русский', 'Deutsch', 'Français', 'Italiano'] as $name) {
            $this->assertStringContainsString('>' . $name . '</option>', $html);
        }
    }

    public function testTheSwitchIsAFormThatWorksWithoutScript(): void
    {
        $this->admin->signIn();

        $html = (string) $this->admin->view->render($this->admin->request(), 'dashboard', [
            'summary' => $this->admin->summarizePages->handle(),
            'pending' => 0,
        ])->getBody();

        $this->assertStringContainsString('action="/route/admin.language"', $html);
        $this->assertStringContainsString('<noscript><button type="submit"', $html);
        $this->assertStringContainsString('/admin/assets/language.js', $html);
    }

    private function switchTo(string $code, string $referrer = ''): ResponseInterface
    {
        $request = $this->admin->request('POST', '/language', ['language' => $code])
            ->withUri(new \HttpSoft\Message\Uri('http://localhost/admin/language'));

        return $this->action()($referrer === '' ? $request : $request->withHeader('Referer', $referrer));
    }

    private function action(): Action
    {
        return new Action(
            $this->admin->changeLanguage,
            $this->admin->redirect,
            $this->admin->flash,
            new NotFoundHandler(new ResponseFactory()),
        );
    }
}
