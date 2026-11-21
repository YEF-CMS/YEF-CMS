<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Settings;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Settings\AdminLanguages;
use Yef\Application\Settings\ChangeAdminLanguage;
use Yef\Application\Settings\CurrentAdminLanguage;
use Yef\Application\Settings\LanguageNotOffered;
use Yef\Domain\Settings\Language;
use Yef\Tests\Support\InMemoryAdminLanguageSetting;

#[CoversClass(AdminLanguages::class)]
#[CoversClass(CurrentAdminLanguage::class)]
#[CoversClass(ChangeAdminLanguage::class)]
#[CoversClass(LanguageNotOffered::class)]
#[CoversClass(Language::class)]
final class AdminLanguageScenariosTest extends TestCase
{
    public function testTheDefaultLanguageIsShownUntilOneIsChosen(): void
    {
        $current = new CurrentAdminLanguage(new InMemoryAdminLanguageSetting(), $this->languages());

        $this->assertSame('en', $current->handle()->code);
    }

    public function testTheChosenLanguageIsShown(): void
    {
        $current = new CurrentAdminLanguage(new InMemoryAdminLanguageSetting(new Language('ru')), $this->languages());

        $this->assertSame('ru', $current->handle()->code);
    }

    public function testALanguageThatIsNoLongerOfferedFallsBackToTheDefault(): void
    {
        $current = new CurrentAdminLanguage(new InMemoryAdminLanguageSetting(new Language('pl')), $this->languages());

        $this->assertSame('en', $current->handle()->code);
    }

    public function testChangingTheLanguageIsRemembered(): void
    {
        $setting = new InMemoryAdminLanguageSetting();

        new ChangeAdminLanguage($setting, $this->languages())->handle('de');

        $this->assertEquals(new Language('de'), $setting->chosen());
    }

    public function testRefusesALanguageThatIsNotOffered(): void
    {
        $setting = new InMemoryAdminLanguageSetting();

        try {
            new ChangeAdminLanguage($setting, $this->languages())->handle('pl');
            $this->fail('A language that is not offered was accepted.');
        } catch (LanguageNotOffered) {
            $this->assertNull($setting->chosen());
        }
    }

    public function testRefusesSomethingThatIsNotALanguageCode(): void
    {
        $this->expectException(LanguageNotOffered::class);

        new ChangeAdminLanguage(new InMemoryAdminLanguageSetting(), $this->languages())->handle('../etc');
    }

    public function testTheDefaultIsTheFirstLanguageWhenTheConfiguredOneIsNotOffered(): void
    {
        $languages = new AdminLanguages(['de' => 'Deutsch', 'ru' => 'Русский'], 'en');

        $this->assertSame('de', $languages->default->code);
    }

    public function testAtLeastOneLanguageIsNeeded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminLanguages([], 'en');
    }

    public function testEveryLanguageNeedsAProperCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminLanguages(['English' => 'English'], 'English');
    }

    public function testLanguagesAreListedInTheOrderTheyWereGiven(): void
    {
        $this->assertSame(['en', 'ru', 'de'], array_keys($this->languages()->names()));
    }

    private function languages(): AdminLanguages
    {
        return new AdminLanguages(['en' => 'English', 'ru' => 'Русский', 'de' => 'Deutsch'], 'en');
    }
}
