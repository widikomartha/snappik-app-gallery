<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogueEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['metadata', 'page_token'];

    protected function casts(): array
    {
        return ['metadata' => 'encrypted:array', 'page_token' => 'encrypted', 'listed' => 'boolean'];
    }
}
