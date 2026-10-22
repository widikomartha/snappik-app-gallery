<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionAdmin extends Command
{
    protected $signature = 'snappik:provision-admin {email : Existing verified owner account email}';

    protected $description = 'Provision the owner role on an existing verified account (server operator only)';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        return DB::transaction(function () use ($email) {
            $owner = User::where('email', $email)->lockForUpdate()->first();
            if (! $owner || ! $owner->hasVerifiedEmail() || $owner->suspended_at !== null) {
                $this->error('A verified, unsuspended owner account is required. Register and verify it first.');

                return self::FAILURE;
            }
            if ($owner->role !== 'admin') {
                $owner->forceFill(['role' => 'admin'])->save();
                DB::table('audit_events')->insert([
                    'actor_id' => null, 'target_user_id' => $owner->id,
                    'action' => 'admin.provisioned', 'reason' => 'Server operator provisioned the owner role.',
                ]);
                DB::table('sessions')->where('user_id', $owner->id)->delete();
            }
            $this->info('Owner role provisioned. Sign in again.');

            return self::SUCCESS;
        });
    }
}
