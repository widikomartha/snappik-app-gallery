<?php

namespace Tests\Integration;

use App\Actions\Selections\ManageSelection;
use App\Contracts\BackupStorage;
use App\Models\Order;
use App\Models\Project;
use App\Services\Archives\DeliveryArchives;
use App\Services\Billing\PaymentProcessing;
use App\Services\Operations\DatabaseBackup;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BackupTools;
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
        $tools = null;
        $env = ['PGHOST' => $db['host'], 'PGPORT' => (string) $db['port'], 'PGUSER' => $db['username'],
            'PGPASSWORD' => $db['password'] ?? '', 'PGDATABASE' => $db['database'], 'PGSSLMODE' => $db['sslmode'] ?? 'prefer'];
        $binary = fn ($tool) => rtrim((string) getenv('SNAPPIK_PG_BIN'), '/\\').DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $original = config('database.default');
        try {
            $before = $this->snapshot('pgsql');
            $tools = new BackupTools;
            unlink($dump);
            app(DatabaseBackup::class)->run(new class($encrypted) implements BackupStorage
            {
                public function __construct(private string $target) {}

                public function store(string $key, string $path): void
                {
                    if (! copy($path, $this->target)) {
                        throw new \RuntimeException('Synthetic backup copy failed.');
                    }
                }
            });
            $this->assertFileDoesNotExist($dump);
            $this->assertFalse($tools->decrypt($encrypted, $tools->root.'/no-key.dump', privateKey: false));
            $bytes = file_get_contents($encrypted);
            $bytes[strlen($bytes) - 5] = chr(ord($bytes[strlen($bytes) - 5]) ^ 1);
            file_put_contents($tools->root.'/corrupt.gpg', $bytes);
            $this->assertFalse($tools->decrypt($tools->root.'/corrupt.gpg', $tools->root.'/corrupt.dump'));
            file_put_contents($tools->root.'/truncated.gpg', substr($bytes, 0, -40));
            $this->assertFalse($tools->decrypt($tools->root.'/truncated.gpg', $tools->root.'/truncated.dump'));
            $this->assertTrue($tools->decrypt($encrypted, $dump));
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
            $tools?->cleanup();
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
