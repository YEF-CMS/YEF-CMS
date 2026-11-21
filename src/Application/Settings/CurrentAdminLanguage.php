<?php

declare(strict_types=1);

namespace Yef\Application\Settings;

use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Language;

/**
 * The language the admin is shown in: the one the owner chose, or the default when none was chosen
 * or the chosen one is no longer offered.
 *
 * @psalm-api Used by the admin adapter.
 */
final readonly class CurrentAdminLanguage
{
    public function __construct(
        private AdminLanguageSetting $setting,
        private AdminLanguages $languages,
    ) {}

    public function handle(): Language
    {
        $chosen = $this->setting->chosen();

        return $chosen instanceof Language && $this->languages->offers($chosen) ? $chosen : $this->languages->default;
    }
}
