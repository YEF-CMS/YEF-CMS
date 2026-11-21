<?php

declare(strict_types=1);

namespace Yef\Platform;

use JsonException;
use Override;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\GitHubRepository;
use Yef\Domain\Module\Install\ModuleRelease;
use Yef\Domain\Module\Install\ModuleSource;
use Yef\Domain\Module\Install\ReleaseVersion;
use Yef\Platform\Http\HttpFetcher;
use Yef\Platform\Http\HttpResponse;

use function fclose;
use function fopen;
use function fwrite;
use function hash_final;
use function hash_init;
use function hash_update;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function rawurlencode;
use function sprintf;
use function unlink;

/**
 * Reads the releases of GitHub repositories through the GitHub API and downloads their archives.
 * Only the latest release counts; branches and tags that are not releases do not exist for the installer.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class GitHubModuleSource implements ModuleSource
{
    private const int MAX_JSON_BYTES = 1048576;

    /**
     * @param string $apiBase "https://api.github.com", or the address of a test server.
     */
    public function __construct(
        private HttpFetcher $http,
        private string $apiBase,
        private int $maxArchiveBytes,
    ) {}

    #[Override]
    public function latestRelease(GitHubRepository $repository): ModuleRelease
    {
        $path = sprintf('%s/repos/%s/%s', $this->apiBase, rawurlencode($repository->owner), rawurlencode($repository->name));
        [$response, $body] = $this->json($path . '/releases/latest');

        if ($response->status === 404) {
            // The API gives the same answer for "no such repository" and "no releases"; the repository itself tells which.
            [$repositoryResponse] = $this->json($path);
            if ($repositoryResponse->status === 200) {
                throw NoRelease::in($repository);
            }

            throw RepositoryNotFound::named($repository);
        }

        $this->assertAnswered($response);
        $data = $this->decode($body);
        $tag = $data['tag_name'] ?? null;
        $archive = $data['zipball_url'] ?? null;
        if (!is_string($tag) || !is_string($archive)) {
            throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'the release is not described as expected');
        }

        if (($data['draft'] ?? false) === true || ($data['prerelease'] ?? false) === true) {
            throw NoRelease::in($repository);
        }

        return new ModuleRelease($repository, new ReleaseVersion($tag), $archive);
    }

    #[Override]
    public function download(ModuleRelease $release, string $file): string
    {
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw SourceUnavailable::because(SourceUnavailable::UNREACHABLE, 'the archive cannot be saved');
        }

        $hash = hash_init('sha256');

        try {
            $response = $this->http->get(
                $release->archiveAddress,
                $this->maxArchiveBytes,
                static function (string $chunk) use ($handle, $hash): void {
                    fwrite($handle, $chunk);
                    hash_update($hash, $chunk);
                },
            );
            $this->assertAnswered($response);
        } catch (SourceUnavailable $sourceUnavailable) {
            fclose($handle);
            if (is_file($file)) {
                unlink($file);
            }

            throw $sourceUnavailable;
        }

        fclose($handle);

        return hash_final($hash);
    }

    /**
     * @return array{0: HttpResponse, 1: string}
     */
    private function json(string $url): array
    {
        $body = '';
        $response = $this->http->get($url, self::MAX_JSON_BYTES, static function (string $chunk) use (&$body): void {
            $body .= $chunk;
        });

        return [$response, $body];
    }

    /**
     * @throws SourceUnavailable
     */
    private function assertAnswered(HttpResponse $response): void
    {
        if ($response->status === 429 || ($response->status === 403 && $response->header('x-ratelimit-remaining') === '0')) {
            throw SourceUnavailable::because(SourceUnavailable::RATE_LIMITED, 'GitHub limits the number of requests');
        }

        if ($response->status !== 200) {
            throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'the answer was ' . $response->status);
        }
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws SourceUnavailable
     */
    private function decode(string $body): array
    {
        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $data = json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'the answer is not JSON', $jsonException);
        }

        return is_array($data) ? $data : throw SourceUnavailable::because(SourceUnavailable::BAD_ANSWER, 'the answer is not an object');
    }
}
