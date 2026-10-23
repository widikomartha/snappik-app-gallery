<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Integrations\Midtrans\SandboxGateway;
use App\Models\Order;
use App\Services\Billing\PaymentProcessing;
use Illuminate\Http\Request;

class MidtransController extends Controller
{
    public function __invoke(Request $request, SandboxGateway $gateway, PaymentProcessing $payments)
    {
        abort_unless($request->isJson() && strlen($request->getContent()) <= 32768, 400);
        $data = $request->json()->all();
        $identity = $data['order_id'] ?? null;
        abort_unless(is_string($identity) && preg_match('/\Asp-[0-9a-f-]{36}\z/', $identity), 400);
        $order = Order::where('provider_order_id', $identity)->first();
        abort_unless($order && $gateway->verifyNotification($order, $data), 403);
        // Persist only a canonical digest. Extra fields, signatures and personal data never enter the queue/history.
        $digest = hash('sha256', json_encode(array_map(fn ($key) => $data[$key], [
            'order_id', 'merchant_id', 'gross_amount', 'currency', 'payment_type', 'status_code', 'transaction_status',
        ]), JSON_THROW_ON_ERROR));
        try {
            $payments->record($order, 'callback', $digest);
        } catch (\Throwable) {
            // Acknowledge only after the durable receipt and its outbox entry commit.
            return response()->json(['message' => 'Payment receipt unavailable. Retry later.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
