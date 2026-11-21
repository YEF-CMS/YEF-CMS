<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yef\Application\ModuleInstall\ModuleFolderNotWritable;
use Yef\Application\ModuleInstall\ModuleIsUpToDate;
use Yef\Application\ModuleInstall\NotInstalledByInstaller;
use Yef\Application\ModuleInstall\OfferCannotBeApplied;
use Yef\Application\ModuleInstall\PrepareModuleUpdate;
use Yef\Application\ModuleInstall\UpdateModule;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;

use function is_string;
use function sprintf;

/**
 * Updates an installed module to the latest release of its repository, after showing what it is.
 *
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:module:update', description: 'Update a module the installer brought to the latest release of its repository')]
final class UpdateModuleCommand extends Command
{
    public function __construct(
        private readonly PrepareModuleUpdate $prepareUpdate,
        private readonly UpdateModule $updateModule,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the module, vendor/name');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Update without asking for confirmation');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $name = $input->getArgument('name');

        try {
            $offer = $this->prepareUpdate->handle(is_string($name) ? $name : '');
            ModuleOfferPrinter::print($output, $offer);
            if (!$offer->canBeApplied()) {
                $output->writeln('<error>[FAIL]</error> The module cannot be updated for the reasons above.');

                return Command::FAILURE;
            }

            if ($input->getOption('yes') !== true && !$this->confirmed($input, $output)) {
                $output->writeln('Nothing was updated.');

                return Command::FAILURE;
            }

            $updated = $this->updateModule->handle($offer->token);
        } catch (ModuleIsUpToDate $upToDate) {
            $output->writeln(sprintf('<info>[OK]</info> %s', $upToDate->getMessage()));

            return Command::SUCCESS;
        } catch (NotInstalledByInstaller|RepositoryNotFound|NoRelease|InvalidReleaseVersion|SourceUnavailable|UnsafeArchive|InvalidModule|ModuleFilesFailed|ModuleSettingsFailed|ModuleFolderNotWritable|OfferCannotBeApplied|StagingNotFound $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>[OK]</info> %s is updated to %s.', $updated->name, $updated->version));

        return Command::SUCCESS;
    }

    private function confirmed(InputInterface $input, OutputInterface $output): bool
    {
        $question = new ConfirmationQuestion('A module is code that runs on your site with full rights. Update it? [y/N] ', false);

        return new QuestionHelper()->ask($input, $output, $question) === true;
    }
}
