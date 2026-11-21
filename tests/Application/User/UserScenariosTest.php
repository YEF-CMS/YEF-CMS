<?php

declare(strict_types=1);

namespace Yef\Tests\Application\User;

use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yef\Application\User\Authenticate;
use Yef\Application\User\ChangePassword;
use Yef\Application\User\CompleteSetup;
use Yef\Application\User\CreateUser;
use Yef\Application\User\InvalidSetupToken;
use Yef\Application\User\PasswordPolicy;
use Yef\Application\User\PrepareSetup;
use Yef\Application\User\SetupNotAllowed;
use Yef\Application\User\WeakPassword;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserNotFound;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Tests\Support\FakePasswordHasher;
use Yef\Tests\Support\FrozenClock;
use Yef\Tests\Support\InMemorySetupToken;
use Yef\Tests\Support\InMemoryUserRepository;
use Yef\Tests\Support\RecordingDefaultModules;
use Yef\Tests\Support\SequentialUserIdGenerator;

#[CoversClass(CreateUser::class)]
#[CoversClass(ChangePassword::class)]
#[CoversClass(Authenticate::class)]
#[CoversClass(PrepareSetup::class)]
#[CoversClass(CompleteSetup::class)]
#[CoversClass(PasswordPolicy::class)]
#[CoversClass(WeakPassword::class)]
#[CoversClass(SetupNotAllowed::class)]
#[CoversClass(InvalidSetupToken::class)]
final class UserScenariosTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    private InMemorySetupToken $token;

    private CreateUser $createUser;

    private RecordingDefaultModules $defaultModules;

    #[Override]
    protected function setUp(): void
    {
        $this->defaultModules = new RecordingDefaultModules();
        $this->users = new InMemoryUserRepository();
        $this->hasher = new FakePasswordHasher();
        $this->token = new InMemorySetupToken();
        $this->createUser = new CreateUser(
            $this->users,
            $this->hasher,
            new SequentialUserIdGenerator(),
            new FrozenClock(),
            new PasswordPolicy(6),
        );
    }

    public function testCreatesAUserWithAHashedPassword(): void
    {
        $user = $this->createUser->handle('Editor', 'secret1');

        $this->assertSame('editor', $user->login->value);
        $this->assertSame('hash:secret1', $user->passwordHash);
        $this->assertEquals($user, $this->users->findByLogin(new UserLogin('editor')));
    }

    public function testRejectsAPasswordThatIsTooShort(): void
    {
        $this->expectException(WeakPassword::class);
        $this->expectExceptionMessage('at least 6');

        $this->createUser->handle('editor', '12345');

        $this->assertFalse($this->users->hasAny());
    }

    public function testRejectsAPasswordThatIsTooLongForTheHash(): void
    {
        $this->expectException(WeakPassword::class);

        $this->createUser->handle('editor', str_repeat('a', 73));
    }

    public function testMinimumLengthCountsCharactersNotBytes(): void
    {
        $user = new CreateUser(
            $this->users,
            $this->hasher,
            new SequentialUserIdGenerator(),
            new FrozenClock(),
            new PasswordPolicy(6),
        )->handle('editor', 'пароль');

        $this->assertSame('hash:пароль', $user->passwordHash);
    }

    public function testMinimumLengthIsASetting(): void
    {
        $this->expectException(WeakPassword::class);

        new CreateUser($this->users, $this->hasher, new SequentialUserIdGenerator(), new FrozenClock(), new PasswordPolicy(12))
            ->handle('editor', 'secret1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLogins(): iterable
    {
        yield 'too short' => ['a'];
        yield 'spaces' => ['two words'];
        yield 'symbols' => ['ed@itor'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidLogins')]
    public function testRejectsAnInvalidLogin(string $login): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createUser->handle($login, 'secret1');
    }

    public function testLoginsDifferingOnlyInCaseAreTheSame(): void
    {
        $this->createUser->handle('Editor', 'secret1');

        $this->expectException(LoginAlreadyTaken::class);

        $this->createUser->handle('EDITOR', 'secret2');
    }

    public function testAuthenticatesWithTheRightPassword(): void
    {
        $this->createUser->handle('editor', 'secret1');

        $user = new Authenticate($this->users, $this->hasher)->handle('Editor', 'secret1');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('editor', $user->login->value);
    }

    public function testRefusesAWrongPassword(): void
    {
        $this->createUser->handle('editor', 'secret1');

        $this->assertNotInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('editor', 'secret2'));
    }

    public function testRefusesAnUnknownLoginButStillSpendsTheTimeOfAHash(): void
    {
        $before = count($this->hasher->hashed);

        $user = new Authenticate($this->users, $this->hasher)->handle('nobody', 'secret1');

        $this->assertNotInstanceOf(User::class, $user);
        $this->assertCount($before + 1, $this->hasher->hashed);
    }

    public function testRefusesALoginThatCannotExistWithoutAnError(): void
    {
        $this->assertNotInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('not a login!', 'secret1'));
    }

    public function testHashMadeWithOldSettingsIsMadeAgainAfterASuccessfulSignIn(): void
    {
        $this->users->save(new User(
            new UserId('01JT0000000000000000000009'),
            new UserLogin('editor'),
            'old:secret1',
            new DateTimeImmutable('2026-01-01 00:00:00 UTC'),
        ));

        $user = new Authenticate($this->users, $this->hasher)->handle('editor', 'secret1');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('hash:secret1', $user->passwordHash);
        $this->assertSame('hash:secret1', $this->users->findByLogin(new UserLogin('editor'))?->passwordHash);
    }

    public function testChangesAPassword(): void
    {
        $this->createUser->handle('editor', 'secret1');

        new ChangePassword($this->users, $this->hasher, new PasswordPolicy(6))->handle('editor', 'another2');

        $this->assertInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('editor', 'another2'));
        $this->assertNotInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('editor', 'secret1'));
    }

    public function testChangingThePasswordOfAnUnknownUserFails(): void
    {
        $this->expectException(UserNotFound::class);

        new ChangePassword($this->users, $this->hasher, new PasswordPolicy(6))->handle('nobody', 'another2');
    }

    public function testChangingToAWeakPasswordKeepsTheOldOne(): void
    {
        $this->createUser->handle('editor', 'secret1');

        try {
            new ChangePassword($this->users, $this->hasher, new PasswordPolicy(6))->handle('editor', 'x');
            $this->fail('A weak password must be refused.');
        } catch (WeakPassword) {
            $this->assertInstanceOf(User::class, new Authenticate($this->users, $this->hasher)->handle('editor', 'secret1'));
        }
    }

    public function testSetupIsRequiredUntilThereIsAUserAndCreatesTheCode(): void
    {
        $prepare = new PrepareSetup($this->users, $this->token);

        $this->assertTrue($prepare->handle());
        $this->assertTrue($this->token->exists());

        $this->createUser->handle('editor', 'secret1');

        $this->assertFalse($prepare->handle());
    }

    public function testSetupCreatesTheFirstUserAndUsesUpTheCode(): void
    {
        $code = $this->token->current();

        $user = $this->completeSetup()->handle('admin', 'secret1', ' ' . $code . "\n");

        $this->assertSame('admin', $user->login->value);
        $this->assertFalse($this->token->exists());
    }

    public function testSetupStartsTheSiteWithItsDefaultModules(): void
    {
        $this->completeSetup()->handle('admin', 'secret1', $this->token->current());

        $this->assertSame(1, $this->defaultModules->provided);
    }

    public function testCreatingUsersOutsideSetupDoesNotTouchTheModules(): void
    {
        $this->createUser->handle('admin', 'secret1');

        $this->assertSame(0, $this->defaultModules->provided);
    }

    public function testSetupWithAWrongCodeCreatesNothing(): void
    {
        $this->token->current();

        try {
            $this->completeSetup()->handle('admin', 'secret1', 'guess');
            $this->fail('A wrong code must be refused.');
        } catch (InvalidSetupToken) {
            $this->assertSame(0, $this->defaultModules->provided);
            $this->assertFalse($this->users->hasAny());
            $this->assertTrue($this->token->exists());
        }
    }

    public function testSetupWithAWeakPasswordKeepsTheCode(): void
    {
        $code = $this->token->current();

        try {
            $this->completeSetup()->handle('admin', '1', $code);
            $this->fail('A weak password must be refused.');
        } catch (WeakPassword) {
            $this->assertTrue($this->token->exists());
        }
    }

    public function testSetupIsRefusedOnceTheSiteHasAUser(): void
    {
        $this->createUser->handle('editor', 'secret1');

        $this->expectException(SetupNotAllowed::class);

        $this->completeSetup()->handle('admin', 'secret1', $this->token->current());
    }

    private function completeSetup(): CompleteSetup
    {
        return new CompleteSetup($this->users, $this->token, $this->createUser, $this->defaultModules);
    }
}
