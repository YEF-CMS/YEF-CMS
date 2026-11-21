<?php

declare(strict_types=1);

namespace Yef\Application\Settings;

use InvalidArgumentException;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Exception\SettingsFailed;
use Yef\Domain\Settings\Language;

/**
 * Switches the admin of the whole site to another language.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class ChangeAdminLanguage
{
    public function __construct(
        private AdminLanguageSetting $setting,
        private AdminLanguages $languages,
    ) {}

    /**
     * @throws LanguageNotOffered When the admin is not available in this language, or the code is not a language code.
     * @throws SettingsFailed
     */
    public function handle(string $code): void
    {
        try {
            $language = new Language($code);
        } catch (InvalidArgumentException) {
            throw LanguageNotOffered::withCode($code);
        }

        if (!$this->languages->offers($language)) {
            throw LanguageNotOffered::withCode($code);
        }

        $this->setting->choose($language);
    }
}
