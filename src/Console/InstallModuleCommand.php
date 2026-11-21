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
use Yef\Application\ModuleInstall\InspectModule;
use Yef\Application\ModuleInstall\InstallModule;
use Yef\Application\ModuleInstall\ModuleFolderNotWritable;
use Yef\Application\ModuleInstall\OfferCannotBeApplied;
use Yef\Domain\Module\Exception\ModuleSettingsFailed;
use Yef\Domain\Module\Install\Exception\InvalidModule;
use Yef\Domain\Module\Install\Exception\InvalidReleaseVersion;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;
use Yef\Domain\Module\Install\Exception\ModuleFilesFailed;
use Yef\Domain\Module\Install\Exception\NoRelease;
use Yef\Domain\Module\Install\Exception\RepositoryNotFound;
use Yef\Domain\Module\Install\Exception\SourceUnavailable;
use Yef\Domain\Module\Install\Exception\StagingNotFound;
use Yef\Domain\Module\Install\Exception\UnsafeArchive;

use function is_string;
use function sprintf;

/**
 * Installs a module from the latest release of a GitHub repository, after showing what it is.
 *
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:module:install', description: 'Install a module from the latest release of a GitHub repository')]
final class InstallModuleCommand extends Command
{
    public function __construct(
        private readonly InspectModule $inspectModule,
        private readonly InstallModule $installModule,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('address', InputArgument::REQUIRED, 'Address of the repository, https://github.com/<owner>/<repository>');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Install without asking for confirmation');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $address = $input->getArgument('address');

        try {
            $offer = $this->inspectModule->handle(is_string($address) ? $address : '');
            ModuleOfferPrinter::print($output, $offer);
            if (!$offer->canBeApplied()) {
                $output->writeln('<error>[FAIL]</error> The module cannot be installed for the reasons above.');

                return Command::FAILURE;
            }

            if ($input->getOption('yes') !== true && !$this->confirmed($input, $output)) {
                $output->writeln('Nothing was installed.');

                return Command::FAILURE;
            }

            $installed = $this->installModule->handle($offer->token);
        } catch (InvalidRepositoryAddress|RepositoryNotFound|NoRelease|InvalidReleaseVersion|SourceUnavailable|UnsafeArchive|InvalidModule|ModuleFilesFailed|ModuleSettingsFailed|ModuleFolderNotWritable|OfferCannotBeApplied|StagingNotFound $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>[OK]</info> %s %s is installed. It is not enabled yet: php bin/yef yef:module:enable %s',
            $installed->name,
            $installed->version,
            $installed->name,
        ));

        return Command::SUCCESS;
    }

    private function confirmed(InputInterface $input, OutputInterface $output): bool
    {
        $question = new ConfirmationQuestion('A module is code that runs on your site with full rights. Install it? [y/N] ', false);

        return new QuestionHelper()->ask($input, $output, $question) === true;
    }
}
