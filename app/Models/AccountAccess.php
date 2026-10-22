<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountAccess extends Model
{
    protected $table = 'account_access';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'access_ends_at' => 'immutable_datetime',
            'has_paid_access' => 'boolean',
        ];
    }
}
