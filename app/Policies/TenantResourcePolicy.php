<?php

namespace App\Policies;

use App\Contracts\PhotographerOwned;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class TenantResourcePolicy
{
    public function __construct(private AccountPolicy $accounts) {}

    public function access(User $actor, Model&PhotographerOwned $resource, AccountAction $action): Response
    {
        // Re-read both identities; stale sessions, in-memory owner changes and admin
        // status cannot confer photographer rights to another tenant's resources.
        $persisted = $resource->fresh();
        $owner = $persisted?->photographer()->first();

        return $action !== AccountAction::Admin && $owner && $owner->id === $actor->id && $this->accounts->allows($actor, $action)
            ? Response::allow() : Response::denyAsNotFound();
    }
}
