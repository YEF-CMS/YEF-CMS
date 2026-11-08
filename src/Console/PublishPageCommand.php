<?php

declare(strict_types=1);

namespace Yef\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yef\Application\Publishing\PublishPage;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\Exception\PageNotFound;
use Yef\Domain\Page\Exception\PageStorageFailed;
use Yef\Domain\Publishing\Exception\PublishingFailed;
use Yef\Domain\Rendering\Exception\RenderingFailed;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:publish', description: 'Publish the HTML of one page')]
final class PublishPageCommand extends Command
{
    private const string ID = 'id';

    public function __construct(private readonly PublishPage $publishPage)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ID, InputArgument::REQUIRED, 'Id of the page');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $id Required argument, so it is always given. */
        $id = $input->getArgument(self::ID);

        try {
            $result = $this->publishPage->handle($id);
        } catch (PageNotFound|PageDataCorrupted|PageStorageFailed|PublishingFailed|RenderingFailed $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>[ ok ]</info> Page %s: %s.', $id, $result->value));

        return Command::SUCCESS;
    }
}
