<?php

declare(strict_types=1);

namespace Yef\Tests\Rendering;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\Asset;
use Yef\Domain\ContentType\AssetKind;
use Yef\Domain\ContentType\RenderedContent;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Rendering\Exception\RenderingFailed;
use Yef\Rendering\TwigPageRenderer;
use Yef\Tests\Support\TemporaryDirectory;
use Yef\Tests\Support\TextPages;

#[CoversClass(TwigPageRenderer::class)]
final class TwigPageRendererTest extends TestCase
{
    private TemporaryDirectory $directory;

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

    public function testSiteLayoutPlacesContentTitleAndDescription(): void
    {
        $html = $this->siteRenderer()->render(
            $this->metadata('about', 'About <us>'),
            new RenderedContent('<p>Hello</p>'),
        );

        $this->assertStringContainsString('<title>About &lt;us&gt;</title>', $html);
        $this->assertStringContainsString("<body>\n<p>Hello</p>", $html);
        $this->assertStringNotContainsString('name="description"', $html);
    }

    public function testAssetsGoToTheirPlacesWithoutDuplicates(): void
    {
        $html = $this->siteRenderer()->render(
            $this->metadata('gallery', 'Gallery'),
            new RenderedContent('', [
                new Asset(AssetKind::Style, '/assets/gallery.css'),
                new Asset(AssetKind::Script, '/assets/gallery.js'),
                new Asset(AssetKind::Script, '/assets/gallery.js'),
            ]),
        );

        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/gallery.css">', $html);
        $this->assertSame(1, substr_count($html, '<script src="/assets/gallery.js" defer></script>'));
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'gallery.css'));
        $this->assertGreaterThan(strpos($html, '</head>'), strpos($html, 'gallery.js'));
    }

    public function testUnknownRegionIsEmpty(): void
    {
        $this->writeLayout("[{{ region('sidebar') }}]");

        $html = $this->renderer()->render($this->metadata('a', 'A'), new RenderedContent('content'));

        $this->assertSame('[]', $html);
    }

    public function testBrokenLayoutIsReportedAsRenderingFailure(): void
    {
        $this->writeLayout('{{ missing_variable }}');

        $this->expectException(RenderingFailed::class);

        $this->renderer()->render($this->metadata('a', 'A'), new RenderedContent(''));
    }

    private function siteRenderer(): TwigPageRenderer
    {
        return new TwigPageRenderer(__DIR__ . '/../../templates', $this->directory->path . '/cache');
    }

    private function renderer(): TwigPageRenderer
    {
        return new TwigPageRenderer($this->directory->path . '/templates', $this->directory->path . '/cache');
    }

    private function writeLayout(string $source): void
    {
        mkdir($this->directory->path . '/templates', 0o775, true);
        file_put_contents($this->directory->path . '/templates/layout.twig', $source);
    }

    private function metadata(string $path, string $title): PageMetadata
    {
        $page = new TextPages()->page('01JC0000000000000000000001', $path, '');

        return new PageMetadata(
            $page->metadata->id,
            $page->metadata->type,
            new \Yef\Domain\Page\PageDetails($page->metadata->details->path, $title),
            $page->metadata->status,
            new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
            new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
        );
    }
}
