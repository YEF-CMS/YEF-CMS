<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;

/**
 * What was downloaded is not a usable module: there is no manifest, or it is wrong.
 * The message says what is wrong with the manifest and is written for the author of the module.
 */
final class InvalidModule extends DomainException {}
