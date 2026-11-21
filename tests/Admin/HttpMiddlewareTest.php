<?php

declare(strict_types=1);

namespace Yef\Tests\Admin;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yef\Admin\Http\CsrfFailureHandler;
use Yef\Admin\Http\Form;
use Yef\Admin\Http\SecurityHeaders;

#[CoversClass(SecurityHeaders::class)]
#[CoversClass(CsrfFailureHandler::class)]
#[CoversClass(Form::class)]
final class HttpMiddlewareTest extends TestCase
{
    public function testEveryAdminPageGetsTheSecurityHeaders(): void
    {
        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new ResponseFactory()->createResponse(200)->withHeader('Cache-Control', 'public');
            }
        };

        $response = new SecurityHeaders()->process(new ServerRequestFactory()->createServerRequest('GET', '/'), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    public function testScriptsMayComeOnlyFromTheAdminItself(): void
    {
        $response = new SecurityHeaders()->process(new ServerRequestFactory()->createServerRequest('GET', '/'), $this->handlerReturning(new ResponseFactory()->createResponse(200)));

        $policy = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    public function testAResponseThatSetsItsOwnPolicyKeepsIt(): void
    {
        $own = new ResponseFactory()->createResponse(200)->withHeader('Content-Security-Policy', 'sandbox');

        $response = new SecurityHeaders()->process(new ServerRequestFactory()->createServerRequest('GET', '/'), $this->handlerReturning($own));

        $this->assertSame('sandbox', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testFormReadsOnlyPlainTextFieldsOfASection(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('POST', '/')->withParsedBody([
            'fields' => ['body' => 'text', 'list' => ['a'], 7 => 'numbered'],
        ]);

        $this->assertSame(['body' => 'text', '7' => 'numbered'], Form::of($request)->section('fields')->strings());
    }

    public function testFormWithoutAValidCsrfTokenIsForbidden(): void
    {
        $response = new CsrfFailureHandler(new ResponseFactory())->handle(new ServerRequestFactory()->createServerRequest('POST', '/login'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', (string) $response->getBody());
    }

    public function testFormFieldsReadAsTextAndAnythingElseAsEmpty(): void
    {
        $request = new ServerRequestFactory()
            ->createServerRequest('POST', '/login')
            ->withParsedBody(['login' => 'admin', 'password' => ['not', 'text'], 'code' => 5]);

        $form = Form::of($request);

        $this->assertSame('admin', $form->text('login'));
        $this->assertSame('', $form->text('password'));
        $this->assertSame('', $form->text('code'));
        $this->assertSame('', $form->text('missing'));
    }

    public function testRequestWithoutAFormBodyReadsAsEmpty(): void
    {
        $this->assertSame('', Form::of(new ServerRequestFactory()->createServerRequest('POST', '/login'))->text('login'));
    }

    private function handlerReturning(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
