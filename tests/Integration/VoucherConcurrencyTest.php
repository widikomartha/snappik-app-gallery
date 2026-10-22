<?php

namespace Tests\Integration;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\VoucherFixture as F;
use Tests\TestCase;

class VoucherConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function worker(User $admin, array $data, ?int $id = null): Process
    {
        $db = config('database.connections.pgsql');
        $process = new Process([PHP_BINARY, base_path('tests/Support/voucher-race-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $db['database'],
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array',
        ], json_encode(['admin_id' => $admin->id, 'data' => $data, 'voucher_id' => $id]), 30);
        $process->start();
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready:')));

        return $process;
    }

    private function workerResult(Process $process): string
    {
        $this->assertSame(0, $process->wait(), 'Worker must finish without leaking diagnostics.');

        return trim(preg_replace('/ready:\d+\s*/', '', $process->getOutput()));
    }

    public function test_concurrent_normalized_code_creation_has_one_root_version_and_audit(): void
    {
        $admins = [F::admin(), F::admin()];
        $processes = [];
        DB::beginTransaction();
        try {
            User::whereIn('id', array_map(fn ($u) => $u->id, $admins))->orderBy('id')->lockForUpdate()->get();
            foreach ($admins as $i => $admin) {
                $processes[] = $this->worker($admin, F::terms(['code' => $i === 0 ? ' race ' : 'RACE']));
            }
            DB::commit();
            $results = array_map(fn ($process) => $this->workerResult($process), $processes);
            sort($results);
            $this->assertSame(['rejected:code', 'saved'], $results);
            $this->assertDatabaseCount('vouchers', 1);
            $this->assertDatabaseCount('voucher_versions', 1);
            $this->assertDatabaseCount('audit_events', 1);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    public function test_competing_editors_cannot_overwrite_the_same_version(): void
    {
        $admins = [F::admin(), F::admin()];
        $voucher = F::create($admins[0]);
        $processes = [];
        DB::beginTransaction();
        try {
            Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            foreach ($admins as $i => $admin) {
                $data = F::terms(['expected_version' => 1, 'discount_value' => 20 + $i]);
                unset($data['code']);
                $processes[] = $this->worker($admin, $data, $voucher->id);
            }
            DB::commit();
            $results = array_map(fn ($process) => $this->workerResult($process), $processes);
            sort($results);
            $this->assertSame(['rejected:expected_version', 'saved'], $results);
            $this->assertSame(2, $voucher->fresh()->currentVersion->number);
            $this->assertDatabaseCount('voucher_versions', 2);
            $this->assertDatabaseCount('audit_events', 2);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    public function test_waiting_limit_edit_rechecks_a_hold_committed_under_the_root_lock(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $buyer = User::factory()->create();
        F::reservation($voucher, $buyer);
        $process = null;
        DB::beginTransaction();
        try {
            Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $data = F::terms(['expected_version' => 1, 'total_limit' => 1]);
            unset($data['code']);
            $process = $this->worker($admin, $data, $voucher->id);
            preg_match('/ready:(\d+)/', $process->getOutput(), $match);
            $waiting = false;
            for ($i = 0; $i < 200; $i++) {
                if (DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$match[1]])?->wait_event_type === 'Lock') {
                    $waiting = true;
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($waiting, 'Admin writer is actually waiting on the root lock.');
            F::reservation($voucher, $buyer);
            DB::commit();
            $this->assertSame('rejected:total_limit', $this->workerResult($process));
            $this->assertDatabaseCount('voucher_versions', 1);
            $this->assertDatabaseCount('voucher_reservations', 2);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            $process?->stop();
        }
    }
}
