<?php

declare(strict_types=1);

use Yef\Domain\Publishing\PublicArtifactPublisher;
use Yef\Domain\Rendering\PageRenderer;
use Yef\Publishing\FilePublicArtifactPublisher;
use Yef\Rendering\TwigPageRenderer;
use Yiisoft\Aliases\Aliases;

return [
    PublicArtifactPublisher::class => static fn(Aliases $aliases): FilePublicArtifactPublisher => new FilePublicArtifactPublisher(
        $aliases->get('@public'),
    ),
    PageRenderer::class => static fn(Aliases $aliases): TwigPageRenderer => new TwigPageRenderer(
        $aliases->get('@templates'),
        $aliases->get('@runtime') . '/twig',
    ),
];
