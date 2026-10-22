<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface PhotographerOwned
{
    // The relation must follow persisted ownership, never an HTTP owner parameter.
    public function photographer(): BelongsTo;
}
