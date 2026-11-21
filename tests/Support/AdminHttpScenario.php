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
use Yef\Application\Page\CreatePage;
use Yef\Application\Page\DeletePage;
use Yef\Application\Page\GetPage;
use Yef\Application\Page\ListContentTypes;
use Yef\Application\Page\ListPages;
use Yef\Application\Page\PreviewPage;
use Yef\Application\Page\SummarizePages;
use Yef\Application\Page\UpdatePage;
use Yef\Application\Publishing\ApplyChange;
use Yef\Application\Publishing\ApplyPageChange;
use Yef\Application\Publishing\PlanChanges;
use Yef\Application\Publishing\PublishedHtml;
use Yef\Application\Publishing\PublishPage;
use Yef\Application\Publishing\RenderPage;
use Yef\Application\Publishing\UnpublishPage;
use Yef\Application\Settings\AdminLanguages;
use Yef\Application\Settings\ChangeAdminLanguage;
use Yef\Application\Settings\CurrentAdminLanguage;
use Yef\Application\User\Authenticate;
use Yef\Application\User\CompleteSetup;
use Yef\Application\User\CreateUser;
use Yef\Application\User\FindUser;
use Yef\Application\User\PasswordPolicy;
use Yef\Application\User\PrepareSetup;
use YefCms\TextPages\TextContentType;
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

    public InMemoryFlash $flash;

    public InMemoryAdminLanguageSetting $languageSetting;

    public AdminLanguages $languages;

    public ChangeAdminLanguage $changeLanguage;

    public InMemoryPageRepository $pages;

    public CreatePage $createPage;

    public UpdatePage $updatePage;

    public DeletePage $deletePage;

    public GetPage $getPage;

    public ListPages $listPages;

    public ListContentTypes $listContentTypes;

    public SummarizePages $summarizePages;

    public InMemoryPublishedPages $published;

    public InMemoryArtifactPublisher $publisher;

    public PlanChanges $planChanges;

    public PreviewPage $previewPage;

    public ApplyPageChange $applyPageChange;

    private string $cacheDirectory;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->flash = new InMemoryFlash();
        $this->languageSetting = new InMemoryAdminLanguageSetting();
        $this->languages = new AdminLanguages(['en' => 'English', 'ru' => 'Русский', 'de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano'], 'en');
        $this->changeLanguage = new ChangeAdminLanguage($this->languageSetting, $this->languages);
        $this->pages = new InMemoryPageRepository();
        $types = TextContentType::registry(TestTexts::textModule());
        $clock = new FrozenClock();
        $this->createPage = new CreatePage($this->pages, $types, new SequentialPageIdGenerator(), $clock);
        $this->updatePage = new UpdatePage($this->pages, $types, $clock);
        $this->deletePage = new DeletePage($this->pages);
        $this->getPage = new GetPage($this->pages, $types);
        $this->listPages = new ListPages($this->pages);
        $this->listContentTypes = new ListContentTypes($types);
        $this->summarizePages = new SummarizePages($this->pages);
        $this->published = new InMemoryPublishedPages();
        $this->publisher = new InMemoryArtifactPublisher();
        $html = new PublishedHtml($this->publisher, $this->published);
        $unpublishPage = new UnpublishPage($this->published, $html);
        $renderPage = new RenderPage($types, new FakePageRenderer());
        $publishPage = new PublishPage($this->pages, $this->pages, $this->published, $html, $renderPage, $this->publisher);
        $this->previewPage = new PreviewPage($types, new SequentialPageIdGenerator(), $clock, $renderPage);
        $this->planChanges = new PlanChanges($this->pages, $this->pages, $this->published, $types);
        $this->applyPageChange = new ApplyPageChange($this->planChanges, new ApplyChange($publishPage, $unpublishPage));
        $this->users = new InMemoryUserRepository();
        $this->setupToken = new InMemorySetupToken();
        $hasher = new FakePasswordHasher();
        $this->policy = new PasswordPolicy(6);
        $this->createUser = new CreateUser($this->users, $hasher, new SequentialUserIdGenerator(), new FrozenClock(), $this->policy);
        $this->findUser = new FindUser($this->users);
        $this->prepareSetup = new PrepareSetup($this->users, $this->setupToken);
        $this->authenticate = new Authenticate($this->users, $hasher);
        $this->completeSetup = new CompleteSetup($this->users, $this->setupToken, $this->createUser, new RecordingDefaultModules());
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
            $this->flash,
            $this->currentUser,
            $this->findUser,
            $responses,
            new CurrentAdminLanguage($this->languageSetting, $this->languages),
            $this->languages,
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
