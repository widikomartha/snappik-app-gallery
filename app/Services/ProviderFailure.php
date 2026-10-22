<?php

namespace App\Services;

use RuntimeException;

final class ProviderFailure extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $providerStatus = null, public readonly bool $retryable = false)
    {
        // Never chain HTTP/SDK exceptions: their URLs, headers and bodies can contain secrets.
        parent::__construct($reason.($providerStatus === null ? '' : " (HTTP {$providerStatus})"));
    }
}
