<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Publishing;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Publishing\ApplyChange;
use Yef\Application\Publishing\ApplyPageChange;
use Yef\Application\Publishing\ChangeAction;
use Yef\Application\Publishing\StepOutcome;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Tests\Support\PublishingScenario;

#[CoversClass(ApplyPageChange::class)]
#[CoversClass(ApplyChange::class)]
#[CoversClass(StepOutcome::class)]
final class ApplyPageChangeTest extends TestCase
{
    private const string FIRST = '01JC0000000000000000000001';

    private const string SECOND = '01JC0000000000000000000002';

    private PublishingScenario $site;

    #[Override]
    protected function setUp(): void
    {
        $this->site = new PublishingScenario();
    }

    public function testBuildsTheChangedPage(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));

        $outcome = $this->site->applyPageChange->handle(self::FIRST);

        $this->assertSame(StepOutcome::Built, $outcome);
        $this->assertSame(['about/index.html'], array_keys($this->site->publisher->artifacts));
    }

    public function testTakesDownAPageThatIsGone(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->delete(new PageId(self::FIRST));

        $outcome = $this->site->applyPageChange->handle(self::FIRST);

        $this->assertSame(StepOutcome::Removed, $outcome);
        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testAppliesOnlyTheGivenPage(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'contacts', 'Contacts'));

        $this->site->applyPageChange->handle(self::SECOND);

        $this->assertSame(['contacts/index.html'], array_keys($this->site->publisher->artifacts));
    }

    public function testDoesNothingForAPageThatIsInLineWithTheSite(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->publisher->artifacts = [];

        $outcome = $this->site->applyPageChange->handle(self::FIRST);

        $this->assertSame(StepOutcome::NothingToDo, $outcome);
        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testNeverPublishesADraftEvenWhenAskedTo(): void
    {
        $this->site->pages->save($this->site->draftPage(self::FIRST, 'secret', 'Secret'));

        $outcome = $this->site->applyPageChange->handle(self::FIRST);

        $this->assertSame(StepOutcome::NothingToDo, $outcome);
        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testReportsAFailureInsteadOfThrowing(): void
    {
        $this->site->published->record(new PublishedPage(
            new PageId(self::FIRST),
            new PagePath('gone'),
            new ContentVersion(hash('sha256', 'x')),
        ));
        $this->site->publisher->failToRemove = 'gone/index.html';

        $this->assertSame(StepOutcome::Failed, $this->site->applyPageChange->handle(self::FIRST));
    }

    public function testRejectsSomethingThatIsNotAPageId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->site->applyPageChange->handle('not-an-id');
    }

    public function testPlanCarriesWhatTheScreenShows(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->delete(new PageId(self::FIRST));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'contacts', 'Contacts'));

        $changes = $this->site->planChanges->handle()->changes;
        $removal = $changes[0] ?? self::fail('The removal is not planned.');
        $build = $changes[1] ?? self::fail('The build is not planned.');

        $this->assertSame(ChangeAction::Remove, $removal->action);
        $this->assertSame('about', $removal->path->value);
        $this->assertNull($removal->title);
        $this->assertSame('contacts', $build->path->value);
        $this->assertNotNull($build->title);
    }
}
