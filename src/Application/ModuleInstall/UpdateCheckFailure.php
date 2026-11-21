<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

enum UpdateCheckFailure: string
{
    case RepositoryGone = 'repository_gone';

    case NoRelease = 'no_release';

    case BadVersion = 'bad_version';

    case RateLimited = 'rate_limited';

    case Unreachable = 'unreachable';
}
