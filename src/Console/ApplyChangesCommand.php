<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Publishing\ApplyChanges;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Publishing\Exception\PublishingFailed;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:apply', description: 'Build the pages that changed and take down the ones that are gone')]
final class ApplyChangesCommand extends Command
{
    public function __construct(private readonly ApplyChanges $applyChanges)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = $this->applyChanges->handle();
        } catch (PageStorageFailed|PublishingFailed $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Built: %d, taken down: %d, skipped: %d.',
            $report->built,
            $report->removed,
            $report->skipped,
        ));

        foreach ($report->failed as $id) {
            $output->writeln(sprintf('<error>[FAIL]</error> The change of page %s could not be applied.', $id));
        }

        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
}
