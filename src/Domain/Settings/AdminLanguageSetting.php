<?php

declare(strict_types=1);

namespace Yef\Domain\Settings;

use Yef\Domain\Settings\Exception\SettingsFailed;

/**
 * The language of the admin that the site owner has chosen. One for the whole site.
 */
interface AdminLanguageSetting
{
    /**
     * @return ?Language Null when nothing was chosen yet; unreadable settings read as "nothing chosen".
     */
    public function chosen(): ?Language;

    /**
     * @throws SettingsFailed
     */
    public function choose(Language $language): void;
}
