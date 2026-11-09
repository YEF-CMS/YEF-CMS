<?php

declare(strict_types=1);

namespace Yef\Storage;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Override;
use Yef\Domain\User\Exception\LoginAlreadyTaken;
use Yef\Domain\User\Exception\UserStorageFailed;
use Yef\Domain\User\User;
use Yef\Domain\User\UserId;
use Yef\Domain\User\UserLogin;
use Yef\Domain\User\UserRepository;

use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Keeps all users in one JSON file that only the owner of the server account can read.
 *
 * Unlike the indexes it is not rebuilt from anything: a damaged file is reported, not read as "no users",
 * because that would reopen the first-run setup to anyone who can reach the site.
 *
 * @psalm-api Wired in through the DI configuration.
 */
final readonly class FileUserRepository implements UserRepository
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    private Files $files;

    private FileLock $lock;

    public function __construct(
        private string $file,
        private string $lockFile,
    ) {
        $this->files = new Files();
        $this->lock = new FileLock($lockFile);
    }

    #[Override]
    public function save(User $user): void
    {
        try {
            $this->files->ensureDirectory(dirname($this->lockFile));
            $this->files->ensureDirectory(dirname($this->file));
            $this->lock->exclusive(fn() => $this->saveUnderLock($user));
        } catch (StorageIoFailure|JsonException $failure) {
            throw UserStorageFailed::whileSaving($failure);
        }
    }

    #[Override]
    public function findById(UserId $id): ?User
    {
        foreach ($this->load() as $user) {
            if ($user->id->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    #[Override]
    public function findByLogin(UserLogin $login): ?User
    {
        foreach ($this->load() as $user) {
            if ($user->login->equals($login)) {
                return $user;
            }
        }

        return null;
    }

    #[Override]
    public function hasAny(): bool
    {
        return $this->load() !== [];
    }

    /**
     * @throws StorageIoFailure
     * @throws JsonException
     * @throws LoginAlreadyTaken
     * @throws UserStorageFailed
     */
    private function saveUnderLock(User $user): void
    {
        $others = [];
        foreach ($this->load() as $existing) {
            if ($existing->id->equals($user->id)) {
                continue;
            }

            if ($existing->login->equals($user->login)) {
                throw LoginAlreadyTaken::byLogin($user->login);
            }

            $others[] = $existing;
        }

        $this->store([...$others, $user]);
    }

    /**
     * @return list<User>
     *
     * @throws UserStorageFailed When the file exists but cannot be read as a list of users.
     */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $decoded = json_decode($this->files->read($this->file), true, flags: \JSON_THROW_ON_ERROR);
            $records = is_array($decoded) ? ($decoded['users'] ?? null) : null;
            if (!is_array($records)) {
                throw new InvalidArgumentException('The user file has no list of users.');
            }

            $users = [];
            /** @psalm-suppress MixedAssignment Records are validated one by one. */
            foreach ($records as $record) {
                $users[] = $this->fromRecord($record);
            }

            return $users;
        } catch (JsonException|InvalidArgumentException|StorageIoFailure $failure) {
            throw UserStorageFailed::whileReading($failure);
        }
    }

    /**
     * @param list<User> $users
     *
     * @throws StorageIoFailure
     * @throws JsonException
     */
    private function store(array $users): void
    {
        $records = [];
        foreach ($users as $user) {
            $records[] = [
                'id' => $user->id->value,
                'login' => $user->login->value,
                'password_hash' => $user->passwordHash,
                'created_at' => $user->createdAt->format(self::DATE_FORMAT),
            ];
        }

        $this->files->writePrivately(
            $this->file,
            json_encode(
                ['users' => $records],
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            ) . "\n",
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fromRecord(mixed $record): User
    {
        if (!is_array($record)) {
            throw new InvalidArgumentException('A user record must be an array.');
        }

        return new User(
            new UserId($this->text($record, 'id')),
            new UserLogin($this->text($record, 'login')),
            $this->text($record, 'password_hash'),
            DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->text($record, 'created_at'))
                ?: throw new InvalidArgumentException('A user record has an invalid date.'),
        );
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function text(array $record, string $field): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $value = $record[$field] ?? null;

        return is_string($value) ? $value : throw new InvalidArgumentException('User field "' . $field . '" is missing.');
    }
}
