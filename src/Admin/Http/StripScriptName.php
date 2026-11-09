<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\UrlGeneratorInterface;

use function dirname;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Lets the admin routes be written relative to the admin entry point, however the page was reached:
 * "/admin/login" with URL rewriting, or "/admin/index.php/login" without it.
 *
 * It cuts the entry point's address off the path and tells the URL generator to put the same address in front
 * of the links it makes, so every link keeps working in the mode the visitor is in.
 *
 * @psalm-api Wired through config/web/di/application.php.
 */
final readonly class StripScriptName implements MiddlewareInterface
{
    /** Request attribute holding the directory of the entry point ("/admin"), where the admin's static files are. */
    public const string DIRECTORY_ATTRIBUTE = 'yef.admin.directory';

    /**
     * @param bool $scriptNameInUrls Keep "index.php" in generated links even when the page was reached without it.
     *                               For hosts without URL rewriting, where "/admin/" works but "/admin/login" does not.
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private bool $scriptNameInUrls = false,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $server = $request->getServerParams();
        $script = isset($server['SCRIPT_NAME']) && is_string($server['SCRIPT_NAME']) ? $server['SCRIPT_NAME'] : '';

        $directory = $this->directoryOf($script);
        $path = $request->getUri()->getPath();

        $base = $this->baseOf($path, $script, $directory);
        $this->urlGenerator->setUriPrefix($this->scriptNameInUrls && $script !== '' ? $script : $base);

        $relative = substr($path, strlen($base));
        $uri = $request->getUri()->withPath($relative === '' ? '/' : $relative);

        return $handler->handle($request->withUri($uri)->withAttribute(self::DIRECTORY_ATTRIBUTE, $directory));
    }

    /**
     * @return string The part of the path that belongs to the entry point; empty when the path has none of it.
     */
    private function baseOf(string $path, string $script, string $directory): string
    {
        foreach ([$script, $directory] as $candidate) {
            if ($candidate !== '' && $this->startsWithSegment($path, $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function directoryOf(string $script): string
    {
        $directory = dirname($script);

        return $directory === '/' || $directory === '.' ? '' : $directory;
    }

    private function startsWithSegment(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
