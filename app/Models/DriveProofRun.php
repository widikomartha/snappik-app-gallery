<?php

namespace App\Models;

use App\Contracts\PhotographerOwned;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriveProofRun extends Model implements PhotographerOwned
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['root', 'object_key'];

    protected function casts(): array
    {
        return ['root' => 'encrypted:array', 'ready_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(DriveProofNode::class, 'run_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
