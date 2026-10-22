<?php

namespace App\Http\Controllers\Photographer;

use App\Actions\Billing\Checkout;
use App\Actions\Billing\ResumeCheckout;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index(Request $request, Checkout $checkout, AccountPolicy $accounts)
    {
        return DB::transaction(function () use ($request, $checkout, $accounts) {
            $user = $checkout->actor($request->user(), AccountAction::Billing);
            $version = DB::table('plans as p')->join('plan_versions as v', 'v.id', '=', 'p.current_version_id')->where('p.code', 'pro')->first();

            return Inertia::render('photographer/billing', ['ownerId' => $user->id,
                'canCheckout' => $accounts->allows($user, AccountAction::Checkout) && $checkout->configured(),
                'configured' => $checkout->configured(), 'base' => (int) $version->price_idr,
                'orders' => Order::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(20)
                    ->through(fn ($order) => $this->snapshot($order, false))]);
        });
    }

    private function validateInput(Request $request, array $rules): array
    {
        // Only the signed quote and operation key may define a purchase.
        foreach (array_diff(array_keys($request->all()), [...array_keys($rules), '_token']) as $field) {
            $rules[$field] = 'prohibited';
        }

        return $request->validate($rules);
    }

    public function quote(Request $request, Checkout $checkout)
    {
        $data = $this->validateInput($request, ['voucher' => ['nullable', 'string', 'max:64']]);
        $quote = $checkout->quote($request->user(), $data['voucher'] ?? null);

        return response()->json(['quote' => array_intersect_key($quote, array_flip(['base_idr', 'discount_idr', 'payable_idr', 'currency', 'duration_days', 'voucher_code', 'quote_token']))]);
    }

    public function store(Request $request, Checkout $checkout, ResumeCheckout $resume)
    {
        $data = $this->validateInput($request, ['idempotency_key' => ['required', 'uuid'], 'quote_token' => ['required', 'string', 'max:10000']]);
        $order = $checkout->reserve($request->user(), $data['idempotency_key'], $data['quote_token']);
        $resume->handle($request->user(), $order->id);

        return response()->json(['id' => $order->id]);
    }

    public function show(Request $request, string $order, Checkout $checkout, AccountPolicy $accounts)
    {
        return DB::transaction(function () use ($request, $order, $checkout, $accounts) {
            $record = $checkout->owned($request->user(), $order);

            return Inertia::render('photographer/order', ['order' => $this->snapshot($record, $accounts->allows($request->user(), AccountAction::Checkout)),
                'canCheckout' => $accounts->allows($request->user(), AccountAction::Checkout)]);
        });
    }

    public function resume(Request $request, string $order, ResumeCheckout $resume)
    {
        $this->validateInput($request, []);
        $resume->handle($request->user(), $order);

        return redirect('/billing/orders/'.$order, 303);
    }

    public function open(Request $request, string $order, Checkout $checkout)
    {
        $url = DB::transaction(function () use ($request, $order, $checkout) {
            $record = $checkout->owned($request->user(), $order, AccountAction::Checkout);
            abort_unless($record->state === 'pending' && $record->checkout_expires_at->isFuture() && $record->checkout_url !== null, 409);
            abort_unless($record->merchant_id === (string) config('billing.merchant_id'), 409);
            $url = $record->checkout_url;
            abort_unless($url === 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$record->snap_token, 409);

            return $url;
        });

        return redirect()->away($url);
    }

    private function snapshot(Order $order, bool $allowed): array
    {
        return [...$order->only(['id', 'state', 'provider_state', 'base_idr', 'discount_idr', 'payable_idr', 'currency', 'duration_days', 'voucher_code', 'created_at', 'checkout_expires_at', 'last_checked_at']),
            'canOpen' => $allowed && $order->state === 'pending' && $order->checkout_url !== null && $order->checkout_expires_at->isFuture(),
            'windowElapsed' => $order->checkout_expires_at->lte(now())];
    }
}
