<?php

namespace Tests\Integration;

use App\Actions\Projects\ManageProject;
use App\Models\CatalogueRevision;
use App\Models\PaymentEvent;
use App\Services\Billing\PaymentProcessing;
use App\Services\Billing\PaymentRecovery;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\SelectionFixture;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class RecoveryBoundaryTest extends TestCase
{
    use DatabaseMigrations, SelectionFixture;

    private function worker(string $action, string $id, bool $hang): Process
    {
        $db = config('database.connections.pgsql');

        return new Process([PHP_BINARY, '-d', 'memory_limit=128M', base_path('tests/Support/recovery-process-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_DATABASE' => $db['database'], 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ], json_encode(compact('action', 'id', 'hang')), 120);
    }

    public function test_killed_import_resumes_committed_pages_without_publishing_partial_or_erasing_choices(): void
    {
        Queue::fake();
        $project = $this->selectionProject();
        $this->postJson('/g/'.$project->share_token.'/selection/choices', $this->selectionPayload($project))->assertOk();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $old = $module->published_revision_id;
        app(ManageProject::class)->command($project->photographer, $project->id, $project->fresh()->version, 'refresh', 'selection');
        $revision = CatalogueRevision::findOrFail($module->fresh()->requested_revision_id);
        $child = $this->worker('import', $revision->id, true);
        $child->start();
        try {
            $this->assertTrue($child->waitUntil(fn ($type, $out) => str_contains($out, 'blocked')));
            $this->assertSame('importing', $revision->fresh()->state);
            $this->assertSame(250, $revision->entries()->where('kind', 'photo')->count());
            $this->assertSame($old, $module->fresh()->published_revision_id);
            $child->stop(0);
            $restart = $this->worker('import', $revision->id, false);
            $this->assertSame(0, $restart->run(), $restart->getErrorOutput());
            $this->assertSame('complete', $revision->fresh()->state);
            $this->assertSame($revision->id, $module->fresh()->published_revision_id);
            $this->assertSame(3000, $revision->entries()->where('kind', 'photo')->count());
            $this->assertSame(3000, $revision->entries()->where('kind', 'photo')->distinct()->count('file_id'));
            $this->assertDatabaseHas('choices', ['project_id' => $project->id, 'file_id' => 'one']);
            $metrics = json_decode(trim($restart->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertLessThan(128 * 1024 ** 2, $metrics['peak_php_bytes']);
            fwrite(STDOUT, "\nIMPORT_RESTART_PROFILE ".json_encode($metrics + ['photos' => 3000, 'page_size' => 250])."\n");
        } finally {
            $child->stop(0);
        }
    }

    public function test_kill_during_grant_transaction_rolls_back_and_scheduler_redelivery_applies_once(): void
    {
        F::configure();
        $buyer = F::buyer();
        $end = $buyer->accountAccess->access_ends_at;
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $event = app(PaymentProcessing::class)->record($order, 'scheduled');
        $child = $this->worker('payment', $event->id, true);
        $child->start();
        try {
            $this->assertTrue($child->waitUntil(fn ($type, $out) => str_contains($out, 'blocked')));
            $this->assertDatabaseCount('entitlement_grants', 0);
            $this->assertSame('processing', $event->fresh()->state);
            $child->stop(0);
            $this->assertDatabaseCount('entitlement_grants', 0);
            $this->assertDatabaseCount('voucher_redemptions', 0);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
            $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end));
            DB::table('jobs')->where('queue', 'payments')->delete(); // Simulate lost in-flight queue delivery.
            PaymentEvent::whereKey($event->id)->update(['started_at' => now()->subMinutes(6), 'enqueued_at' => now()->subMinutes(6)]);
            app(PaymentRecovery::class)->sweep();
            $this->assertSame(1, DB::table('jobs')->where('queue', 'payments')->count());
            foreach ([1, 2] as $delivery) {
                $restart = $this->worker('payment', $event->id, false);
                $this->assertSame(0, $restart->run(), $restart->getErrorOutput());
            }
            $this->assertSame('paid', $order->fresh()->state);
            $this->assertDatabaseCount('entitlement_grants', 1);
            $this->assertDatabaseCount('voucher_redemptions', 1);
            $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end->addHours(720)));
        } finally {
            $child->stop(0);
        }
    }
}
