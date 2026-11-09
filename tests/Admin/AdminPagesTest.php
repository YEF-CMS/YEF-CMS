<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Admin\Authentication\SessionAuthentication;
use Yef\Admin\Authentication\UserIdentity;
use Yef\Admin\Authentication\UserIdentityRepository;
use Yef\Admin\Dashboard;
use Yef\Admin\Http\Redirect;
use Yef\Admin\Login;
use Yef\Admin\Logout;
use Yef\Admin\Setup;
use Yef\Admin\View\AdminView;
use Yef\Tests\Support\AdminHttpScenario;
use Yef\Tests\Support\FakeCsrfToken;

#[CoversClass(Login\Action::class)]
#[CoversClass(Setup\Action::class)]
#[CoversClass(Logout\Action::class)]
#[CoversClass(Dashboard\Action::class)]
#[CoversClass(AdminView::class)]
#[CoversClass(Redirect::class)]
#[CoversClass(SessionAuthentication::class)]
#[CoversClass(UserIdentity::class)]
#[CoversClass(UserIdentityRepository::class)]
final class AdminPagesTest extends TestCase
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

    public function testSiteWithoutUsersSendsVisitorsFromSignInToSetup(): void
    {
        $response = $this->loginAction()($this->admin->request('GET', '/login'));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.setup', $response->getHeaderLine('Location'));
        $this->assertTrue($this->admin->setupToken->exists(), 'The setup code has to be there for the owner to read.');
    }

    public function testSignInFormHasTheTokenAndAssetsOfTheAdmin(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->loginAction()($this->admin->request('GET', '/login'));
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="_csrf" value="' . FakeCsrfToken::VALUE . '"', $html);
        $this->assertStringContainsString('action="/route/admin.login"', $html);
        $this->assertStringContainsString('href="/admin/assets/vendor/bootstrap/bootstrap.min.css"', $html);
        $this->assertStringContainsString('Sign in', $html);
    }

    public function testRightPasswordSignsTheUserInAndOpensTheDashboard(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->loginAction()($this->admin->request('POST', '/login', ['login' => 'Admin', 'password' => 'secret1']));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/route/admin.dashboard', $response->getHeaderLine('Location'));
        $this->assertFalse($this->admin->currentUser->isGuest());
    }

    public function testWrongPasswordShowsOneMessageAndKeepsTheLogin(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->loginAction()($this->admin->request('POST', '/login', ['login' => 'admin', 'password' => 'wrong']));
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('The login or the password is wrong.', $html);
        $this->assertStringContainsString('value="admin"', $html);
        $this->assertStringNotContainsString('wrong"', $html);
        $this->assertTrue($this->admin->currentUser->isGuest());
    }

    public function testUnknownLoginGetsTheSameMessage(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->loginAction()($this->admin->request('POST', '/login', ['login' => 'nobody', 'password' => 'secret1']));

        $this->assertStringContainsString('The login or the password is wrong.', (string) $response->getBody());
    }

    public function testWhatTheVisitorTypedIsEscapedInThePage(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->loginAction()($this->admin->request('POST', '/login', ['login' => '"><script>alert(1)</script>', 'password' => 'x']));
        $html = (string) $response->getBody();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testSignedInUserVisitingTheSignInPageGoesToTheDashboard(): void
    {
        $this->admin->signIn();

        $response = $this->loginAction()($this->admin->request('GET', '/login'));

        $this->assertSame('/route/admin.dashboard', $response->getHeaderLine('Location'));
    }

    public function testSetupFormExplainsWhereToFindTheCode(): void
    {
        $response = $this->setupAction()($this->admin->request('GET', '/setup'));
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('var/setup-token', $html);
        $this->assertStringContainsString('At least 6 characters.', $html);
    }

    public function testSetupCreatesTheFirstUserAndSignsThemIn(): void
    {
        $code = $this->admin->setupToken->current();

        $response = $this->setupAction()($this->admin->request('POST', '/setup', ['login' => 'owner', 'password' => 'secret1', 'code' => $code]));

        $this->assertSame('/route/admin.dashboard', $response->getHeaderLine('Location'));
        $this->assertFalse($this->admin->currentUser->isGuest());
        $this->assertTrue($this->admin->users->hasAny());
        $this->assertFalse($this->admin->setupToken->exists());
    }

    public function testSetupWithAWrongCodeSaysSoAndCreatesNothing(): void
    {
        $this->admin->setupToken->current();

        $response = $this->setupAction()($this->admin->request('POST', '/setup', ['login' => 'owner', 'password' => 'secret1', 'code' => 'nope']));

        $this->assertStringContainsString('The setup code is not right.', (string) $response->getBody());
        $this->assertFalse($this->admin->users->hasAny());
    }

    public function testSetupSaysWhichRuleAWeakPasswordBreaks(): void
    {
        $code = $this->admin->setupToken->current();

        $response = $this->setupAction()($this->admin->request('POST', '/setup', ['login' => 'owner', 'password' => '123', 'code' => $code]));

        $this->assertStringContainsString('The password must have at least 6 characters.', (string) $response->getBody());
        $this->assertFalse($this->admin->users->hasAny());
    }

    public function testSetupSaysWhatIsWrongWithALogin(): void
    {
        $code = $this->admin->setupToken->current();

        $response = $this->setupAction()($this->admin->request('POST', '/setup', ['login' => 'a b', 'password' => 'secret1', 'code' => $code]));

        $this->assertStringContainsString('The login must have 2 to 32 characters', (string) $response->getBody());
    }

    public function testSetupIsClosedOnceThereIsAUser(): void
    {
        $this->admin->createUser->handle('admin', 'secret1');

        $response = $this->setupAction()($this->admin->request('GET', '/setup'));

        $this->assertSame('/route/admin.login', $response->getHeaderLine('Location'));
    }

    public function testSigningOutLeavesNoOneSignedInAndOpensTheSignInPage(): void
    {
        $this->admin->signIn();

        $response = new Logout\Action($this->admin->currentUser, $this->admin->redirect)();

        $this->assertSame('/route/admin.login', $response->getHeaderLine('Location'));
        $this->assertTrue($this->admin->currentUser->isGuest());
    }

    public function testDashboardGreetsTheSignedInUserAndOffersSigningOutSafely(): void
    {
        $this->admin->signIn('editor');

        $response = new Dashboard\Action($this->admin->currentUser, $this->admin->findUser, $this->admin->view)($this->admin->request());
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Welcome, editor.', $html);
        $this->assertStringContainsString('Signed in as editor', $html);
        $this->assertStringContainsString('<form method="post" action="/route/admin.logout"', $html);
        $this->assertStringContainsString('name="_csrf" value="' . FakeCsrfToken::VALUE . '"', $html);
    }

    public function testGuestIsSentToTheSignInPageByTheAuthenticationMiddleware(): void
    {
        $authentication = new SessionAuthentication($this->admin->currentUser, new \HttpSoft\Message\ResponseFactory(), $this->admin->urls);

        $this->assertNotInstanceOf(UserIdentity::class, $authentication->authenticate($this->admin->request()));

        $challenge = $authentication->challenge(new \HttpSoft\Message\Response());
        $this->assertSame(302, $challenge->getStatusCode());
        $this->assertSame('/route/admin.login', $challenge->getHeaderLine('Location'));
    }

    public function testSignedInUserIsRecognisedByTheAuthenticationMiddleware(): void
    {
        $this->admin->signIn();
        $authentication = new SessionAuthentication($this->admin->currentUser, new \HttpSoft\Message\ResponseFactory(), $this->admin->urls);

        $this->assertInstanceOf(\Yiisoft\Auth\IdentityInterface::class, $authentication->authenticate($this->admin->request()));
    }

    public function testIdentityIsRestoredOnlyForExistingUsers(): void
    {
        $user = $this->admin->createUser->handle('admin', 'secret1');
        $identities = new UserIdentityRepository($this->admin->findUser);

        $this->assertSame($user->id->value, $identities->findIdentity($user->id->value)?->getId());
        $this->assertNull($identities->findIdentity('01JT0000000000000000000099'));
        $this->assertNull($identities->findIdentity('not an id'));
    }

    private function loginAction(): Login\Action
    {
        return new Login\Action(
            $this->admin->prepareSetup,
            $this->admin->authenticate,
            $this->admin->currentUser,
            $this->admin->view,
            $this->admin->redirect,
        );
    }

    private function setupAction(): Setup\Action
    {
        return new Setup\Action(
            $this->admin->prepareSetup,
            $this->admin->completeSetup,
            $this->admin->policy,
            $this->admin->currentUser,
            $this->admin->view,
            $this->admin->redirect,
        );
    }
}
