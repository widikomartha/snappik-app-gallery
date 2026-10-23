<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetAccountSuspension
{
    public function __construct(private AccountPolicy $accounts) {}

    public function handle(User $admin, User $target, bool $suspended, string $reason, ?int $expectedVersion = null): void
    {
        abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 500, 422);
        DB::transaction(function () use ($admin, $target, $suspended, $reason, $expectedVersion) {
            // Deterministic locking also avoids two administrators locking each other.
            $users = User::whereIn('id', [$admin->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($this->accounts->allows($users[$admin->id], AccountAction::Admin), 403);
            $target = $users[$target->id];
            abort_unless($target->role === 'photographer', 403);
            if ($expectedVersion !== null && $target->suspension_version !== $expectedVersion) {
                throw ValidationException::withMessages(['operation' => 'Versi telah berubah. Muat ulang sebelum melanjutkan.']);
            }
            if (($target->suspended_at !== null) === $suspended) {
                return;
            }
            $before = $target->suspension_version;
            $target->forceFill(['suspended_at' => $suspended ? now() : null, 'suspension_version' => $before + 1])->save();
            DB::table('audit_events')->insert([
                'actor_id' => $admin->id, 'target_user_id' => $target->id,
                'action' => $suspended ? 'account.suspended' : 'account.reactivated', 'reason' => trim($reason),
                'subject_type' => 'account', 'subject_id' => (string) $target->id,
                'before_reference' => ($suspended ? 'active:' : 'suspended:').$before,
                'after_reference' => ($suspended ? 'suspended:' : 'active:').($before + 1),
            ]);
        });
    }
}
