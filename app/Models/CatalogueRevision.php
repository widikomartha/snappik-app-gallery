<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogueRevision extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['source'];

    protected function casts(): array
    {
        return ['source' => 'encrypted:array', 'completed_at' => 'immutable_datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CatalogueEntry::class, 'revision_id');
    }
}
