<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Yef\Domain\Module\ModuleName;
use Yef\Domain\Text\ModuleTexts;
use Yef\Domain\Text\Texts;
use Yef\Platform\TranslatorModuleTexts;
use Yef\Platform\TranslatorTexts;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;

/**
 * Texts as the application has them, made from the real message files, for tests.
 */
final class TestTexts
{
    /**
     * The texts of the core (the admin) in a language.
     */
    public static function core(string $language = 'en'): Texts
    {
        $translator = new Translator($language, 'en');
        $translator->addCategorySources(new CategorySource(
            'admin',
            new MessageSource(dirname(__DIR__, 2) . '/src/Admin/messages'),
            new SimpleMessageFormatter(),
        ));

        return new TranslatorTexts($translator, 'admin', $language);
    }

    /**
     * The texts of the modules found in a directory, in a language.
     */
    public static function modules(string $modulesDirectory, string $language = 'en'): ModuleTexts
    {
        return new TranslatorModuleTexts(new Translator($language, 'en'), $modulesDirectory, $language);
    }

    /**
     * The texts of the bundled text pages module.
     */
    public static function textModule(string $language = 'en'): Texts
    {
        return self::modules(dirname(__DIR__, 2) . '/modules', $language)->of(new ModuleName('yef-cms/text'));
    }
}
