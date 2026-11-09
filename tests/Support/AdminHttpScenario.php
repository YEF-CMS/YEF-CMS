<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yef\Admin\Authentication\UserIdentityRepository;
use Yef\Admin\Http\Redirect;
use Yef\Admin\View\AdminView;
use Yef\Application\User\Authenticate;
use Yef\Application\User\CompleteSetup;
use Yef\Application\User\CreateUser;
use Yef\Application\User\FindUser;
use Yef\Application\User\PasswordPolicy;
use Yef\Application\User\PrepareSetup;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\User\CurrentUser;

/**
 * The admin's HTTP pieces wired to in-memory ports and the real templates and texts, as the tests need them.
 */
final readonly class AdminHttpScenario
{
    public InMemoryUserRepository $users;

    public InMemorySetupToken $setupToken;

    public CreateUser $createUser;

    public CurrentUser $currentUser;

    public AdminView $view;

    public Redirect $redirect;

    public RecordingUrlGenerator $urls;

    public FindUser $findUser;

    public PrepareSetup $prepareSetup;

    public Authenticate $authenticate;

    public CompleteSetup $completeSetup;

    public PasswordPolicy $policy;

    private string $cacheDirectory;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->users = new InMemoryUserRepository();
        $this->setupToken = new InMemorySetupToken();
        $hasher = new FakePasswordHasher();
        $this->policy = new PasswordPolicy(6);
        $this->createUser = new CreateUser($this->users, $hasher, new SequentialUserIdGenerator(), new FrozenClock(), $this->policy);
        $this->findUser = new FindUser($this->users);
        $this->prepareSetup = new PrepareSetup($this->users, $this->setupToken);
        $this->authenticate = new Authenticate($this->users, $hasher);
        $this->completeSetup = new CompleteSetup($this->users, $this->setupToken, $this->createUser);
        $this->urls = new RecordingUrlGenerator();
        $responses = new ResponseFactory();
        $this->redirect = new Redirect($responses, $this->urls);
        $this->currentUser = new CurrentUser(
            new UserIdentityRepository($this->findUser),
            new class implements EventDispatcherInterface {
                #[Override]
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );

        $translator = new Translator('en', 'en');
        $source = new MessageSource($root . '/src/Admin/messages');
        $translator->addCategorySources(new CategorySource('admin', $source, new SimpleMessageFormatter()));

        $this->cacheDirectory = sys_get_temp_dir() . '/yef-admin-twig-' . bin2hex(random_bytes(4));
        $this->view = new AdminView(
            $root . '/src/Admin/templates',
            $this->cacheDirectory,
            $translator,
            $this->urls,
            new FakeCsrfToken(),
            $responses,
        );
    }

    public function request(string $method = 'GET', string $path = '/', array $form = []): ServerRequestInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, $path, ['SCRIPT_NAME' => '/admin/index.php']);

        return $form === []
            ? $request->withAttribute('yef.admin.directory', '/admin')
            : $request->withParsedBody($form)->withAttribute('yef.admin.directory', '/admin');
    }

    public function signIn(string $login = 'admin', string $password = 'secret1'): void
    {
        $user = $this->createUser->handle($login, $password);
        $this->currentUser->login(new \Yef\Admin\Authentication\UserIdentity($user->id->value));
    }

    public function cleanUp(): void
    {
        new \Yef\Storage\Files()->remove($this->cacheDirectory);
    }
}
