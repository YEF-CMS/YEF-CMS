<?php

declare(strict_types=1);

namespace Yef\Domain\Module\Install\Exception;

use DomainException;

/**
 * The name of a release is not a semantic version, so it cannot be compared with another one.
 */
final class InvalidReleaseVersion extends DomainException {}
