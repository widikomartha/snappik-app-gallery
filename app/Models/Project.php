<?php

namespace App\Models;

use App\Contracts\PhotographerOwned;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model implements PhotographerOwned
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['share_token', 'share_digest'];

    protected function casts(): array
    {
        return ['share_token' => 'encrypted', 'archived_at' => 'immutable_datetime', 'selection_locked_at' => 'immutable_datetime',
            'selection_updated_at' => 'immutable_datetime'];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(GalleryModule::class);
    }
}
