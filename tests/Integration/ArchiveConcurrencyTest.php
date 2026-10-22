<?php

namespace Tests\Integration;

use App\Contracts\DeliveryArchiveStorage;
use App\Models\ArchiveJob;
use App\Models\Project;
use App\Services\Archives\ArchiveRecovery;
use App\Services\Archives\DeliveryArchives;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\ArchiveDisk;
use Tests\Support\DeliveryFixture;
use Tests\TestCase;

class ArchiveConcurrencyTest extends TestCase
{
    use DatabaseMigrations, DeliveryFixture;

    private function process(array $input): Process
    {
        $db = config('database.connections.pgsql');

        return new Process([PHP_BINARY, base_path('tests/Support/archive-process-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_DATABASE' => $db['database'], 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'] ?? '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ], json_encode($input), 30);
    }

    private function fixture(): Project
    {
        $manager = Queue::getFacadeRoot();
        Queue::fake();
        $project = $this->deliveryProject();
        Queue::swap($manager);

        return $project;
    }

    public function test_simultaneous_requests_commit_one_manifest_and_one_database_job(): void
    {
        $project = $this->fixture();
        $processes = [];
        DB::beginTransaction();
        try {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < 2; $i++) {
                $process = $this->process(['action' => 'request', 'capability' => $project->share_token]);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')));
            }
            DB::commit();
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }
            $this->assertSame($processes[0]->getOutput(), $processes[1]->getOutput());
            $this->assertDatabaseCount('archive_jobs', 1);
            $this->assertDatabaseCount('archive_entries', 2);
            $this->assertSame(1, DB::table('jobs')->where('queue', 'archives')->count());
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

    public function test_killed_worker_leaves_tracked_partial_and_redelivery_cannot_overlap_live_worker(): void
    {
        $project = $this->fixture();
        $request = Request::create('/synthetic', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $archives = app(DeliveryArchives::class);
        $job = $archives->authorized($request, $project->share_token, fn ($module) => $archives->request($module));
        $disk = new ArchiveDisk;
        app()->instance(DeliveryArchiveStorage::class, $disk);
        $child = $this->process(['action' => 'hang', 'archive_id' => $job->id]);
        $child->start();
        try {
            $this->assertTrue($child->waitUntil(fn ($type, $output) => str_contains($output, 'streaming')));
            $job->refresh();
            $this->assertSame('processing', $job->state);
            $key = $job->object_key;
            $this->assertFileExists($disk->path($key));
            $other = $this->process(['action' => 'build', 'archive_id' => $job->id]);
            $this->assertSame(0, $other->run(), $other->getErrorOutput());
            $this->assertDatabaseCount('archive_attempts', 1);
            ArchiveJob::whereKey($job->id)->update(['lease_until' => now()->subSecond()]);
            app(ArchiveRecovery::class)->sweep();
            $this->assertSame('processing', $job->fresh()->state, 'A live session lock protects slow workers, including stalled provider calls.');
            $child->stop(0);
            app(ArchiveRecovery::class)->sweep();
            $this->assertSame('archive_worker_interrupted', $job->fresh()->failure);
            $this->assertFileDoesNotExist($disk->path($key));
            $archives->authorized($request, $project->share_token, fn ($module) => $archives->request($module));
            $restarted = $this->process(['action' => 'build', 'archive_id' => $job->id]);
            $this->assertSame(0, $restarted->run(), $restarted->getErrorOutput());
            $this->assertSame('ready', $job->fresh()->state);
            $this->assertNotSame($key, $job->fresh()->object_key);
        } finally {
            if ($child->isRunning()) {
                $child->stop(0);
            }
            foreach (DB::table('archive_attempts')->where('archive_job_id', $job->id)->pluck('object_key') as $key) {
                $disk->delete($key);
            }
        }
    }
}
