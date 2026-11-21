<?php

declare(strict_types=1);

use Yef\Application\Settings\CurrentAdminLanguage;
use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;
use Yef\Platform\TranslatorModuleTexts;
use Yef\Platform\TranslatorTexts;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Translator\TranslatorInterface;

// What people read in the admin is in the language the owner chose for the site.
return [
    Texts::class => static fn(
        TranslatorInterface $translator,
        CurrentAdminLanguage $language,
    ): TranslatorTexts => new TranslatorTexts($translator, 'admin', $language->handle()->code),
    ModuleTexts::class => static fn(
        TranslatorInterface $translator,
        Aliases $aliases,
        CurrentAdminLanguage $language,
    ): TranslatorModuleTexts => new TranslatorModuleTexts($translator, $aliases->get('@modules'), $language->handle()->code),
];
