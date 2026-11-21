<?php

declare(strict_types=1);

namespace Yef\Tests\Platform;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\ModuleName;
use Yef\Platform\TranslatorModuleTexts;
use Yef\Platform\TranslatorTexts;
use Yef\Tests\Support\TemporaryDirectory;
use Yiisoft\Translator\Translator;

#[CoversClass(TranslatorTexts::class)]
#[CoversClass(TranslatorModuleTexts::class)]
final class TranslatedTextsTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->install('acme/gallery', 'en', ['type.title' => 'Gallery', 'photos' => '{count} photos', 'only.english' => 'Only English']);
        $this->install('acme/gallery', 'ru', ['type.title' => 'Галерея', 'photos' => 'Фото: {count}']);
        $this->install('acme/news', 'en', ['type.title' => 'News']);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testTextComesInTheLanguageOfTheSite(): void
    {
        $this->assertSame('Галерея', $this->texts('ru')->of(new ModuleName('acme/gallery'))->t('type.title'));
        $this->assertSame('Gallery', $this->texts('en')->of(new ModuleName('acme/gallery'))->t('type.title'));
    }

    public function testPlaceholdersAreFilledIn(): void
    {
        $this->assertSame('Фото: 3', $this->texts('ru')->of(new ModuleName('acme/gallery'))->t('photos', ['count' => 3]));
    }

    public function testMissingTranslationFallsBackToEnglish(): void
    {
        $this->assertSame('Only English', $this->texts('ru')->of(new ModuleName('acme/gallery'))->t('only.english'));
    }

    public function testLanguageWithoutAnyTextsFallsBackToEnglish(): void
    {
        $this->assertSame('News', $this->texts('de')->of(new ModuleName('acme/news'))->t('type.title'));
    }

    public function testUnknownKeyComesBackAsItself(): void
    {
        $this->assertSame('no.such.key', $this->texts('ru')->of(new ModuleName('acme/gallery'))->t('no.such.key'));
    }

    public function testModulesDoNotSeeEachOthersTexts(): void
    {
        $texts = $this->texts('en');

        $this->assertSame('Gallery', $texts->of(new ModuleName('acme/gallery'))->t('type.title'));
        $this->assertSame('News', $texts->of(new ModuleName('acme/news'))->t('type.title'));
        $this->assertSame('photos', $texts->of(new ModuleName('acme/news'))->t('photos'));
    }

    public function testModuleWithoutTextsGetsItsKeys(): void
    {
        $this->assertSame('type.title', $this->texts('ru')->of(new ModuleName('acme/bare'))->t('type.title'));
    }

    public function testAskingForTheSameModuleTwiceKeepsItsTextsWorking(): void
    {
        $texts = $this->texts('ru');
        $texts->of(new ModuleName('acme/gallery'));

        $this->assertSame('Галерея', $texts->of(new ModuleName('acme/gallery'))->t('type.title'));
    }

    public function testTextsOfTheCoreAreOneCategory(): void
    {
        $translator = new Translator('en', 'en');

        $this->assertSame('x.y', new TranslatorTexts($translator, 'admin', 'en')->t('x.y'));
    }

    private function texts(string $language): TranslatorModuleTexts
    {
        return new TranslatorModuleTexts(new Translator($language, 'en'), $this->directory->path, $language);
    }

    /**
     * @param array<string, string> $messages
     */
    private function install(string $module, string $language, array $messages): void
    {
        $directory = new ModuleName($module)->directoryName();
        $folder = $this->directory->path . '/' . $directory . '/messages/' . $language;
        mkdir($folder, 0o777, true);
        file_put_contents($folder . '/' . $directory . '.php', '<?php return ' . var_export($messages, true) . ';');
    }
}
