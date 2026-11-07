<?php

declare(strict_types=1);

namespace Yef\Setup;

enum CheckStatus: string
{
    /** Requirement is met. */
    case Ok = 'ok';
    /** Optional or recommended feature is missing; the system works with reduced functionality. */
    case Warning = 'warning';
    /** Hard requirement is not met; the system cannot work. */
    case Fail = 'fail';
}
