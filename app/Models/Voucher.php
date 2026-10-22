<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $guarded = ['id'];

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(VoucherVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VoucherVersion::class);
    }
}
