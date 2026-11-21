<?php

declare(strict_types=1);

namespace Yef\Console;

use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yef\Application\ModuleInstall\NotInstalledByInstaller;
use Yef\Application\ModuleInstall\RemoveModule;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;

use function is_string;
use function sprintf;

/**
 * Removes a module the installer brought.
 *
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:module:remove', description: 'Remove a module the installer brought: switch it off and delete its files')]
final class RemoveModuleCommand extends Command
{
    public function __construct(private readonly RemoveModule $removeModule)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the module, vendor/name');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Remove without asking for confirmation');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $name = $input->getArgument('name');
        $name = is_string($name) ? $name : '';

        if ($input->getOption('yes') !== true && !$this->confirmed($input, $output, $name)) {
            $output->writeln('Nothing was removed.');

            return Command::FAILURE;
        }

        try {
            $this->removeModule->handle($name);
        } catch (InvalidArgumentException|NotInstalledByInstaller|ModuleSettingsFailed|ModuleFilesFailed $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>[OK]</info> %s is removed.', $name));

        return Command::SUCCESS;
    }

    private function confirmed(InputInterface $input, OutputInterface $output, string $name): bool
    {
        $question = new ConfirmationQuestion(
            sprintf('Remove %s? Its files are deleted; pages of its types become unavailable. [y/N] ', $name),
            false,
        );

        return new QuestionHelper()->ask($input, $output, $question) === true;
    }
}
