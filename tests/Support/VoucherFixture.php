<?php

namespace Tests\Support;

use App\Actions\Billing\ManageVoucher;
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
        $id = DB::table('voucher_reservations')->insertGetId(['voucher_id' => $voucher->id,
            'voucher_version_id' => $voucher->current_version_id, 'user_id' => $user->id,
            'order_id' => (string) Str::uuid(), 'state' => $state, 'resolved_at' => $state === 'held' ? null : now()]);
        if ($state === 'consumed') {
            DB::table('voucher_redemptions')->insert(['reservation_id' => $id]);
        }

        return $id;
    }
}
