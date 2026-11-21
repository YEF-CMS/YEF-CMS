<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\ModuleInstall\CheckModuleUpdates;
use Yef\Application\ModuleInstall\UpdateCheck;

use function array_map;

/**
 * Compares the release installed for each module the installer brought with the latest one on GitHub.
 *
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:module:updates', description: 'Check whether the modules the installer brought have newer releases')]
final class CheckModuleUpdatesCommand extends Command
{
    public function __construct(private readonly CheckModuleUpdates $checkUpdates)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new Table($output)
            ->setHeaders(['Module', 'Installed', 'Latest', 'Update'])
            ->setRows(array_map(
                static fn(UpdateCheck $check): array => [
                    $check->module->name->value,
                    $check->module->version->value,
                    $check->latest->value ?? '?',
                    match (true) {
                        $check->failure instanceof \Yef\Application\ModuleInstall\UpdateCheckFailure => 'could not be checked: ' . $check->failure->value,
                        $check->updateIsAvailable() => 'available: php bin/yef yef:module:update ' . $check->module->name->value,
                        default => 'up to date',
                    },
                ],
                $this->checkUpdates->handle(),
            ))
            ->render();

        return Command::SUCCESS;
    }
}
