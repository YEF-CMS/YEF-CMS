<?php

declare(strict_types=1);

namespace Yef\Domain\ContentType;

/**
 * What kind of input a field of a page form needs. The admin decides how each kind looks.
 */
enum FieldKind: string
{
    /** A single line of text. */
    case Text = 'text';

    /** Several lines of plain text. */
    case LongText = 'long_text';

    /** Formatted text (HTML) written in an editor. */
    case RichText = 'rich_text';
}
