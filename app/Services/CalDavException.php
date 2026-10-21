<?php

declare(strict_types=1);

namespace App\Services;

/** A CalDAV request that failed: status 0 = no connection; retryAfter (seconds) when the server is throttling. */
final class CalDavException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $status);
    }

    public function isThrottled(): bool
    {
        return in_array($this->status, [429, 503], true);
    }

    public function isConflict(): bool
    {
        return in_array($this->status, [409, 412], true);
    }
}
