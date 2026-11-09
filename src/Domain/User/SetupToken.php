<?php

declare(strict_types=1);

namespace Yef\Domain\User;

use Yef\Domain\User\Exception\UserStorageFailed;

/**
 * A secret code that proves the person setting up the site can read the server's files.
 * It is created the first time it is asked for and lives until it is used.
 */
interface SetupToken
{
    /**
     * @throws UserStorageFailed
     */
    public function current(): string;

    /**
     * Makes the current code useless. Does nothing when there is none.
     *
     * @throws UserStorageFailed
     */
    public function consume(): void;
}
