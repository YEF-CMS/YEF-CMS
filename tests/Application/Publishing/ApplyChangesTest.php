<?php

declare(strict_types=1);

namespace Yef\Tests\Application\Publishing;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Application\Publishing\ApplyChanges;
use Yef\Application\Publishing\ApplyReport;
use Yef\Application\Publishing\Change;
use Yef\Application\Publishing\ChangeAction;
use Yef\Application\Publishing\ChangePlan;
use Yef\Application\Publishing\PlanChanges;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\ContentVersion;
use Yef\Domain\Page\Page;
use Yef\Domain\Page\PageId;
use Yef\Domain\Page\PagePath;
use Yef\Domain\Publishing\PublishedPage;
use Yef\Tests\Support\PublishingScenario;

#[CoversClass(PlanChanges::class)]
#[CoversClass(ApplyChanges::class)]
#[CoversClass(ApplyReport::class)]
#[CoversClass(ChangePlan::class)]
#[CoversClass(Change::class)]
final class ApplyChangesTest extends TestCase
{
    private const string FIRST = '01JC0000000000000000000001';

    private const string SECOND = '01JC0000000000000000000002';

    private PublishingScenario $site;

    #[Override]
    protected function setUp(): void
    {
        $this->site = new PublishingScenario();
    }

    public function testNothingIsPlannedWhenThereAreNoPages(): void
    {
        $this->assertTrue($this->site->planChanges->handle()->isEmpty());
    }

    public function testNewPublishedPageIsPlannedForBuilding(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));

        $plan = $this->site->planChanges->handle();

        $this->assertSame([[ChangeAction::Build, self::FIRST]], array_map(static fn(Change $change): array => [$change->action, $change->page->value], $plan->changes));
    }

    public function testNewDraftIsNotPlanned(): void
    {
        $this->site->pages->save($this->site->draftPage(self::FIRST, 'about', 'About'));

        $this->assertTrue($this->site->planChanges->handle()->isEmpty());
    }

    public function testPageInLineWithThePublicSiteIsNotPlanned(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();

        $this->assertTrue($this->site->planChanges->handle()->isEmpty());
    }

    public function testApplyingTwiceDoesNotBuildAgain(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->publisher->artifacts = [];

        $report = $this->site->applyChanges->handle();

        $this->assertSame(0, $report->built);
        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testOnlyTheChangedPageIsBuilt(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'contacts', 'Contacts'));
        $this->site->applyChanges->handle();
        $this->site->publisher->artifacts = [];
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'contacts', 'New contacts'));

        $report = $this->site->applyChanges->handle();

        $this->assertSame(1, $report->built);
        $this->assertSame(['contacts/index.html'], array_keys($this->site->publisher->artifacts));
    }

    public function testSavingWithoutChangesPlansNothing(): void
    {
        $page = $this->site->publishedPage(self::FIRST, 'about', 'About');
        $this->site->pages->save($page);
        $this->site->applyChanges->handle();

        $this->site->pages->save(new Page(
            $page->metadata->revised($page->metadata->details, new DateTimeImmutable('2027-01-01 00:00:00 UTC')),
            $page->payload,
        ));

        $this->assertTrue($this->site->planChanges->handle()->isEmpty());
    }

    public function testMovedPageIsRebuiltAtTheNewAddressAndGoneFromTheOldOne(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about-us', 'About'));

        $this->site->applyChanges->handle();

        $this->assertSame(['about-us/index.html'], array_keys($this->site->publisher->artifacts));
    }

    public function testDeletedPageIsTakenDown(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->delete(new PageId(self::FIRST));

        $report = $this->site->applyChanges->handle();

        $this->assertSame(1, $report->removed);
        $this->assertSame([], $this->site->publisher->artifacts);
        $this->assertSame([], $this->site->published->all());
    }

    public function testPageThatBecameADraftIsTakenDown(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->save($this->site->draftPage(self::FIRST, 'about', 'About'));

        $report = $this->site->applyChanges->handle();

        $this->assertSame(1, $report->removed);
        $this->assertSame([], $this->site->publisher->artifacts);
    }

    public function testRemovalsAreListedBeforeBuilds(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->delete(new PageId(self::FIRST));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'about', 'Replacement'));

        $plan = $this->site->planChanges->handle();

        $this->assertSame(
            [ChangeAction::Remove, ChangeAction::Build],
            array_map(static fn(Change $change): ChangeAction => $change->action, $plan->changes),
        );
        $this->assertCount(1, $plan->withAction(ChangeAction::Remove));
        $this->assertCount(1, $plan->withAction(ChangeAction::Build));
    }

    public function testPageTakingOverAnAddressOfADeletedPageKeepsItsHtml(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->delete(new PageId(self::FIRST));
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'about', 'Replacement'));

        $this->site->applyChanges->handle();

        $this->assertStringContainsString('Replacement', $this->site->publisher->artifacts['about/index.html'] ?? '');
    }

    public function testPageOfUnavailableTypeIsNotTouched(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->pages->save(new Page(
            $this->site->publishedPage(self::FIRST, 'about', 'About')->metadata,
            new UnavailablePayload(new ContentTypeId('gallery'), new PayloadData(['photos' => '[]'])),
        ));

        $report = $this->site->applyChanges->handle();

        $this->assertSame(0, $report->built + $report->removed);
        $this->assertSame(['about/index.html'], array_keys($this->site->publisher->artifacts));
    }

    public function testLostRecordBuildsEveryPublishedPageAgain(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::FIRST, 'about', 'About'));
        $this->site->applyChanges->handle();
        $this->site->published->forget(new PageId(self::FIRST));

        $report = $this->site->applyChanges->handle();

        $this->assertSame(1, $report->built);
        $this->assertCount(1, $this->site->published->all());
    }

    public function testFailureOfOneChangeDoesNotStopTheOthers(): void
    {
        $this->site->pages->save($this->site->publishedPage(self::SECOND, 'contacts', 'Contacts'));
        // A record of a page that no longer exists, pointing at an address the publisher cannot remove.
        $this->site->published->record(new PublishedPage(
            new PageId(self::FIRST),
            new PagePath('gone'),
            new ContentVersion(hash('sha256', 'x')),
        ));
        $this->site->publisher->failToRemove = 'gone/index.html';

        $report = $this->site->applyChanges->handle();

        $this->assertTrue($report->hasFailures());
        $this->assertSame([self::FIRST], array_map(static fn(PageId $id): string => $id->value, $report->failed));
        $this->assertSame(1, $report->built);
    }
}
