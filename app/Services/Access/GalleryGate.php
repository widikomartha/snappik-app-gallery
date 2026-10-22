<?php

namespace App\Services\Access;

use App\Models\User;
use Carbon\CarbonInterface;

class GalleryGate
{
    public function __construct(private AccountPolicy $accounts) {}

    /** Internal policy inputs must be resolved from persisted module/capability/session state.
     * TASK-004/005/007 own that resolution; no public gallery route accepts this structure.
     */
    public function allows(
        User $owner, AccountAction $action, bool $capabilityValid,
        bool $published, bool $archived, ?CarbonInterface $deadline,
        bool $passwordRequired, bool $passwordSessionValid,
        int $gateVersion, ?int $sessionGateVersion, bool $selectionLocked = false,
    ): bool {
        if (! in_array($action, [AccountAction::ClientRead, AccountAction::ClientWrite, AccountAction::Download], true)) {
            return false;
        }

        return $capabilityValid && $published && ! $archived
            && ($deadline === null || $deadline->isFuture())
            && (! $passwordRequired || ($passwordSessionValid && $sessionGateVersion === $gateVersion))
            && ($action !== AccountAction::ClientWrite || ! $selectionLocked)
            && $this->accounts->allows($owner, $action);
    }
}
