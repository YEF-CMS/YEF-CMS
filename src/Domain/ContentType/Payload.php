<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * Type-specific data of a page (the HTML of a text page, the photos of a gallery).
 *
 * The core never looks inside: only the content type that created a payload reads and writes it.
 */
interface Payload {}
