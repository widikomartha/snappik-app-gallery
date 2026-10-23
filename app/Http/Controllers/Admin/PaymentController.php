<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\Checkout;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Access\AccountAction;
use App\Services\Billing\PaymentProcessing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function recheck(Request $request, string $order, Checkout $checkout, PaymentProcessing $payments)
    {
        abort_unless(array_diff(array_keys($request->all()), ['_token']) === [], 422);
        DB::transaction(function () use ($request, $order, $checkout, $payments) {
            $admin = $checkout->actor($request->user(), AccountAction::Admin);
            $payments->record(Order::findOrFail($order), 'admin', actor: $admin->id);
        });

        return response()->json(['queued' => true], 202);
    }
}
