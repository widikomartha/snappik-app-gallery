<?php

namespace App\Services\Access;

use App\Models\AccountAccess;
use App\Models\User;

class AccountPolicy
{
    public function state(User $user): string
    {
        $user = User::findOrFail($user->id);
        if ($user->suspended_at !== null) {
            return 'suspended';
        }
        if (! $user->hasVerifiedEmail()) {
            return 'unverified';
        }
        if ($user->role === 'admin') {
            return 'admin';
        }
        $access = AccountAccess::find($user->id);
        if ($access?->access_ends_at?->isFuture()) {
            return $access->has_paid_access ? 'pro' : 'trial';
        }

        return 'expired';
    }

    public function allows(User $user, AccountAction $action): bool
    {
        $state = $this->state($user);
        $user = User::findOrFail($user->id);
        if ($action === AccountAction::Admin) {
            return $state === 'admin';
        }
        if ($user->role !== 'photographer' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        return match ($action) {
            AccountAction::Billing => true,
            AccountAction::Read, AccountAction::Checkout, AccountAction::ClientRead, AccountAction::Download => $state !== 'suspended',
            AccountAction::Mutate, AccountAction::ClientWrite => in_array($state, ['trial', 'pro'], true),
            default => false,
        };
    }
}
