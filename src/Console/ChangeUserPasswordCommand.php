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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Yef\Application\User\ChangePassword;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserNotFound;
use Yef\Domain\User\Exception\UserStorageFailed;

use function sprintf;

/**
 * @psalm-api Wired through config/console/commands.php.
 */
#[AsCommand(name: 'yef:user:password', description: 'Set a new password for a user, for example one who forgot theirs')]
final class ChangeUserPasswordCommand extends Command
{
    private const string LOGIN = 'login';

    public function __construct(private readonly ChangePassword $changePassword)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::LOGIN, InputArgument::REQUIRED, 'Login of the user');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $login Required argument, so it is always given. */
        $login = $input->getArgument(self::LOGIN);
        $question = new Question('New password (hidden): ');
        $question->setHidden(true)->setHiddenFallback(false);

        /** @var string|null $password */
        $password = new QuestionHelper()->ask($input, $output, $question);

        try {
            $this->changePassword->handle($login, $password ?? '');
        } catch (InvalidArgumentException|UserNotFound|LoginAlreadyTaken|UserStorageFailed $failure) {
            $output->writeln(sprintf('<error>[FAIL]</error> %s', $failure->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>[ ok ]</info> The password of %s is changed.', $login));

        return Command::SUCCESS;
    }
}
