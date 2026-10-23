<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

final readonly class GatewayStatus
{
    public function __construct(
        public string $state,
        public ?string $transactionId = null,
        public ?CarbonImmutable $paidAt = null,
        public ?string $digest = null,
    ) {}
}
