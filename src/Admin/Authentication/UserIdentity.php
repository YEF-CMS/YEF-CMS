<?php

declare(strict_types=1);

namespace Yef\Admin\Authentication;

use Override;
use Yiisoft\Auth\IdentityInterface;

/**
 * Who is signed in, as the Yii user package sees them: just the id of the user.
 */
final readonly class UserIdentity implements IdentityInterface
{
    public function __construct(private string $id) {}

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }
}
