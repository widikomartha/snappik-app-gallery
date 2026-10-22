<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetAccountPassword
{
    public function handle(#[\SensitiveParameter] array $credentials): string
    {
        return DB::transaction(function () use ($credentials) {
            // Serialize reset validation and consumption, including concurrent replay.
            User::where('email', $credentials['email'])->lockForUpdate()->first();

            return Password::reset($credentials, function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::afterCommit(fn () => event(new PasswordReset($user)));
            });
        });
    }
}
