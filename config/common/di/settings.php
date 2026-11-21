<?php

declare(strict_types=1);

use Yef\Application\Settings\AdminLanguages;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Storage\FileAdminLanguageSetting;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

return [
    AdminLanguageSetting::class => static fn(Aliases $aliases): FileAdminLanguageSetting => new FileAdminLanguageSetting(
        $aliases->get('@data') . '/settings.json',
        $aliases->get('@runtime') . '/storage/settings.lock',
    ),
    AdminLanguages::class => static fn(): AdminLanguages => new AdminLanguages(
        $params['yef']['admin']['languages'],
        $params['yiisoft/translator']['locale'],
    ),
];
