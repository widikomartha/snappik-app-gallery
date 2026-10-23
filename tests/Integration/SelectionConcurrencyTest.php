<?php

namespace Tests\Integration;

use App\Actions\Projects\ManageProject;
use App\Actions\Selections\ManageSelection;
use App\Models\AccountAccess;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\SelectionFixture;
use Tests\TestCase;

class SelectionConcurrencyTest extends TestCase
{
    use DatabaseMigrations, SelectionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function race(Project $project, array $operations, ?callable $beforeRelease = null): array
    {
        $db = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => $db['database'], 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '', 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'AUTH_MAILER' => 'array'];
        $processes = [];
        DB::beginTransaction();
        try {
            User::whereKey($project->user_id)->lockForUpdate()->firstOrFail();
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/selection-race-worker.php')], base_path(), $env,
                    json_encode(['capability' => $project->share_token, ...$operation]), 30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), 'Independent selection worker started.');
                preg_match('/ready:(\d+)/', $process->getOutput(), $match);
                $waiting = false;
                for ($attempt = 0; $attempt < 200; $attempt++) {
                    DB::select('select pg_stat_clear_snapshot()');
                    if (DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$match[1]])?->wait_event_type === 'Lock') {
                        $waiting = true;
                        break;
                    }
                    usleep(10000);
                }
                $this->assertTrue($waiting, 'Selection worker must contend on a real PostgreSQL lock before the gate change.');
            }
            if ($beforeRelease) {
                $beforeRelease();
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'Selection worker completed with sanitized output.');
                $results[] = trim(preg_replace('/ready:\d+\s*/', '', $process->getOutput()));
            }
            sort($results);

            return $results;
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

    private function choose(Project $project): void
    {
        $this->postJson('/g/'.$project->share_token.'/selection/choices', $this->selectionPayload($project))->assertOk();
    }

    public function test_two_clients_cannot_both_take_the_final_slot(): void
    {
        $project = $this->selectionProject(1);
        $operations = array_map(fn ($file) => ['confirm' => false, 'data' => $this->selectionPayload($project, $file)], ['one', 'two']);
        $this->assertSame(['200', '409'], $this->race($project, $operations));
        $this->assertDatabaseCount('choices', 1);
        $this->assertSame(2, $project->fresh()->selection_version);
    }

    public function test_simultaneous_operation_replay_commits_once(): void
    {
        $project = $this->selectionProject();
        $operation = ['confirm' => false, 'data' => $this->selectionPayload($project)];
        $this->assertSame(['200', '200'], $this->race($project, [$operation, $operation]));
        $this->assertDatabaseCount('choices', 1);
        $this->assertDatabaseCount('mutation_receipts', 1);
        $this->assertSame(2, $project->fresh()->selection_version);
    }

    public function test_edit_and_confirmation_serialize_without_mutating_a_confirmed_snapshot(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $confirm = array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]);
        $results = $this->race($project, [['confirm' => true, 'data' => $confirm],
            ['confirm' => false, 'data' => $this->selectionPayload($project, 'two')]]);
        $this->assertTrue(in_array($results, [['200', '403'], ['200', '409']], true));
        if ($project->fresh()->selection_locked_at) {
            $this->assertDatabaseCount('confirmation_choices', 1);
            $this->assertDatabaseHas('confirmation_choices', ['file_id' => 'one']);
            $this->assertDatabaseCount('choices', 1);
        } else {
            $this->assertDatabaseCount('selection_confirmations', 0);
            $this->assertDatabaseCount('choices', 2);
        }
    }

    public function test_simultaneous_identical_review_confirmations_make_one_snapshot(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $operations = [];
        for ($i = 0; $i < 2; $i++) {
            $operations[] = ['confirm' => true, 'data' => array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true])];
        }
        $this->assertSame(['200', '200'], $this->race($project, $operations));
        $this->assertDatabaseCount('selection_confirmations', 1);
        $this->assertDatabaseCount('confirmation_choices', 1);
    }

    public function test_waiting_requests_recheck_account_expiry_under_the_lock(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $operation = ['confirm' => false, 'data' => $this->selectionPayload($project, 'two')];
        $this->assertSame(['403', '403'], $this->race($project, [$operation, $operation],
            fn () => AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()])));
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_waiting_requests_recheck_module_deadline_under_the_lock(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $operation = ['confirm' => false, 'data' => $this->selectionPayload($project, 'two')];
        $this->assertSame(['404', '404'], $this->race($project, [$operation, $operation],
            fn () => $project->modules()->where('type', 'selection')->update(['deadline' => now()])));
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_waiting_old_epoch_writes_cannot_modify_a_photographer_reopened_selection(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $confirm = array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]);
        $this->postJson('/g/'.$project->share_token.'/selection/confirm', $confirm)->assertOk();
        $operation = ['confirm' => false, 'data' => $this->selectionPayload($project, 'two')];
        $reopen = ['epoch' => $project->selection_epoch, 'version' => $project->selection_version];
        $this->assertSame(['409', '409'], $this->race($project, [$operation, $operation],
            fn () => app(ManageSelection::class)->reopen($project->photographer, $project->id, $reopen)));
        $this->assertSame(2, $project->fresh()->selection_epoch);
        $this->assertDatabaseCount('choices', 1);
        $this->assertDatabaseCount('confirmation_choices', 1);
        $this->assertDatabaseCount('selection_confirmations', 1);
    }

    public function test_waiting_requests_revalidate_capability_after_rotation_commits(): void
    {
        $project = $this->selectionProject();
        $operation = ['confirm' => false, 'data' => $this->selectionPayload($project)];
        $this->assertSame(['404', '404'], $this->race($project, [$operation, $operation],
            fn () => app(ManageProject::class)->command($project->photographer, $project->id, $project->version, 'rotate')));
        $this->assertDatabaseCount('choices', 0);
        $this->assertDatabaseCount('mutation_receipts', 0);
    }
}
