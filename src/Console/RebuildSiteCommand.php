<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Publishing\PublishResult;
use Yef\Application\Publishing\RebuildSite;
use Yef\Domain\Page\Exception\PageStorageFailed;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:rebuild', description: 'Publish every page again')]
final class RebuildSiteCommand extends Command
{
    public function __construct(private readonly RebuildSite $rebuildSite)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = $this->rebuildSite->handle();
        } catch (PageStorageFailed $pageStorageFailed) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $pageStorageFailed->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Published: %d, withdrawn: %d, skipped: %d.',
            $report->count(PublishResult::Published),
            $report->count(PublishResult::Withdrawn),
            $report->count(PublishResult::Skipped),
        ));

        foreach ($report->failed as $id) {
            $output->writeln(sprintf('<error>[FAIL]</error> Page %s could not be published.', $id));
        }

        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
}
