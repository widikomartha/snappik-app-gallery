<?php

namespace App\Actions\Billing;

use App\Models\AccountAccess;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\Billing\VoucherQuote;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Checkout
{
    public function __construct(private AccountPolicy $accounts) {}

    // Matches the user -> access ordering used by verification and suspension.
    public function actor(User $user, AccountAction $permission): User
    {
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        AccountAccess::whereKey($user->id)->lockForUpdate()->first();
        abort_unless($this->accounts->allows($user, $permission), 403);

        return $user;
    }

    public function configured(): bool
    {
        return (string) config('billing.merchant_id') !== '' && (string) config('billing.server_key') !== '' && config('billing.qris_minimum') > 0;
    }

    public function quote(User $user, ?string $code): array
    {
        return DB::transaction(function () use ($user, $code) {
            $user = $this->actor($user, AccountAction::Checkout);
            $terms = $this->terms($user, $code);

            return [...$terms, 'quote_token' => Crypt::encryptString(json_encode(['terms' => $terms, 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
        }, 3);
    }

    private function terms(User $user, ?string $code): array
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['checkout' => 'Checkout sandbox belum dikonfigurasi. Hubungi pengelola.']);
        }
        $plan = DB::table('plans')->where('code', 'pro')->lockForUpdate()->first();
        $version = DB::table('plan_versions')->find($plan->current_version_id);
        $base = (int) $version->price_idr;
        $minimum = (int) config('billing.qris_minimum');
        $code = Voucher::normalize($code ?? '');
        $voucher = null;
        $discount = 0;
        $payable = $base;
        if ($code !== '') {
            $voucher = Voucher::where('code', $code)->lockForUpdate()->first();
            if (! $voucher) {
                throw ValidationException::withMessages(['voucher' => 'Voucher tidak tersedia.']);
            }
            $terms = $voucher->currentVersion;
            $quote = VoucherQuote::calculate($terms, $base, $minimum);
            $discount = $quote->discount;
            $payable = $quote->payable;
            $occupied = DB::table('voucher_reservations as r')->where('r.voucher_id', $voucher->id)->where(function ($q) {
                $q->where('r.state', 'held')->orWhereExists(fn ($d) => $d->selectRaw('1')->from('voucher_redemptions as d')->whereColumn('d.reservation_id', 'r.id'));
            });
            if (($terms->total_limit !== null && (clone $occupied)->count() >= $terms->total_limit)
                || ($terms->per_user_limit !== null && (clone $occupied)->where('r.user_id', $user->id)->count() >= $terms->per_user_limit)) {
                throw ValidationException::withMessages(['voucher' => 'Batas penggunaan voucher telah terpakai atau masih direservasi.']);
            }
        }
        if ($payable <= 0 || $payable < $minimum) {
            throw ValidationException::withMessages(['checkout' => 'Total tidak memenuhi minimum QRIS.']);
        }

        return ['user_id' => $user->id, 'plan_version_id' => (int) $version->id, 'base_idr' => $base,
            'discount_idr' => $discount, 'payable_idr' => $payable, 'currency' => 'IDR', 'duration_days' => (int) $version->duration_days,
            'voucher_id' => $voucher?->id, 'voucher_version_id' => $voucher?->current_version_id, 'voucher_code' => $voucher?->code,
            'environment' => 'sandbox', 'merchant_id' => (string) config('billing.merchant_id'), 'channel_minimum' => $minimum];
    }

    public function reserve(User $user, string $key, #[\SensitiveParameter] string $token): Order
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Identitas checkout tidak valid.']);
        }
        try {
            $quote = json_decode(Crypt::decryptString($token), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['quote_token' => 'Kutipan harga tidak valid. Periksa harga kembali.']);
        }
        abort_unless(($quote['terms']['user_id'] ?? null) === $user->id, 403);
        $digest = hash('sha256', json_encode($quote['terms'], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $key, $quote, $digest) {
            $user = $this->actor($user, AccountAction::Checkout);
            $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->quote_digest, $digest)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Identitas checkout ini telah digunakan dengan ketentuan berbeda.']);
                }

                return $existing;
            }
            if (($quote['expires'] ?? 0) <= now()->timestamp) {
                throw ValidationException::withMessages(['quote_token' => 'Kutipan harga kedaluwarsa. Periksa harga kembali.']);
            }
            $terms = $this->terms($user, $quote['terms']['voucher_code']);
            if (! hash_equals($digest, hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR)))) {
                throw ValidationException::withMessages(['quote_token' => 'Harga atau ketentuan telah berubah. Periksa harga kembali sebelum membayar.']);
            }
            $id = (string) Str::uuid();
            $order = Order::create([...$terms, 'id' => $id, 'idempotency_key' => $key, 'quote_digest' => $digest,
                'provider_order_id' => 'sp-'.$id, 'checkout_expires_at' => now()->addHours(24)]);
            if ($order->voucher_id !== null) {
                DB::table('voucher_reservations')->insert(['order_id' => $id, 'user_id' => $user->id,
                    'voucher_id' => $order->voucher_id, 'voucher_version_id' => $order->voucher_version_id, 'state' => 'held']);
            }

            return $order;
        }, 3);
    }

    public function owned(User $user, string $id, AccountAction $permission = AccountAction::Billing): Order
    {
        $this->actor($user, $permission);

        return Order::whereKey($id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }
}
