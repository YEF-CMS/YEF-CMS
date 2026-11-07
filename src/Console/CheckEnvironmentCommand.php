<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Setup\CheckStatus;
use Yef\Setup\EnvironmentChecker;
use Yiisoft\Aliases\Aliases;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:check', description: 'Check that the hosting environment meets the requirements')]
final class CheckEnvironmentCommand extends Command
{
    public function __construct(private readonly Aliases $aliases)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = EnvironmentChecker::fromRuntime([
            $this->aliases->get('@runtime'),
        ])->check();

        foreach ($report->results as $result) {
            $label = match ($result->status) {
                CheckStatus::Ok => '<info>[ ok ]</info>',
                CheckStatus::Warning => '<comment>[warn]</comment>',
                CheckStatus::Fail => '<error>[FAIL]</error>',
            };
            $output->writeln(sprintf('%s %s: %s', $label, $result->name, $result->message));
        }

        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
}
