<?php

namespace App\Models;

use App\Contracts\PhotographerOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model implements PhotographerOwned
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['snap_token', 'checkout_url', 'quote_digest', 'idempotency_key'];

    protected function casts(): array
    {
        return ['base_idr' => 'integer', 'discount_idr' => 'integer', 'payable_idr' => 'integer',
            'duration_days' => 'integer', 'channel_minimum' => 'integer', 'plan_version_id' => 'integer',
            'voucher_id' => 'integer', 'voucher_version_id' => 'integer', 'snap_token' => 'encrypted', 'checkout_url' => 'encrypted',
            'checkout_expires_at' => 'immutable_datetime', 'last_checked_at' => 'immutable_datetime'];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
