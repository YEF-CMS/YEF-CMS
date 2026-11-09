<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

use InvalidArgumentException;

use function preg_match;
use function sprintf;
use function trim;

/**
 * One input of the form a content type shows for its pages.
 */
final readonly class FormField
{
    /**
     * @param string $name Key of the value in the data the form submits and in `PayloadFactory::fromInput()`.
     * @param string $label What the user reads next to the input.
     *
     * @throws InvalidArgumentException When the name is not a lowercase identifier or the label is empty.
     */
    public function __construct(
        public string $name,
        public FieldKind $kind,
        public string $label,
        public bool $required = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid form field name.', $name));
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('A form field needs a label.');
        }
    }
}
