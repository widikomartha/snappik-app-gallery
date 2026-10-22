<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'encrypted:array'];
    }
}
