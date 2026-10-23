<?php

namespace Tests\Integration;

use App\Actions\Selections\ManageSelection;
use App\Models\Order;
use App\Models\Project;
use App\Services\Archives\DeliveryArchives;
use App\Services\Billing\PaymentProcessing;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\DeliveryFixture;
use Tests\Support\PaymentFixture as P;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations, DeliveryFixture;

    public function test_encrypted_dump_restores_into_an_isolated_database_with_coherent_choices_and_money(): void
    {
        if (getenv('SNAPPIK_BACKUP_PROOF') !== '1') {
            $this->markTestSkipped('Opt in with SNAPPIK_BACKUP_PROOF=1 and SNAPPIK_PG_BIN; needs PostgreSQL tools and CREATEDB on the test role.');
        }
        F::configure();
        $queue = Queue::getFacadeRoot();
        Queue::fake();
        $project = $this->deliveryProject();
        $path = '/g/'.$project->share_token.'/selection/';
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $choice = ['operation_id' => (string) Str::uuid(), 'epoch' => $project->selection_epoch,
            'version' => $project->selection_version, 'revision' => $module->published_revision_id, 'file_id' => 'selection-one', 'selected' => true];
        $this->postJson($path.'choices', $choice)->assertOk();
        $project->refresh();
        $confirmation = [...$choice, 'operation_id' => (string) Str::uuid(), 'version' => $project->selection_version];
        unset($confirmation['file_id'], $confirmation['selected']);
        $this->postJson($path.'confirm', $confirmation)->assertOk();
        $project->refresh();
        app(ManageSelection::class)->reopen($project->photographer, $project->id, ['epoch' => $project->selection_epoch, 'version' => $project->selection_version]);
        $voucher = V::create(V::admin());
        $paid = F::reserve($project->photographer, $voucher->code);
        P::gateway([P::response($paid)]);
        $event = app(PaymentProcessing::class)->record($paid, 'scheduled', queue: false);
        app(PaymentProcessing::class)->process($event->id);
        $held = F::reserve($project->photographer, $voucher->code);
        $released = F::reserve($project->photographer, $voucher->code);
        P::gateway([P::response($released, ['transaction_status' => 'expire', 'status_code' => '202'])]);
        $failedEvent = app(PaymentProcessing::class)->record($released, 'scheduled', queue: false);
        app(PaymentProcessing::class)->process($failedEvent->id);
        // Retain actual transactional outbox rows in the snapshot.
        Queue::swap($queue);
        app(PaymentProcessing::class)->record($held, 'scheduled');
        $request = Request::create('/synthetic', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $archives = app(DeliveryArchives::class);
        $archives->authorized($request, $project->share_token, fn ($delivery) => $archives->request($delivery));

        $db = config('database.connections.pgsql');
        $target = 'snappik_restore_'.bin2hex(random_bytes(8)).'_test';
        $this->assertMatchesRegularExpression('/\Asnappik_restore_[a-f0-9]{16}_test\z/', $target);
        $this->assertNotSame($db['database'], $target);
        $dump = tempnam(sys_get_temp_dir(), 'snappik-dump-');
        $encrypted = $dump.'.enc';
        $created = false;
        $env = ['PGHOST' => $db['host'], 'PGPORT' => (string) $db['port'], 'PGUSER' => $db['username'],
            'PGPASSWORD' => $db['password'] ?? '', 'PGDATABASE' => $db['database'], 'PGSSLMODE' => $db['sslmode'] ?? 'prefer'];
        $binary = fn ($tool) => rtrim((string) getenv('SNAPPIK_PG_BIN'), '/\\').DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $original = config('database.default');
        try {
            $before = $this->snapshot('pgsql');
            $process = new Process([$binary('pg_dump'), '--format=custom', '--no-owner', '--no-acl', '--file='.$dump], base_path(), $env, timeout: 60);
            $this->assertSame(0, $process->run(), 'pg_dump must succeed using the test connection.');
            $dumpHash = hash_file('sha256', $dump);
            // This bounded fixture proves authenticated encryption; production backup streaming,
            // independent key custody, off-host transport and retention are TASK-015 inputs.
            $key = random_bytes(32);
            $nonce = random_bytes(12);
            $ciphertext = openssl_encrypt(file_get_contents($dump), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'snappik-backup-v1');
            $this->assertIsString($ciphertext);
            file_put_contents($encrypted, $nonce.$tag.$ciphertext);
            unlink($dump);
            $this->assertFileDoesNotExist($dump);
            $bytes = file_get_contents($encrypted);
            $this->assertFalse(openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', random_bytes(32), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'snappik-backup-v1'));
            $corrupt = $bytes;
            $corrupt[30] = chr(ord($corrupt[30]) ^ 1);
            $this->assertFalse(openssl_decrypt(substr($corrupt, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'snappik-backup-v1'));
            $plaintext = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'snappik-backup-v1');
            $this->assertIsString($plaintext);
            file_put_contents($dump, $plaintext);
            unset($plaintext);
            $this->assertSame($dumpHash, hash_file('sha256', $dump));
            DB::connection('pgsql')->unprepared('CREATE DATABASE "'.$target.'"');
            $created = true;
            $process = new Process([$binary('pg_restore'), '--exit-on-error', '--single-transaction', '--no-owner', '--no-acl', '--dbname='.$target, $dump], base_path(), $env, timeout: 60);
            $this->assertSame(0, $process->run(), 'pg_restore must succeed into the newly created isolated test database.');
            config(['database.connections.restore' => [...$db, 'database' => $target, 'url' => null]]);
            $this->assertSame($before, $this->snapshot('restore'), 'Every application table must retain identical rows.');
            DB::setDefaultConnection('restore');
            $this->assertSame($target, DB::selectOne('select current_database() as name')->name);
            $restored = Project::findOrFail($project->id);
            $this->assertSame($project->share_token, $restored->share_token, 'Encrypted metadata requires the separately retained application key.');
            $this->assertSame(2, $restored->selection_epoch);
            $this->assertNull($restored->selection_locked_at);
            $this->assertDatabaseCount('choices', 1);
            $this->assertDatabaseCount('confirmation_choices', 1);
            $this->assertDatabaseCount('entitlement_grants', 1);
            $this->assertDatabaseCount('voucher_redemptions', 1);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $held->id, 'state' => 'held']);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $released->id, 'state' => 'released']);
            $this->assertGreaterThanOrEqual(2, DB::table('jobs')->count());
            app(PaymentProcessing::class)->process($event->id);
            $this->postJson($path.'choices', $choice)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('state.epoch', 2);
            $this->assertDatabaseCount('entitlement_grants', 1);
            $this->assertDatabaseCount('voucher_redemptions', 1);
            $this->assertSame('paid', Order::findOrFail($paid->id)->state);
            try {
                DB::table('entitlement_grants')->where('order_id', $paid->id)->update(['ends_at' => now()->subDay()]);
                $this->fail('Restored immutable-ledger trigger must still reject changes.');
            } catch (QueryException $exception) {
                $this->assertSame('P0001', $exception->errorInfo[0]);
            }
            $this->assertSame($before, $this->snapshot('restore'), 'Replay must leave the restored snapshot unchanged.');
            fwrite(STDOUT, "\nBACKUP_RESTORE_PROFILE ".json_encode(['tables' => count($before), 'dump_bytes' => filesize($dump),
                'encrypted_bytes' => filesize($encrypted), 'isolated_restore' => true, 'ledger_and_replay' => 'passed'])."\n");
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('restore');
            if ($created && preg_match('/\Asnappik_restore_[a-f0-9]{16}_test\z/', $target) && $target !== $db['database']) {
                DB::connection('pgsql')->unprepared('DROP DATABASE "'.$target.'" WITH (FORCE)');
            }
            foreach ([$dump, $encrypted] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function snapshot(string $connection): array
    {
        $result = [];
        $db = DB::connection($connection);
        foreach ($db->select("select tablename from pg_tables where schemaname = 'public' order by tablename") as $table) {
            $name = $db->getQueryGrammar()->wrapTable($table->tablename);
            $hash = hash_init('sha256');
            foreach ($db->cursor('select to_jsonb(t)::text as row from '.$name.' t order by to_jsonb(t)::text') as $record) {
                hash_update($hash, $record->row."\n");
            }
            $result[$table->tablename] = hash_final($hash);
        }

        return $result;
    }
}
