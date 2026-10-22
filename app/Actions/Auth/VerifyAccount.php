<?php

namespace App\Actions\Auth;

use App\Models\AccountAccess;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class VerifyAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            // The user lock also serializes first creation of the unique access row.
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasVerifiedEmail()) {
                return;
            }
            $instant = now();
            $locked->forceFill(['email_verified_at' => $instant])->save();
            if ($locked->role === 'photographer') {
                $access = AccountAccess::whereKey($locked->id)->lockForUpdate()->first() ?? new AccountAccess;
                if ($access->trial_started_at === null) {
                    $end = $instant->copy()->addHours(168);
                    $access->forceFill([
                        'user_id' => $locked->id, 'trial_started_at' => $instant,
                        'trial_ends_at' => $end,
                        'access_ends_at' => $access->access_ends_at?->greaterThan($end) ? $access->access_ends_at : $end,
                    ])->save();
                }
            }
            DB::afterCommit(fn () => event(new Verified($locked)));
        });
    }
}
