<?php

declare(strict_types=1);

namespace Yef\Tests\Platform;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Platform\GitHubModuleSource;
use Yef\Platform\Http\CurlTransport;
use Yef\Platform\Http\HttpFetcher;
use Yef\Platform\Http\HttpResponse;
use Yef\Platform\Http\HttpTransport;
use Yef\Platform\Http\StreamTransport;
use Yef\Tests\Support\GithubMock;
use Yef\Tests\Support\TemporaryDirectory;
use ZipArchive;

/**
 * The installer's way to GitHub, tried against a stand-in server, with both ways of making a request:
 * the curl extension and PHP streams.
 */
#[CoversClass(GitHubModuleSource::class)]
#[CoversClass(HttpFetcher::class)]
#[CoversClass(HttpResponse::class)]
#[CoversClass(CurlTransport::class)]
#[CoversClass(StreamTransport::class)]
final class GitHubModuleSourceTest extends TestCase
{
    private static GithubMock $github;

    private TemporaryDirectory $directory;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$github = new GithubMock();
        self::$github->start();
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$github->stop();
    }

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function transports(): iterable
    {
        yield 'curl' => ['curl'];
        yield 'streams' => ['stream'];
    }

    #[DataProvider('transports')]
    public function testFindsTheLatestRelease(string $transport): void
    {
        $release = $this->source($transport)->latestRelease($this->repository('news'));

        $this->assertSame('1.2.0', $release->version->value);
        $this->assertSame('acme/news', (string) $release->repository);
        $this->assertSame(self::$github->address() . '/repos/acme/news/zipball/v1.2.0', $release->archiveAddress);
    }

    #[DataProvider('transports')]
    public function testRepositoryWithoutReleasesIsToldFromOneThatDoesNotExist(string $transport): void
    {
        $source = $this->source($transport);

        $this->assertThrows(NoRelease::class, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('norelease')));
        $this->assertThrows(RepositoryNotFound::class, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('missing')));
    }

    #[DataProvider('transports')]
    public function testPreReleaseIsNotARelease(string $transport): void
    {
        $this->expectException(NoRelease::class);

        $this->source($transport)->latestRelease($this->repository('prerelease'));
    }

    #[DataProvider('transports')]
    public function testRateLimitIsToldFromOtherRefusals(string $transport): void
    {
        $source = $this->source($transport);

        $this->assertUnavailable(SourceUnavailable::RATE_LIMITED, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('limited')));
        $this->assertUnavailable(SourceUnavailable::RATE_LIMITED, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('toomany')));
        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('forbidden')));
        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('broken')));
    }

    #[DataProvider('transports')]
    public function testAnswerThatIsNotWhatWasExpectedIsReported(string $transport): void
    {
        $source = $this->source($transport);

        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('badjson')));
        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('notag')));
    }

    #[DataProvider('transports')]
    public function testReleaseNotNamedAfterAVersionIsReported(string $transport): void
    {
        $this->expectException(InvalidReleaseVersion::class);

        $this->source($transport)->latestRelease($this->repository('notsemver'));
    }

    #[DataProvider('transports')]
    public function testDownloadsTheArchiveFollowingTheRedirectOfTheHost(string $transport): void
    {
        $source = $this->source($transport);
        $release = $source->latestRelease($this->repository('news'));
        $file = $this->directory->path . '/release.zip';

        $hash = $source->download($release, $file);

        $this->assertFileExists($file);
        $this->assertSame(hash_file('sha256', $file), $hash);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file));
        $this->assertNotFalse($zip->locateName('acme-news-1a2b3c/module.json'));
    }

    #[DataProvider('transports')]
    public function testArchiveThatIsTooLargeIsRefusedAndNotKept(string $transport): void
    {
        $source = $this->source($transport, maxArchiveBytes: 100_000);
        $release = $source->latestRelease($this->repository('huge'));
        $file = $this->directory->path . '/release.zip';

        $this->assertUnavailable(SourceUnavailable::TOO_LARGE, static fn(): string => $source->download($release, $file));
        $this->assertFileDoesNotExist($file);
    }

    #[DataProvider('transports')]
    public function testRedirectToAnotherHostIsNotFollowed(string $transport): void
    {
        $source = $this->source($transport);
        $release = $source->latestRelease($this->repository('evilredirect'));
        $file = $this->directory->path . '/release.zip';

        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): string => $source->download($release, $file));
        $this->assertFileDoesNotExist($file);
    }

    #[DataProvider('transports')]
    public function testArchiveAddressOnAnotherHostIsNotFetched(string $transport): void
    {
        $source = $this->source($transport);
        $release = $source->latestRelease($this->repository('evilhost'));
        $file = $this->directory->path . '/release.zip';

        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): string => $source->download($release, $file));
    }

    #[DataProvider('transports')]
    public function testEndlessRedirectsAreGivenUpOn(string $transport): void
    {
        $source = $this->source($transport);
        $release = $source->latestRelease($this->repository('loop'));
        $file = $this->directory->path . '/release.zip';

        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): string => $source->download($release, $file));
    }

    #[DataProvider('transports')]
    public function testServerThatCannotBeReachedIsReported(string $transport): void
    {
        $closed = self::$github->closedPort();
        $http = new HttpFetcher($this->transport($transport), ['127.0.0.1:' . $closed], true);
        $source = new GitHubModuleSource($http, 'http://127.0.0.1:' . $closed, 1_000_000);

        $this->assertUnavailable(SourceUnavailable::UNREACHABLE, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('news')));
    }

    #[DataProvider('transports')]
    public function testHttpIsRefusedUnlessTheConfigurationAllowsIt(string $transport): void
    {
        $http = new HttpFetcher($this->transport($transport), [self::$github->host()], false);
        $source = new GitHubModuleSource($http, self::$github->address(), 1_000_000);

        $this->assertUnavailable(SourceUnavailable::BAD_ANSWER, static fn(): \Yef\Domain\Module\Install\ModuleRelease => $source->latestRelease(self::repositoryNamed('news')));
    }

    #[DataProvider('transports')]
    public function testRequestsSayWhoAsksAndCarryNothingElse(string $transport): void
    {
        $body = '';
        new HttpFetcher($this->transport($transport), [self::$github->host()], true)->get(
            self::$github->address() . '/headers',
            10_000,
            static function (string $chunk) use (&$body): void {
                $body .= $chunk;
            },
        );

        /** @var array{headers: array<string, string>} $seen */
        $seen = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        $this->assertSame('YEF-CMS-module-installer', $seen['headers']['user-agent'] ?? null);
        $this->assertArrayNotHasKey('cookie', $seen['headers']);
        $this->assertArrayNotHasKey('authorization', $seen['headers']);
    }

    public function testAddressesWithAUserNameAreNotFetched(): void
    {
        $http = new HttpFetcher($this->transport('curl'), [self::$github->host()], true);

        $this->assertUnavailable(
            SourceUnavailable::BAD_ANSWER,
            static fn(): \Yef\Platform\Http\HttpResponse => $http->get('http://user:pass@' . self::$github->host() . '/headers', 1000, static function (): void {}),
        );
    }

    private function source(string $transport, int $maxArchiveBytes = 1_000_000): GitHubModuleSource
    {
        return new GitHubModuleSource(
            new HttpFetcher($this->transport($transport), [self::$github->host()], true),
            self::$github->address(),
            $maxArchiveBytes,
        );
    }

    private function transport(string $name): HttpTransport
    {
        return $name === 'curl' ? new CurlTransport(10) : new StreamTransport(10);
    }

    private function repository(string $name): GitHubRepository
    {
        return self::repositoryNamed($name);
    }

    private static function repositoryNamed(string $name): GitHubRepository
    {
        return GitHubRepository::fromAddress('https://github.com/acme/' . $name);
    }

    /**
     * @param class-string<Throwable> $class
     * @param callable(): mixed $action
     */
    private function assertThrows(string $class, callable $action): void
    {
        try {
            $action();
        } catch (Throwable $throwable) {
            $this->assertInstanceOf($class, $throwable);

            return;
        }

        $this->fail('Expected ' . $class);
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertUnavailable(string $reason, callable $action): void
    {
        try {
            $action();
        } catch (SourceUnavailable $sourceUnavailable) {
            $this->assertSame($reason, $sourceUnavailable->reason, $sourceUnavailable->getMessage());

            return;
        }

        $this->fail('Expected the source to be unavailable (' . $reason . ').');
    }
}
