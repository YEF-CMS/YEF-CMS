<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\Module\Install;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\GitHubRepository;

#[CoversClass(GitHubRepository::class)]
#[CoversClass(InvalidRepositoryAddress::class)]
#[CoversClass(NoRelease::class)]
#[CoversClass(RepositoryNotFound::class)]
#[CoversClass(SourceUnavailable::class)]
final class GitHubRepositoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function goodAddresses(): iterable
    {
        yield 'plain' => ['https://github.com/YEF-CMS/module-gallery', 'YEF-CMS/module-gallery'];
        yield 'with .git' => ['https://github.com/acme/news.git', 'acme/news'];
        yield 'with a slash' => ['https://github.com/acme/news/', 'acme/news'];
        yield 'with spaces around' => ["  https://github.com/acme/news\n", 'acme/news'];
        yield 'dots and underscores in the name' => ['https://github.com/acme/my_module.v2', 'acme/my_module.v2'];
        yield 'digits' => ['https://github.com/a1/b2', 'a1/b2'];
    }

    #[DataProvider('goodAddresses')]
    public function testAcceptsTheAddressOfARepository(string $address, string $expected): void
    {
        $repository = GitHubRepository::fromAddress($address);

        $this->assertSame($expected, (string) $repository);
        $this->assertSame('https://github.com/' . $expected, $repository->address());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badAddresses(): iterable
    {
        yield 'plain http' => ['http://github.com/acme/news'];
        yield 'another host' => ['https://gitlab.com/acme/news'];
        yield 'a host that only starts like it' => ['https://github.com.evil.example/acme/news'];
        yield 'a host that ends like it' => ['https://evilgithub.com/acme/news'];
        yield 'with a user name' => ['https://user@github.com/acme/news'];
        yield 'with a user and password' => ['https://user:pass@github.com/acme/news'];
        yield 'with a port' => ['https://github.com:8443/acme/news'];
        yield 'with a query' => ['https://github.com/acme/news?x=1'];
        yield 'with a fragment' => ['https://github.com/acme/news#readme'];
        yield 'with a branch path' => ['https://github.com/acme/news/tree/main'];
        yield 'only an owner' => ['https://github.com/acme'];
        yield 'no scheme' => ['github.com/acme/news'];
        yield 'empty' => [''];
        yield 'a path that leaves' => ['https://github.com/acme/..'];
        yield 'a dot as the name' => ['https://github.com/acme/.'];
        yield 'two slashes' => ['https://github.com//news'];
        yield 'a space in the name' => ['https://github.com/acme/new s'];
        yield 'an owner starting with a hyphen' => ['https://github.com/-acme/news'];
        yield 'a new line inside' => ["https://github.com/acme/news\nhttps://evil.example"];
        yield 'an encoded slash' => ['https://github.com/acme%2Fnews'];
    }

    #[DataProvider('badAddresses')]
    public function testRefusesEverythingElse(string $address): void
    {
        $this->expectException(InvalidRepositoryAddress::class);

        GitHubRepository::fromAddress($address);
    }

    public function testRepositoriesAreComparedByOwnerAndName(): void
    {
        $this->assertTrue(GitHubRepository::fromAddress('https://github.com/acme/news')->equals(GitHubRepository::fromAddress('https://github.com/acme/news.git')));
        $this->assertFalse(GitHubRepository::fromAddress('https://github.com/acme/news')->equals(GitHubRepository::fromAddress('https://github.com/acme/blog')));
    }

    public function testFailuresNameTheirCause(): void
    {
        $repository = GitHubRepository::fromAddress('https://github.com/acme/news');

        $this->assertStringContainsString('acme/news', RepositoryNotFound::named($repository)->getMessage());
        $this->assertStringContainsString('no release', NoRelease::in($repository)->getMessage());
        $this->assertSame(SourceUnavailable::RATE_LIMITED, SourceUnavailable::because(SourceUnavailable::RATE_LIMITED)->reason);
    }
}
