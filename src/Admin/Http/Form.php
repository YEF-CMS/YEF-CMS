<?php

declare(strict_types=1);

namespace Yef\Admin\Http;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * The fields of a submitted form. A field that is missing or is not plain text reads as empty text.
 */
final readonly class Form
{
    /**
     * @param array<array-key, mixed> $fields
     */
    private function __construct(private array $fields) {}

    public static function of(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();

        return new self(is_array($body) ? $body : []);
    }

    /**
     * The fields grouped under a name (`fields[body]` is the field "body" of the section "fields").
     */
    public function section(string $name): self
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $section = $this->fields[$name] ?? null;

        return new self(is_array($section) ? $section : []);
    }

    public function text(string $field): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $value = $this->fields[$field] ?? null;

        return is_string($value) ? $value : '';
    }
}
