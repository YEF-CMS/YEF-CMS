<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

/**
 * Behavior every UserRepository implementation has to provide.
 */
abstract class UserRepositoryContract extends TestCase
{
    private const string FIRST_ID = '01ARZ3NDEKTSV4RRFFQ69G5001';

    private const string SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5002';

    public function testRepositoryStartsEmpty(): void
    {
        $users = $this->createUsers();

        $this->assertFalse($users->hasAny());
        $this->assertNotInstanceOf(User::class, $users->findByLogin(new UserLogin('admin')));
        $this->assertNotInstanceOf(User::class, $users->findById(new UserId(self::FIRST_ID)));
    }

    public function testSavedUserCanBeFoundByLoginAndById(): void
    {
        $users = $this->createUsers();
        $user = $this->user(self::FIRST_ID, 'admin');

        $users->save($user);

        $this->assertTrue($users->hasAny());
        $this->assertEquals($user, $users->findByLogin(new UserLogin('Admin')));
        $this->assertEquals($user, $users->findById(new UserId(self::FIRST_ID)));
    }

    public function testSavingAgainReplacesTheUser(): void
    {
        $users = $this->createUsers();
        $users->save($this->user(self::FIRST_ID, 'admin'));

        $changed = $this->user(self::FIRST_ID, 'admin')->withPasswordHash('another-hash');

        $users->save($changed);

        $this->assertEquals($changed, $users->findById(new UserId(self::FIRST_ID)));
    }

    public function testTwoUsersCannotShareALogin(): void
    {
        $users = $this->createUsers();
        $users->save($this->user(self::FIRST_ID, 'admin'));

        $this->expectException(LoginAlreadyTaken::class);

        $users->save($this->user(self::SECOND_ID, 'admin'));
    }

    public function testUsersAreKeptApart(): void
    {
        $users = $this->createUsers();
        $first = $this->user(self::FIRST_ID, 'admin');
        $second = $this->user(self::SECOND_ID, 'editor');

        $users->save($first);
        $users->save($second);

        $this->assertEquals($first, $users->findByLogin(new UserLogin('admin')));
        $this->assertEquals($second, $users->findByLogin(new UserLogin('editor')));
    }

    public function testDatesKeepMicrosecondsAndOffset(): void
    {
        $users = $this->createUsers();
        $user = new User(
            new UserId(self::FIRST_ID),
            new UserLogin('admin'),
            'hash',
            new DateTimeImmutable('2026-01-02 03:04:05.678901 +03:00'),
        );

        $users->save($user);

        $this->assertEquals($user, $users->findById($user->id));
    }

    abstract protected function createUsers(): UserRepository;

    private function user(string $id, string $login): User
    {
        return new User(
            new UserId($id),
            new UserLogin($login),
            'hash-of-' . $login,
            new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
        );
    }
}
