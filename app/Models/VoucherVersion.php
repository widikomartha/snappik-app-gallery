<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'discount_value' => 'integer', 'total_limit' => 'integer',
            'per_user_limit' => 'integer', 'enabled' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
