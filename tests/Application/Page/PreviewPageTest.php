<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Page;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Page\PreviewPage;
use Yef\Application\Page\PreviewPageCommand;
use Yef\Application\Publishing\RenderPage;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\ContentType\Exception\UnknownContentType;
use Yef\Tests\Support\FakePageRenderer;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\SequentialPageIdGenerator;
use Yef\Tests\Support\TestTexts;
use YefCms\TextPages\TextContentType;

#[CoversClass(PreviewPage::class)]
#[CoversClass(PreviewPageCommand::class)]
#[CoversClass(RenderPage::class)]
final class PreviewPageTest extends TestCase
{
    public function testBuildsThePageFromTheFormData(): void
    {
        $html = $this->preview()->handle(new PreviewPageCommand('text', 'About us', ['body' => '<p>Hello</p>']));

        $this->assertSame('<doc title="About us"><p>Hello</p></doc>', $html);
    }

    public function testAPageWithoutATitleIsShownAsUntitled(): void
    {
        $html = $this->preview()->handle(new PreviewPageCommand('text', '  ', ['body' => 'x']));

        $this->assertStringContainsString('title="Untitled"', $html);
    }

    public function testRefusesContentTheTypeDoesNotAccept(): void
    {
        $this->expectException(InvalidPayload::class);

        $this->preview()->handle(new PreviewPageCommand('text', 'About', ['body' => ['not', 'text']]));
    }

    public function testRefusesAnUnknownType(): void
    {
        $this->expectException(UnknownContentType::class);

        $this->preview()->handle(new PreviewPageCommand('gallery', 'About', ['body' => 'x']));
    }

    public function testRefusesSomethingThatIsNotATypeId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->preview()->handle(new PreviewPageCommand('', 'About', ['body' => 'x']));
    }

    private function preview(): PreviewPage
    {
        $types = TextContentType::registry(TestTexts::textModule());

        return new PreviewPage($types, new SequentialPageIdGenerator(), new FrozenClock(), new RenderPage($types, new FakePageRenderer()));
    }
}
