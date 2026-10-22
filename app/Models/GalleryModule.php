<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryModule extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['password_hash', 'source'];

    protected function casts(): array
    {
        return ['source' => 'encrypted:array', 'deadline' => 'immutable_datetime', 'published' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
