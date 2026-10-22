<?php

namespace App\Services\Billing;

final readonly class GatewayCheckout
{
    public function __construct(public string $token, public string $url) {}
}
