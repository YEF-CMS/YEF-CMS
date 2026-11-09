<?php

declare(strict_types=1);

use Yef\Application\User\PasswordPolicy;
use Yef\Domain\Clock;
use Yef\Domain\User\PasswordHasher;
use Yef\Domain\User\SetupToken;
use Yef\Domain\User\UserIdGenerator;
use Yef\Domain\User\UserRepository;
use Yef\Platform\SystemClock;
use Yef\Platform\YiiPasswordHasher;
use Yef\Storage\FileSetupToken;
use Yef\Storage\FileUserRepository;
use Yef\Storage\UlidUserIdGenerator;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

return [
    UserRepository::class => static fn(Aliases $aliases): FileUserRepository => new FileUserRepository(
        $aliases->get('@data') . '/users.json',
        $aliases->get('@runtime') . '/storage/users.lock',
    ),
    SetupToken::class => static fn(Aliases $aliases): FileSetupToken => new FileSetupToken(
        $aliases->get('@runtime') . '/setup-token',
        $aliases->get('@runtime') . '/storage/setup.lock',
    ),
    UserIdGenerator::class => UlidUserIdGenerator::class,
    PasswordHasher::class => YiiPasswordHasher::class,
    Clock::class => SystemClock::class,
    PasswordPolicy::class => static fn(): PasswordPolicy => new PasswordPolicy($params['yef']['admin']['minPasswordLength']),
];
