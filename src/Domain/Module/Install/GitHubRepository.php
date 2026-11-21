<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install;

use Override;
use Stringable;
use Yef\Domain\Module\Install\Exception\InvalidRepositoryAddress;

use function preg_match;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function substr;

/**
 * A repository on GitHub, by owner and name. Built only from an address of the form
 * `https://github.com/<owner>/<repository>`.
 */
final readonly class GitHubRepository implements Stringable
{
    private const string ADDRESS = '#^https://github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9._-]{1,100})/?$#';

    private function __construct(
        public string $owner,
        public string $name,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return $this->owner . '/' . $this->name;
    }

    /**
     * Accepts the address with a ".git" ending or a slash at the end. Everything else is refused: other schemes
     * and hosts, user names, ports, query strings, and any path beyond the owner and the repository.
     *
     * @throws InvalidRepositoryAddress
     */
    public static function fromAddress(string $address): self
    {
        $address = rtrim(trim($address), '/');
        if (str_ends_with($address, '.git')) {
            $address = substr($address, 0, -4);
        }

        if (preg_match(self::ADDRESS, $address, $parts) !== 1) {
            throw InvalidRepositoryAddress::forAddress($address);
        }

        $name = $parts[2] ?? '';
        if ($name === '.' || $name === '..') {
            throw InvalidRepositoryAddress::forAddress($address);
        }

        return new self($parts[1] ?? '', $name);
    }

    public function address(): string
    {
        return sprintf('https://github.com/%s/%s', $this->owner, $this->name);
    }

    public function equals(self $other): bool
    {
        return $this->owner === $other->owner && $this->name === $other->name;
    }
}
