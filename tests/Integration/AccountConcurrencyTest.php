<?php

namespace Tests\Integration;

use App\Actions\Auth\VerifyAccount;
use App\Actions\Projects\ManageProject;
use App\Models\AccountAccess;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function race(User $user, array $payload): array
    {
        $db = config('database.connections.pgsql');
        $env = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $db['database'],
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array',
        ];
        $processes = [];
        DB::beginTransaction();
        try {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/account-race-worker.php')], base_path(), $env, json_encode([...$payload, 'worker' => $i]), 30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $ready = $process->waitUntil(fn ($type, $output) => str_contains($output, 'ready'));
                $this->assertTrue($ready, 'Concurrent test worker started.');
            }
            // Both workers contend for the same real PostgreSQL row lock.
            DB::commit();
            $outputs = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'Concurrent worker completed without leaking diagnostics.');
                $outputs[] = trim(str_replace("ready\n", '', str_replace("\r", '', $process->getOutput())));
            }

            return $outputs;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_simultaneous_verification_creates_one_seven_day_trial(): void
    {
        $user = User::factory()->unverified()->create();
        $this->assertSame(['verified', 'verified'], $this->race($user, ['action' => 'verify', 'user_id' => $user->id]));
        $this->assertDatabaseCount('account_access', 1);
        $access = AccountAccess::findOrFail($user->id);
        $this->assertTrue($access->trial_started_at->addHours(168)->equalTo($access->trial_ends_at));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_simultaneous_reset_consumes_token_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $outputs = $this->race($user, ['action' => 'reset', 'credentials' => [
            'email' => $user->email, 'token' => $token, 'password' => 'ConcurrentPassword123',
            'password_confirmation' => 'ConcurrentPassword123',
        ]]);
        sort($outputs);
        $this->assertSame(['rejected', 'reset'], $outputs);
        $this->assertTrue(Hash::check('ConcurrentPassword123', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_concurrent_project_creates_cannot_take_the_same_final_trial_slot(): void
    {
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);
        $data = ['name' => 'Synthetic race project', 'allowance' => 2,
            'selection_source' => 'https://drive.google.com/drive/folders/synthetic-root'];
        for ($i = 0; $i < 2; $i++) {
            app(ManageProject::class)->create($user, $data);
        }
        $outputs = $this->race($user, ['action' => 'project-create', 'user_id' => $user->id, 'data' => $data]);
        sort($outputs);
        $this->assertSame(['created', 'rejected'], $outputs);
        $this->assertSame(3, Project::where('user_id', $user->id)->whereNull('archived_at')->count());
        $this->assertDatabaseCount('jobs', 3);
    }

    public function test_project_request_and_database_queue_dispatch_roll_back_together(): void
    {
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);
        try {
            DB::transaction(function () use ($user) {
                app(ManageProject::class)->create($user, ['name' => 'Synthetic rollback', 'allowance' => 2,
                    'selection_source' => 'https://drive.google.com/drive/folders/synthetic-root']);
                $this->assertDatabaseCount('jobs', 1);
                throw new \LogicException('Synthetic failure before outer commit.');
            });
            $this->fail('The synthetic transaction must roll back.');
        } catch (\LogicException) {
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('catalogue_revisions', 0);
            $this->assertDatabaseCount('jobs', 0);
        }
    }

    public function test_concurrent_restores_cannot_take_the_same_final_trial_slot(): void
    {
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);
        $action = app(ManageProject::class);
        $data = ['name' => 'Synthetic restore project', 'allowance' => 2,
            'selection_source' => 'https://drive.google.com/drive/folders/synthetic-root'];
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $project = $action->create($user, $data);
            $action->command($user, $project->id, $project->version, 'archive');
            $ids[] = $project->id;
        }
        for ($i = 0; $i < 2; $i++) {
            $action->create($user, $data);
        }
        $outputs = $this->race($user, ['action' => 'project-restore', 'user_id' => $user->id, 'project_ids' => $ids]);
        sort($outputs);
        $this->assertSame(['rejected', 'restored'], $outputs);
        $this->assertSame(3, Project::whereNull('archived_at')->count());
        $this->assertDatabaseCount('projects', 4);
    }
}
