<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Language;
use Yef\Storage\FileAdminLanguageSetting;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileAdminLanguageSetting::class)]
final class FileAdminLanguageSettingTest extends AdminLanguageSettingContract
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testChoiceSurvivesANewInstance(): void
    {
        $this->createSetting()->choose(new Language('fr'));

        $this->assertEquals(new Language('fr'), $this->createSetting()->chosen());
    }

    public function testFileIsReadableJson(): void
    {
        $this->createSetting()->choose(new Language('it'));

        $this->assertSame(['language' => 'it'], json_decode((string) file_get_contents($this->file()), true));
    }

    public function testOtherSettingsInTheFileAreKept(): void
    {
        file_put_contents($this->file(), '{"theme": "dark", "language": "en"}');

        $this->createSetting()->choose(new Language('ru'));

        $this->assertSame(['theme' => 'dark', 'language' => 'ru'], json_decode((string) file_get_contents($this->file()), true));
    }

    public function testDamagedFileReadsAsNothingChosenAndCanBeWrittenAgain(): void
    {
        file_put_contents($this->file(), '{ nope');
        $setting = $this->createSetting();

        $this->assertNull($setting->chosen());

        $setting->choose(new Language('de'));
        $this->assertEquals(new Language('de'), $setting->chosen());
    }

    public function testSomethingThatIsNotALanguageCodeReadsAsNothingChosen(): void
    {
        file_put_contents($this->file(), '{"language": "Klingon"}');

        $this->assertNull($this->createSetting()->chosen());
    }

    public function testLanguageOfTheWrongTypeReadsAsNothingChosen(): void
    {
        file_put_contents($this->file(), '{"language": 5}');

        $this->assertNull($this->createSetting()->chosen());
    }

    #[Override]
    protected function createSetting(): AdminLanguageSetting
    {
        return new FileAdminLanguageSetting($this->file(), $this->directory->path . '/var/settings.lock');
    }

    private function file(): string
    {
        return $this->directory->path . '/settings.json';
    }
}
