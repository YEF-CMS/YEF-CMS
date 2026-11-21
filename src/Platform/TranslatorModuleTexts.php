<?php

declare(strict_types=1);

namespace Yef\Platform;

use Override;
use Yef\Domain\Module\ModuleName;
use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Gives every installed module its own texts: `messages/<language>/<directory of the module>.php`
 * in the directory of the module, which also names the category of the module's texts.
 * A module without such files simply has no texts, and `t()` returns the keys.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final class TranslatorModuleTexts implements ModuleTexts
{
    /** @var array<string, true> */
    private array $known = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly string $modulesDirectory,
        private readonly string $language,
    ) {}

    #[Override]
    public function of(ModuleName $module): Texts
    {
        $category = $module->directoryName();
        if (!isset($this->known[$category])) {
            $this->translator->addCategorySources(new CategorySource(
                $category,
                new MessageSource($this->modulesDirectory . '/' . $category . '/messages'),
                new SimpleMessageFormatter(),
            ));
            $this->known[$category] = true;
        }

        return new TranslatorTexts($this->translator, $category, $this->language);
    }
}
