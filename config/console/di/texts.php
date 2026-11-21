<?php

declare(strict_types=1);

use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;
use Yef\Platform\TranslatorModuleTexts;
use Yef\Platform\TranslatorTexts;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Translator\TranslatorInterface;

// The console speaks English whatever language the owner chose for the admin.
return [
    Texts::class => static fn(TranslatorInterface $translator): TranslatorTexts => new TranslatorTexts($translator, 'admin', 'en'),
    ModuleTexts::class => static fn(
        TranslatorInterface $translator,
        Aliases $aliases,
    ): TranslatorModuleTexts => new TranslatorModuleTexts($translator, $aliases->get('@modules'), 'en'),
];
