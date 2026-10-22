<?php

namespace App\Services\Billing;

use App\Models\VoucherVersion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class VoucherQuote
{
    private function __construct(
        public int $voucherId, public int $versionId, public int $base,
        public int $discount, public int $payable, public string $currency = 'IDR',
    ) {}

    // Evaluates immutable terms only; checkout must lock/recheck the active root
    // and reserve capacity before accepting a new order in TASK-010.
    public static function calculate(VoucherVersion $version, int $base, int $channelMinimum, ?CarbonImmutable $at = null): self
    {
        $at ??= CarbonImmutable::now();
        if (! $version->enabled || $at->lt($version->starts_at) || $at->gte($version->ends_at)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher tidak aktif pada waktu ini.']);
        }
        if ($base <= 0 || $channelMinimum <= 0) {
            throw ValidationException::withMessages(['voucher' => 'Harga dan minimum kanal harus berupa IDR positif.']);
        }
        $value = $version->discount_value;
        // Split before multiplication to avoid overflow, even at PHP_INT_MAX.
        $discount = $version->discount_type === 'percentage'
            ? intdiv($base, 100) * $value + intdiv(($base % 100) * $value, 100)
            : $value;
        $payable = $base - $discount;
        if ($payable <= 0 || $payable < $channelMinimum) {
            throw ValidationException::withMessages(['voucher' => 'Voucher menghasilkan total di bawah minimum kanal pembayaran atau tidak positif.']);
        }

        return new self($version->voucher_id, $version->id, $base, $discount, $payable);
    }
}
