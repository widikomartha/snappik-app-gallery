<?php

namespace Tests\Support;

use App\Actions\Billing\ManageVoucher;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VoucherFixture
{
    public static function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public static function terms(array $overrides = []): array
    {
        return [...['code' => 'SYNTHETIC10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i:sP'), 'ends_at' => now()->addDays(30)->format('Y-m-d\TH:i:sP'),
            'total_limit' => null, 'per_user_limit' => null, 'enabled' => true, 'reason' => 'Synthetic voucher verification'], ...$overrides];
    }

    public static function create(User $admin, array $overrides = []): Voucher
    {
        return app(ManageVoucher::class)->save($admin, self::terms($overrides));
    }

    // Synthetic accounting ONLY: production ledger writes belong to the verified
    // checkout/payment Actions in TASK-010/011, never an admin grant endpoint.
    public static function reservation(Voucher $voucher, User $user, string $state = 'held'): int
    {
        $orderId = (string) Str::uuid();
        $plan = DB::table('plans')->where('code', 'pro')->value('current_version_id');
        Order::create(['id' => $orderId, 'user_id' => $user->id, 'idempotency_key' => (string) Str::uuid(),
            'quote_digest' => hash('sha256', $orderId), 'plan_version_id' => $plan,
            'base_idr' => 29000, 'discount_idr' => 2900, 'payable_idr' => 26100, 'duration_days' => 30,
            'voucher_id' => $voucher->id, 'voucher_version_id' => $voucher->current_version_id, 'voucher_code' => $voucher->code,
            'provider_order_id' => 'sp-'.$orderId, 'merchant_id' => 'synthetic-merchant', 'channel_minimum' => 1, 'checkout_expires_at' => now()->addHours(24)]);
        $id = DB::table('voucher_reservations')->insertGetId(['voucher_id' => $voucher->id,
            'voucher_version_id' => $voucher->current_version_id, 'user_id' => $user->id,
            'order_id' => $orderId, 'state' => $state, 'resolved_at' => $state === 'held' ? null : now()]);
        if ($state === 'consumed') {
            DB::table('voucher_redemptions')->insert(['reservation_id' => $id]);
        }

        return $id;
    }
}
