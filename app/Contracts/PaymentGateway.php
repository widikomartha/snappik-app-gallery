<?php

namespace App\Contracts;

use App\Models\Order;
use App\Services\Billing\GatewayCheckout;
use App\Services\Billing\GatewayStatus;

interface PaymentGateway
{
    public function create(Order $order): GatewayCheckout;

    // missing means no Core payment yet; an existing Snap page may still exist.
    public function status(Order $order): GatewayStatus;
}
