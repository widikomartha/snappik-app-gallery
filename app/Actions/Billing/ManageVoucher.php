<?php

namespace App\Actions\Billing;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherVersion;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\Billing\VoucherUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageVoucher
{
    public function __construct(private AccountPolicy $accounts, private VoucherUsage $usage) {}

    public function save(User $admin, array $data, ?int $voucherId = null): Voucher
    {
        try {
            return DB::transaction(function () use ($admin, $data, $voucherId) {
                $admin = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->accounts->allows($admin, AccountAction::Admin), 403);
                if ($voucherId !== null) {
                    $voucher = Voucher::whereKey($voucherId)->lockForUpdate()->firstOrFail();
                    $previous = $voucher->currentVersion;
                    if ($previous->number !== (int) $data['expected_version']) {
                        throw ValidationException::withMessages(['expected_version' => 'Versi telah berubah. Muat versi terbaru sebelum menyimpan kembali.']);
                    }
                    $capacity = $this->usage->capacity($voucher->id);
                    foreach (['total_limit' => 'total', 'per_user_limit' => 'per_user'] as $field => $key) {
                        if (isset($data[$field]) && (int) $data[$field] < $capacity[$key]) {
                            throw ValidationException::withMessages([$field => 'Batas tidak boleh kurang dari penggunaan berhasil ditambah reservasi yang masih ditahan ('.$capacity[$key].').']);
                        }
                    }
                } else {
                    $previous = null;
                    $voucher = Voucher::create(['code' => Voucher::normalize($data['code'])]);
                }
                $terms = array_intersect_key($data, array_flip(['discount_type', 'discount_value', 'starts_at', 'ends_at', 'total_limit', 'per_user_limit', 'enabled']));
                // Eloquent formats timestamps without an offset for PostgreSQL.
                // Normalize first so WITA submissions preserve their exact instant.
                foreach (['starts_at', 'ends_at'] as $field) {
                    $terms[$field] = CarbonImmutable::parse($terms[$field])->utc();
                }
                $version = VoucherVersion::create([...$terms, 'voucher_id' => $voucher->id, 'number' => ($previous?->number ?? 0) + 1, 'created_by' => $admin->id]);
                $voucher->update(['current_version_id' => $version->id]);
                $action = ! $previous ? 'voucher.created' : ($previous->enabled && ! $version->enabled ? 'voucher.deactivated' : (! $previous->enabled && $version->enabled ? 'voucher.reactivated' : 'voucher.updated'));
                DB::table('audit_events')->insert(['actor_id' => $admin->id, 'target_user_id' => null,
                    'voucher_id' => $voucher->id, 'voucher_version_id' => $version->id, 'action' => $action, 'reason' => trim($data['reason'])]);

                return $voucher;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            if ($voucherId === null) {
                throw ValidationException::withMessages(['code' => 'Kode voucher sudah digunakan. Gunakan kode lain.']);
            }
            throw $e;
        }
    }
}
