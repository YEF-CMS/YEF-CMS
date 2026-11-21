<?php

declare(strict_types=1);

namespace Yef\Platform\Http;

/**
 * What a transfer has seen so far, shared by the callbacks that curl calls while it works.
 */
final class TransferState
{
    public int $status = 0;

    /** @var array<string, string> */
    public array $headers = [];

    public int $bytes = 0;

    public bool $tooLarge = false;
}
