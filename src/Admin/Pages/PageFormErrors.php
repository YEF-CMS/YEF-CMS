<?php

declare(strict_types=1);

namespace Yef\Admin\Pages;

use InvalidArgumentException;
use Throwable;
use Yef\Domain\ContentType\Exception\InvalidPayload;
use Yef\Domain\Page\Exception\InvalidPageDetails;
use Yef\Domain\Page\Exception\PathAlreadyTaken;

/**
 * Turns what went wrong with a submitted page form into messages for the fields they belong to.
 *
 * Each message is either a translation key with its parameters, or plain text written by a content type
 * (a module's own message about its own fields).
 */
final readonly class PageFormErrors
{
    /**
     * @return array<string, array{key: string|null, params: array<string, int>, text: string|null}>|null
     *                                                                                                    Messages by field ("title", "path", "payload", "general"), or null when the failure is not a mistake
     *                                                                                                    of the person filling in the form and must not be hidden.
     */
    public function of(Throwable $failure): ?array
    {
        if ($failure instanceof InvalidPageDetails) {
            return [$failure->field => $this->key('pages.error.' . $failure->field . '.' . $failure->rule, ['limit' => $failure->limit])];
        }

        if ($failure instanceof PathAlreadyTaken) {
            return ['path' => $this->key('pages.error.path.taken')];
        }

        if ($failure instanceof InvalidPayload) {
            return ['payload' => ['key' => null, 'params' => [], 'text' => $failure->getMessage()]];
        }

        if ($failure instanceof InvalidArgumentException) {
            return ['general' => $this->key('pages.error.invalid')];
        }

        return null;
    }

    /**
     * @param array<string, int> $params
     *
     * @return array{key: string, params: array<string, int>, text: null}
     */
    private function key(string $key, array $params = []): array
    {
        return ['key' => $key, 'params' => $params, 'text' => null];
    }
}
