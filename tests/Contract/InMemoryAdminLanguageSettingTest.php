<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Tests\Support\InMemoryAdminLanguageSetting;

#[CoversClass(InMemoryAdminLanguageSetting::class)]
final class InMemoryAdminLanguageSettingTest extends AdminLanguageSettingContract
{
    #[Override]
    protected function createSetting(): AdminLanguageSetting
    {
        return new InMemoryAdminLanguageSetting();
    }
}
