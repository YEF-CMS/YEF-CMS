<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Language;

/**
 * Behavior every AdminLanguageSetting implementation has to provide.
 */
abstract class AdminLanguageSettingContract extends TestCase
{
    public function testNothingIsChosenAtFirst(): void
    {
        $this->assertNull($this->createSetting()->chosen());
    }

    public function testChosenLanguageIsRemembered(): void
    {
        $setting = $this->createSetting();

        $setting->choose(new Language('ru'));

        $this->assertEquals(new Language('ru'), $setting->chosen());
    }

    public function testChoosingAgainReplacesTheLanguage(): void
    {
        $setting = $this->createSetting();

        $setting->choose(new Language('ru'));
        $setting->choose(new Language('de'));

        $this->assertEquals(new Language('de'), $setting->chosen());
    }

    abstract protected function createSetting(): AdminLanguageSetting;
}
