<?php

namespace App\Services\Billing;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class VoucherUsage
{
    public function reservations(int $voucherId): Builder
    {
        return DB::table('voucher_reservations as r')->where('r.voucher_id', $voucherId);
    }

    public function redemptions(int $voucherId): Builder
    {
        return $this->reservations($voucherId)->join('voucher_redemptions as d', 'd.reservation_id', '=', 'r.id');
    }

    public function summary(int $voucherId): array
    {
        return ['held' => $this->reservations($voucherId)->where('r.state', 'held')->count(),
            'used' => $this->redemptions($voucherId)->count(),
            'released' => $this->reservations($voucherId)->where('r.state', 'released')->count()];
    }

    // Caller must hold the voucher root lock, including future checkout/payment writers.
    public function capacity(int $voucherId): array
    {
        $occupied = $this->reservations($voucherId)->where(function (Builder $query) {
            $query->where('r.state', 'held')->orWhereExists(fn (Builder $q) => $q->selectRaw('1')->from('voucher_redemptions as d')->whereColumn('d.reservation_id', 'r.id'));
        });
        $maxUser = (clone $occupied)->selectRaw('count(*) as occupied')->groupBy('r.user_id')->orderByDesc('occupied')->value('occupied');

        return ['total' => $occupied->count(), 'per_user' => (int) ($maxUser ?? 0)];
    }
}
