<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'enqueued_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
