<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ArchiveJob extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['object_key'];

    protected function casts(): array
    {
        return ['lease_until' => 'immutable_datetime', 'ready_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
