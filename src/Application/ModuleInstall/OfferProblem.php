<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

/**
 * Why a downloaded module cannot be put to use. The admin still shows the module, with these reasons.
 */
final readonly class OfferProblem
{
    private function __construct(
        public OfferProblemKind $kind,
        public string $detail,
    ) {}

    public static function of(OfferProblemKind $kind, string $detail = ''): self
    {
        return new self($kind, $detail);
    }
}
