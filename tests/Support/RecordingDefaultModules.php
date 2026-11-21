<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Module\DefaultModules;

final class RecordingDefaultModules implements DefaultModules
{
    public int $provided = 0;

    #[Override]
    public function provide(): void
    {
        ++$this->provided;
    }
}
