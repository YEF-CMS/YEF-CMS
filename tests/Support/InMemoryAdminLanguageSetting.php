<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Settings\AdminLanguageSetting;
use Yef\Domain\Settings\Language;

final class InMemoryAdminLanguageSetting implements AdminLanguageSetting
{
    public function __construct(private ?Language $language = null) {}

    #[Override]
    public function chosen(): ?Language
    {
        return $this->language;
    }

    #[Override]
    public function choose(Language $language): void
    {
        $this->language = $language;
    }
}
