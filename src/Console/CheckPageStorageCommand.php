<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Page\CheckPageStorage;
use Yef\Application\Page\RepairPageStorage;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Page\IntegrityReport;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:storage:check', description: 'Check the page storage and optionally repair it')]
final class CheckPageStorageCommand extends Command
{
    private const string REPAIR = 'repair';

    public function __construct(
        private readonly CheckPageStorage $check,
        private readonly RepairPageStorage $repair,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(self::REPAIR, null, InputOption::VALUE_NONE, 'Remove leftovers and rebuild what can be rebuilt');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = $input->getOption(self::REPAIR) === true ? $this->repair->handle() : $this->check->handle();
        } catch (PageStorageFailed $pageStorageFailed) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $pageStorageFailed->getMessage()));

            return Command::FAILURE;
        }

        $this->print($report, $output);

        return $report->isHealthy() ? Command::SUCCESS : Command::FAILURE;
    }

    private function print(IntegrityReport $report, OutputInterface $output): void
    {
        if ($report->isHealthy()) {
            $output->writeln('<info>[ ok ]</info> The page storage is healthy.');

            return;
        }

        if ($report->leftovers > 0) {
            $output->writeln(sprintf(
                '<comment>[warn]</comment> %d leftover(s) of interrupted writes; run with --repair to clean up.',
                $report->leftovers,
            ));
        }

        foreach ($report->damagedPages as $id) {
            $output->writeln(sprintf('<error>[FAIL]</error> Page %s is damaged and cannot be read.', $id));
        }
    }
}
