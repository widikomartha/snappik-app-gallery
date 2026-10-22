<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\AccountLink;

class AccountMail
{
    public function send(User $user, #[\SensitiveParameter] ?string $resetToken = null): void
    {
        $mailer = (string) config('auth.mailer');
        // Account links must never reach the log transport, including via failover.
        if (! in_array(config("mail.mailers.{$mailer}.transport"), ['array', 'smtp', 'sendmail'], true)) {
            throw new \RuntimeException('Account mail transport is unavailable.');
        }
        $user->notify(new AccountLink($mailer, $resetToken));
    }
}
