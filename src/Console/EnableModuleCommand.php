<?php

declare(strict_types=1);

namespace Yef\Console;

use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Module\EnableModule;
use Yef\Domain\Module\Exception\ModuleCannotBeEnabled;
use Yef\Domain\Module\Exception\ModuleNotFound;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:module:enable', description: 'Enable an installed module')]
final class EnableModuleCommand extends Command
{
    private const string NAME = 'name';

    public function __construct(private readonly EnableModule $enableModule)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::NAME, InputArgument::REQUIRED, 'Module name, for example yef-cms/text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name Required argument, so it is always given. */
        $name = $input->getArgument(self::NAME);

        try {
            $this->enableModule->handle($name);
        } catch (InvalidArgumentException|ModuleNotFound|ModuleCannotBeEnabled|ModuleSettingsFailed $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>[ ok ]</info> Module %s is enabled.', $name));

        return Command::SUCCESS;
    }
}
