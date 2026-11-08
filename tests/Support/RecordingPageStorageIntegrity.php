<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Page\IntegrityReport;
use Yef\Domain\Page\PageStorageIntegrity;

/**
 * Answers with prepared reports and remembers what was asked of it.
 */
final class RecordingPageStorageIntegrity implements PageStorageIntegrity
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private readonly IntegrityReport $foundByCheck,
        private readonly IntegrityReport $leftAfterRepair,
    ) {}

    #[Override]
    public function check(): IntegrityReport
    {
        $this->calls[] = 'check';

        return $this->foundByCheck;
    }

    #[Override]
    public function repair(): IntegrityReport
    {
        $this->calls[] = 'repair';

        return $this->leftAfterRepair;
    }
}
