<?php

declare(strict_types=1);

namespace Yef\Rendering;

use Override;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\TwigFunction;
use Yef\Domain\ContentType\Asset;
use Yef\Domain\ContentType\AssetKind;
use Yef\Domain\ContentType\RenderedContent;
use Yef\Domain\Page\PageMetadata;
use Yef\Domain\Rendering\Exception\RenderingFailed;
use Yef\Domain\Rendering\PageRenderer;

use function array_unique;
use function htmlspecialchars;
use function implode;
use function sprintf;

/**
 * Renders pages with the site layout `layout.twig`.
 *
 * The layout sees `page` (title, metaTitle, metaDescription, path) and calls
 * `region(name)`, `assets_head()` and `assets_footer()`.
 */
final readonly class TwigPageRenderer implements PageRenderer
{
    private const string LAYOUT = 'layout.twig';

    private const string MAIN_REGION = 'main';

    private Environment $twig;

    public function __construct(string $templatesDirectory, string $cacheDirectory)
    {
        $this->twig = new Environment(new FilesystemLoader($templatesDirectory), [
            'cache' => $cacheDirectory,
            'auto_reload' => true,
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $this->twig->addFunction(new TwigFunction(
            'region',
            static fn(array $context, string $name): Markup => new Markup(
                $name === self::MAIN_REGION ? self::contentOf($context) : '',
                'UTF-8',
            ),
            ['needs_context' => true],
        ));
        $this->twig->addFunction(new TwigFunction(
            'assets_head',
            static fn(array $context): Markup => self::assetTags($context, AssetKind::Style),
            ['needs_context' => true],
        ));
        $this->twig->addFunction(new TwigFunction(
            'assets_footer',
            static fn(array $context): Markup => self::assetTags($context, AssetKind::Script),
            ['needs_context' => true],
        ));
    }

    #[Override]
    public function render(PageMetadata $page, RenderedContent $content): string
    {
        try {
            return $this->twig->render(self::LAYOUT, [
                'page' => [
                    'title' => $page->details->title,
                    'metaTitle' => $page->details->metaTitle,
                    'metaDescription' => $page->details->metaDescription,
                    'path' => $page->details->path->value,
                ],
                'content' => $content->html,
                'assets' => $content->assets,
            ]);
        } catch (TwigError $twigError) {
            throw RenderingFailed::forPage($page->id, $twigError);
        }
    }

    /**
     * @param array<array-key, mixed> $context The variables passed to the layout in render().
     */
    private static function contentOf(array $context): string
    {
        /** @var string $content */
        $content = $context['content'] ?? '';

        return $content;
    }

    /**
     * @param array<array-key, mixed> $context The variables passed to the layout in render().
     */
    private static function assetTags(array $context, AssetKind $kind): Markup
    {
        /** @var list<Asset> $assets */
        $assets = $context['assets'] ?? [];
        $tags = [];

        foreach ($assets as $asset) {
            if ($asset->kind === $kind) {
                $tags[] = self::tag($asset);
            }
        }

        return new Markup(implode("\n", array_unique($tags)), 'UTF-8');
    }

    private static function tag(Asset $asset): string
    {
        $path = htmlspecialchars($asset->path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return match ($asset->kind) {
            AssetKind::Style => sprintf('<link rel="stylesheet" href="%s">', $path),
            AssetKind::Script => sprintf('<script src="%s" defer></script>', $path),
        };
    }
}
