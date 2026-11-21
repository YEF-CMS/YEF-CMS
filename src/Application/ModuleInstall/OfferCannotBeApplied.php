<?php

declare(strict_types=1);

namespace Yef\Application\ModuleInstall;

use DomainException;

use function count;
use function sprintf;

/**
 * The module that waits cannot be installed or updated, for the reasons that `ModuleOffer` lists, or because
 * the confirmation was meant for the other action.
 */
final class OfferCannotBeApplied extends DomainException
{
    /**
     * @param list<OfferProblem> $problems
     */
    private function __construct(
        public readonly array $problems,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<OfferProblem> $problems
     */
    public static function because(array $problems): self
    {
        return new self($problems, sprintf('The module cannot be applied: %d problem(s).', count($problems)));
    }

    public static function forTheOtherAction(): self
    {
        return new self([], 'The downloaded module is meant for the other action (install or update).');
    }
}
