<?php

declare(strict_types=1);

namespace Yef\Domain\Text;

/**
 * The texts of one source (the core or one module) in the language of the site.
 *
 * It is for what people read in the admin. The public site is not translated: its content has one language.
 */
interface Texts
{
    /**
     * @param array<string, string|int> $parameters Values for the {placeholders} of the text.
     *
     * @return string The text in the language of the site; the English text when there is none in that language;
     *                the key itself when there is no text at all, so a missing text is easy to see.
     */
    public function t(string $key, array $parameters = []): string;
}
