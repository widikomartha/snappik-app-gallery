<?php

namespace Tests\Integration;

use App\Models\AccountAccess;
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
                $process = new Process([PHP_BINARY, base_path('tests/Support/account-race-worker.php')], base_path(), $env, json_encode($payload), 30);
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
}
