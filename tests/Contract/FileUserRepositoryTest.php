<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;
use Yef\Storage\FileUserRepository;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileUserRepository::class)]
final class FileUserRepositoryTest extends UserRepositoryContract
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testUsersSurviveANewInstance(): void
    {
        $user = $this->aUser();
        $this->createUsers()->save($user);

        $this->assertEquals($user, $this->createUsers()->findByLogin(new UserLogin('admin')));
    }

    public function testFileIsReadableByItsOwnerOnly(): void
    {
        $this->createUsers()->save($this->aUser());

        $this->assertSame(0o600, (int) fileperms($this->file()) & 0o777);
    }

    public function testDamagedFileIsReportedAndNeverReadAsNoUsers(): void
    {
        $this->createUsers()->save($this->aUser());
        file_put_contents($this->file(), '{ nope');

        $this->expectException(UserStorageFailed::class);

        $this->createUsers()->hasAny();
    }

    public function testDamagedFileIsNotOverwrittenBySaving(): void
    {
        $this->createUsers()->save($this->aUser());
        file_put_contents($this->file(), '{ nope');

        try {
            $this->createUsers()->save($this->aUser());
            $this->fail('Saving over a damaged file must fail.');
        } catch (UserStorageFailed) {
            $this->assertSame('{ nope', file_get_contents($this->file()));
        }
    }

    public function testFileWithAnInvalidRecordIsReported(): void
    {
        mkdir(dirname($this->file()), 0o775, true);
        file_put_contents($this->file(), '{"users": [{"id": "x"}]}');

        $this->expectException(UserStorageFailed::class);

        $this->createUsers()->findByLogin(new UserLogin('admin'));
    }

    #[Override]
    protected function createUsers(): UserRepository
    {
        return new FileUserRepository($this->file(), $this->directory->path . '/var/users.lock');
    }

    private function file(): string
    {
        return $this->directory->path . '/data/users.json';
    }

    private function aUser(): User
    {
        return new User(
            new UserId('01ARZ3NDEKTSV4RRFFQ69G5001'),
            new UserLogin('admin'),
            'hash',
            new DateTimeImmutable('2026-11-09 12:00:00 UTC'),
        );
    }
}
