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
}
