<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Module\ListModules;
use Yef\Domain\Module\ModuleStatus;

use function array_map;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:modules', description: 'List the installed modules')]
final class ListModulesCommand extends Command
{
    public function __construct(private readonly ListModules $listModules)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new Table($output)
            ->setHeaders(['Module', 'Name', 'Version', 'State', 'Reason'])
            ->setRows(array_map(
                static fn(ModuleStatus $status): array => [
                    $status->title,
                    $status->id,
                    $status->version,
                    $status->state->value,
                    $status->reason,
                ],
                $this->listModules->handle(),
            ))
            ->render();

        return Command::SUCCESS;
    }
}
