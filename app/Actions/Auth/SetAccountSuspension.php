<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Illuminate\Support\Facades\DB;

class SetAccountSuspension
{
    public function __construct(private AccountPolicy $accounts) {}

    public function handle(User $admin, User $target, bool $suspended, string $reason): void
    {
        abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 500, 422);
        DB::transaction(function () use ($admin, $target, $suspended, $reason) {
            // Deterministic locking also avoids two administrators locking each other.
            $users = User::whereIn('id', [$admin->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($this->accounts->allows($users[$admin->id], AccountAction::Admin), 403);
            $target = $users[$target->id];
            abort_unless($target->role === 'photographer', 403);
            if (($target->suspended_at !== null) === $suspended) {
                return;
            }
            $target->forceFill(['suspended_at' => $suspended ? now() : null])->save();
            DB::table('audit_events')->insert([
                'actor_id' => $admin->id, 'target_user_id' => $target->id,
                'action' => $suspended ? 'account.suspended' : 'account.reactivated', 'reason' => trim($reason),
            ]);
        });
    }
}
