<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yef\Admin\Http\StripScriptName;
use Yef\Tests\Support\RecordingUrlGenerator;

#[CoversClass(StripScriptName::class)]
#[CoversClass(RecordingUrlGenerator::class)]
final class StripScriptNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}> Path asked for, path the router sees, link prefix.
     */
    public static function addresses(): iterable
    {
        yield 'rewritten login' => ['/admin/login', '/login', '/admin'];
        yield 'login through the script' => ['/admin/index.php/login', '/login', '/admin/index.php'];
        yield 'home through the directory' => ['/admin/', '/', '/admin'];
        yield 'home without the slash' => ['/admin', '/', '/admin'];
        yield 'home through the script' => ['/admin/index.php', '/', '/admin/index.php'];
        yield 'home through the script with a slash' => ['/admin/index.php/', '/', '/admin/index.php'];
        yield 'nested path' => ['/admin/pages/12/edit', '/pages/12/edit', '/admin'];
        yield 'a directory that only starts like the admin' => ['/administrator/login', '/administrator/login', ''];
        yield 'an address outside the admin' => ['/blog/post', '/blog/post', ''];
    }

    #[DataProvider('addresses')]
    public function testRoutesSeeAddressesRelativeToTheEntryPoint(string $asked, string $routed, string $prefix): void
    {
        $generator = new RecordingUrlGenerator();
        $seen = $this->handle(new StripScriptName($generator), $asked);

        $this->assertSame($routed, $seen->getUri()->getPath());
        $this->assertSame($prefix, $generator->getUriPrefix());
    }

    public function testQueryStringIsKept(): void
    {
        $seen = $this->handle(new StripScriptName(new RecordingUrlGenerator()), '/admin/pages?page=2');

        $this->assertSame('/pages', $seen->getUri()->getPath());
        $this->assertSame('page=2', $seen->getUri()->getQuery());
    }

    public function testStaticFilesAreFoundInTheDirectoryOfTheEntryPoint(): void
    {
        $seen = $this->handle(new StripScriptName(new RecordingUrlGenerator()), '/admin/index.php/login');

        $this->assertSame('/admin', $seen->getAttribute(StripScriptName::DIRECTORY_ATTRIBUTE));
    }

    public function testLinksKeepTheScriptNameWhenAskedTo(): void
    {
        $generator = new RecordingUrlGenerator();

        $seen = $this->handle(new StripScriptName($generator, scriptNameInUrls: true), '/admin/');

        $this->assertSame('/', $seen->getUri()->getPath());
        $this->assertSame('/admin/index.php', $generator->getUriPrefix());
    }

    public function testNothingIsCutWhenTheServerTellsNoScriptName(): void
    {
        $generator = new RecordingUrlGenerator();
        $request = new ServerRequestFactory()->createServerRequest('GET', '/admin/login');

        $seen = $this->pass(new StripScriptName($generator), $request);

        $this->assertSame('/admin/login', $seen->getUri()->getPath());
        $this->assertSame('', $generator->getUriPrefix());
    }

    private function pass(StripScriptName $middleware, ServerRequestInterface $request): ServerRequestInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $seen = null;

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = $request;

                return new ResponseFactory()->createResponse();
            }
        };

        $middleware->process($request, $handler);

        return $handler->seen ?? throw new LogicException('The request did not reach the handler.');
    }

    private function handle(StripScriptName $middleware, string $address): ServerRequestInterface
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', $address, ['SCRIPT_NAME' => '/admin/index.php']);

        return $this->pass($middleware, $request);
    }
}
