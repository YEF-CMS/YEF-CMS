<?php

declare(strict_types=1);

namespace Yef\Platform;

use Override;
use Yef\Domain\Text\Texts;
use Yiisoft\Translator\TranslatorInterface;

/**
 * The texts of one category, translated by the translator into one language.
 * A missing text comes back as its key; the translator tries English before that.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class TranslatorTexts implements Texts
{
    public function __construct(
        private TranslatorInterface $translator,
        private string $category,
        private string $language,
    ) {}

    #[Override]
    public function t(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters, $this->category, $this->language);
    }
}
