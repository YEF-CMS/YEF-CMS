<?php

declare(strict_types=1);

namespace Yef\Tests\Console;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yef\Application\User\Authenticate;
use Yef\Application\User\ChangePassword;
use Yef\Application\User\CreateUser;
use Yef\Application\User\PasswordPolicy;
use Yef\Console\ChangeUserPasswordCommand;
use Yef\Console\CreateUserCommand;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Tests\Support\FakePasswordHasher;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemoryUserRepository;
use Yef\Tests\Support\SequentialUserIdGenerator;

#[CoversClass(CreateUserCommand::class)]
#[CoversClass(ChangeUserPasswordCommand::class)]
final class UserCommandsTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    #[Override]
    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->hasher = new FakePasswordHasher();
    }

    public function testCreatesAUserWithThePasswordTypedAtThePrompt(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->setInputs(['secret1']);

        $tester->execute(['login' => 'owner']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('owner', 'secret1'));
        $this->assertStringNotContainsString('secret1', $tester->getDisplay());
    }

    public function testRefusesAWeakPasswordAndCreatesNothing(): void
    {
        $tester = new CommandTester($this->createCommand());
        $tester->setInputs(['1']);

        $tester->execute(['login' => 'owner']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('at least 6', $tester->getDisplay());
        $this->assertFalse($this->users->hasAny());
    }

    public function testRefusesALoginThatIsTaken(): void
    {
        $this->users->save($this->existingUser());
        $tester = new CommandTester($this->createCommand());
        $tester->setInputs(['secret1']);

        $tester->execute(['login' => 'owner']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('already taken', $tester->getDisplay());
    }

    public function testChangesThePasswordOfAnExistingUser(): void
    {
        $this->users->save($this->existingUser());
        $tester = new CommandTester(new ChangeUserPasswordCommand(new ChangePassword($this->users, $this->hasher, new PasswordPolicy(6))));
        $tester->setInputs(['another2']);

        $tester->execute(['login' => 'owner']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('owner', 'another2'));
    }

    public function testChangingThePasswordOfAnUnknownUserFails(): void
    {
        $tester = new CommandTester(new ChangeUserPasswordCommand(new ChangePassword($this->users, $this->hasher, new PasswordPolicy(6))));
        $tester->setInputs(['another2']);

        $tester->execute(['login' => 'nobody']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('There is no user', $tester->getDisplay());
    }

    private function createCommand(): CreateUserCommand
    {
        return new CreateUserCommand(new CreateUser(
            $this->users,
            $this->hasher,
            new SequentialUserIdGenerator(),
            new FrozenClock(),
            new PasswordPolicy(6),
        ));
    }

    private function existingUser(): User
    {
        return new User(
            new UserId('01JT0000000000000000000099'),
            new UserLogin('owner'),
            'hash:secret1',
            new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
        );
    }
}
