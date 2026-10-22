<?php

namespace Tests\Feature;

use App\Contracts\ArchiveStorage;
use App\Contracts\DeliveryArchiveStorage;
use App\Contracts\DriveSource;
use App\Jobs\BuildDeliveryArchive;
use App\Models\AccountAccess;
use App\Models\ArchiveEntry;
use App\Models\ArchiveJob;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Services\Archives\ArchiveRecovery;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Archives\DeliveryArchives;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use Tests\Support\ArchiveDisk;
use Tests\Support\CatalogueDrive;
use Tests\Support\DeliveryFixture;
use Tests\Support\DriveFixtures as F;
use Tests\TestCase;
use ZipArchive;

class ClientArchiveTest extends TestCase
{
    use DeliveryFixture, RefreshDatabase;

    private ArchiveDisk $disk;

    private Project $project;

    private string $base;

    private $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = Queue::getFacadeRoot();
        Queue::fake();
        $this->project = $this->deliveryProject();
        $this->base = '/g/'.$this->project->share_token.'/delivery/archive';
        $this->disk = new ArchiveDisk;
        app()->instance(ArchiveStorage::class, $this->disk);
        app()->instance(DeliveryArchiveStorage::class, $this->disk);
        app()->instance(DriveSource::class, new class extends CatalogueDrive
        {
            public function original(DriveItem $expected): StreamInterface
            {
                return Utils::streamFor('original-one');
            }
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->disk->keys as $key) {
            $this->disk->delete($key);
        }
        parent::tearDown();
    }

    private function createJob(): ArchiveJob
    {
        $response = $this->postJson($this->base)->assertAccepted();

        return ArchiveJob::findOrFail($response->json('archive.id'));
    }

    private function build(ArchiveJob $job): ArchiveJob
    {
        (new BuildDeliveryArchive($job->id))->handle(app(DeliveryArchives::class));

        return $job->fresh();
    }

    public function test_full_immutable_manifest_deduplicates_reloads_and_preserves_all_identities_and_crc(): void
    {
        $module = $this->project->modules()->where('type', 'delivery')->firstOrFail();
        // Crosses the UI window and the server's 200-entry streaming chunk.
        for ($i = 0; $i < 207; $i++) {
            CatalogueEntry::create(['revision_id' => $module->published_revision_id, 'file_id' => 'extra-'.$i, 'kind' => 'photo',
                'name' => 'same.jpg', 'path' => '../nested/same.jpg', 'bytes' => 12, 'metadata' => F::photo('extra-'.$i)]);
        }
        $job = $this->createJob();
        $this->assertSame(209, $job->total_files);
        $this->postJson($this->base.'?folder=1&page=2')->assertJsonPath('archive.id', $job->id);
        $this->getJson($this->base)->assertJsonPath('archive.state', 'queued');
        Queue::assertPushed(BuildDeliveryArchive::class, 1);
        $entries = ArchiveEntry::where('archive_job_id', $job->id)->get();
        CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->delete();
        $job = $this->build($job);
        $this->assertSame('ready', $job->state);
        $this->assertSame(86400.0, $job->ready_at->diffInSeconds($job->expires_at));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->disk->path($job->object_key)));
        $this->assertSame(209, $zip->numFiles);
        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('../', $entry->path);
            $this->assertSame('original-one', $zip->getFromName($entry->path));
            $this->assertSame(sprintf('%08x', $zip->statName($entry->path)['crc']), hash('crc32b', 'original-one'));
        }
        $zip->close();
        $this->getJson($this->base)->assertJsonPath('archive.completedFiles', 209)->assertJsonPath('archive.state', 'ready');
        $this->postJson($this->base)->assertJsonPath('archive.id', $job->id)->assertJsonPath('archive.state', 'ready');
        $this->assertCount(1, $this->disk->keys);
        $response = $this->get($this->base.'/'.$job->id)->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertSame(file_get_contents($this->disk->path($job->object_key)), $response->streamedContent());
        $this->assertStringNotContainsString('object_key', $this->getJson($this->base)->getContent());
    }

    public function test_range_head_if_range_and_ordinary_account_expiry(): void
    {
        $job = $this->build($this->createJob());
        AccountAccess::whereKey($this->project->user_id)->update(['access_ends_at' => now()]);
        $url = $this->base.'/'.$job->id;
        $bytes = file_get_contents($this->disk->path($job->object_key));
        $head = $this->head($url)->assertOk()->assertHeader('Content-Length', (string) strlen($bytes));
        $etag = $head->headers->get('ETag');
        $part = $this->withHeaders(['Range' => 'bytes=3-18', 'If-Range' => $etag])->get($url)->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 3-18/'.strlen($bytes));
        $this->assertSame(substr($bytes, 3, 16), $part->streamedContent());
        $this->withHeaders(['Range' => 'bytes=3-18', 'If-Range' => '"stale"'])->get($url)->assertOk();
        $this->withHeaders(['Range' => 'bytes='.strlen($bytes).'-', 'If-Range' => $etag])->get($url)->assertStatus(416);
        $this->flushHeaders();
        $this->postJson($this->base)->assertJsonPath('archive.state', 'ready');
    }

    public static function invalidInputs(): array
    {
        return [['missing'], ['changed'], ['truncated'], ['surplus'], ['manifest']];
    }

    #[DataProvider('invalidInputs')]
    public function test_failed_input_never_publishes_partial_and_retry_uses_a_fresh_attempt(string $mode): void
    {
        $job = $this->createJob();
        $good = app(DriveSource::class);
        if ($mode === 'manifest') {
            $entry = ArchiveEntry::where('archive_job_id', $job->id)->firstOrFail();
            $entry->update(['path' => 'changed.jpg']);
        } else {
            app()->instance(DriveSource::class, new class($mode) extends CatalogueDrive
            {
                private int $originalCalls = 0;

                public function __construct(private string $mode) {}

                public function original(DriveItem $expected): StreamInterface
                {
                    if (++$this->originalCalls === 1) {
                        return Utils::streamFor('original-one');
                    }
                    if (in_array($this->mode, ['missing', 'changed'], true)) {
                        throw new ProviderFailure($this->mode === 'missing' ? 'drive_source_unavailable' : 'drive_source_changed');
                    }

                    return Utils::streamFor($this->mode === 'surplus' ? 'original-one-extra' : 'short');
                }
            });
        }
        $job = $this->build($job);
        $this->assertSame('failed', $job->state);
        $oldKey = $job->object_key;
        $this->assertFileDoesNotExist($this->disk->path($oldKey));
        $this->get($this->base.'/'.$job->id)->assertNotFound();
        app()->instance(DriveSource::class, $good);
        if ($mode === 'manifest') {
            // A corrupt immutable manifest requires repair, not silently accepting different input.
            return;
        }
        $this->assertSame('ready', $this->build($this->createJob())->state);
        $this->assertNotSame($oldKey, $job->fresh()->object_key);
        $this->assertSame(2, DB::table('archive_attempts')->count());
    }

    public function test_expiry_is_enforced_at_equality_without_cleanup_and_recreation_is_safe(): void
    {
        $job = $this->build($this->createJob());
        $oldKey = $job->object_key;
        $this->travelTo($job->expires_at);
        $this->get($this->base.'/'.$job->id)->assertNotFound();
        $this->getJson($this->base)->assertJsonPath('archive.state', 'expired');
        $this->assertFileExists($this->disk->path($oldKey));
        $this->disk->failCleanup = true;
        app(ArchiveRecovery::class)->sweep();
        $this->assertDatabaseHas('archive_attempts', ['id' => $job->attempt_id, 'cleanup_pending' => true, 'cleanup_failure' => 'archive_cleanup_required']);
        $newJob = $this->build($this->createJob());
        $this->assertSame('ready', $newJob->state);
        $this->disk->failCleanup = false;
        app(ArchiveRecovery::class)->sweep();
        $this->assertFileDoesNotExist($this->disk->path($oldKey));
        $this->assertFileExists($this->disk->path($newJob->object_key));
    }

    public static function gates(): array
    {
        return array_map(fn ($gate) => [$gate], ['password', 'deadline', 'unpublished', 'archive', 'suspension', 'capability', 'revision']);
    }

    private function close(string $gate): void
    {
        $module = $this->project->modules()->where('type', 'delivery')->firstOrFail();
        match ($gate) {
            'password' => $module->update(['password_hash' => Hash::make('new-password'), 'gate_version' => $module->gate_version + 1]),
            'deadline' => $module->update(['deadline' => now()]),
            'unpublished' => $module->update(['published' => false]),
            'archive' => $this->project->update(['archived_at' => now()]),
            'suspension' => $this->project->photographer()->update(['suspended_at' => now()]),
            'capability' => $this->project->update(['share_digest' => hash('sha256', Str::random(64))]),
            'revision' => $module->update(['published_revision_id' => null]),
        };
    }

    #[DataProvider('gates')]
    public function test_new_requests_cannot_bypass_changed_gates(string $gate): void
    {
        $job = $this->build($this->createJob());
        $this->close($gate);
        $this->getJson($this->base)->assertNotFound();
        $this->postJson($this->base)->assertNotFound();
        $this->withHeader('Range', 'bytes=1-5')->get($this->base.'/'.$job->id)->assertNotFound();
        $this->head($this->base.'/'.$job->id)->assertNotFound();
    }

    public function test_worker_rechecks_gate_before_source_and_before_publishing_completed_object(): void
    {
        $job = $this->createJob();
        $this->close('deadline');
        $this->assertSame('failed', $this->build($job)->state);
        $this->assertSame([], $this->disk->keys);
    }

    public function test_cross_project_archive_substitution_and_missing_storage_are_denied(): void
    {
        $job = $this->build($this->createJob());
        $source = app(DriveSource::class);
        $other = $this->deliveryProject();
        app()->instance(DriveSource::class, $source);
        $this->get('/g/'.$other->share_token.'/delivery/archive/'.$job->id)->assertNotFound();
        $this->disk->delete($job->object_key);
        $this->get($this->base.'/'.$job->id)->assertStatus(502)->assertHeaderMissing('Content-Disposition')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->getJson($this->base)->assertJsonPath('archive.state', 'failed');
        $this->assertSame('ready', $this->build($this->createJob())->state);
    }

    public function test_gate_change_during_finalization_deletes_even_a_completed_object(): void
    {
        $job = $this->createJob();
        $this->disk->beforeComplete = fn () => $this->close('deadline');
        $job = $this->build($job);
        $this->assertSame('failed', $job->state);
        $this->assertSame('archive_gallery_closed', $job->failure);
        $this->assertFileDoesNotExist($this->disk->path($job->object_key));
        $this->assertDatabaseHas('archive_attempts', ['id' => $job->attempt_id, 'cleanup_pending' => false]);
    }

    public function test_new_gate_generation_never_serves_an_old_ready_archive(): void
    {
        $old = $this->build($this->createJob());
        $module = $this->project->modules()->where('type', 'delivery')->firstOrFail();
        $module->increment('gate_version');
        $this->getJson($this->base)->assertJsonPath('archive', null);
        $this->get($this->base.'/'.$old->id)->assertNotFound();
        $new = $this->createJob();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(2, ArchiveJob::count());
        app(ArchiveRecovery::class)->sweep();
        $this->assertFileDoesNotExist($this->disk->path($old->object_key));
    }

    public function test_new_published_revision_creates_its_own_immutable_manifest(): void
    {
        $old = $this->build($this->createJob());
        $module = $this->project->modules()->where('type', 'delivery')->firstOrFail();
        $revision = CatalogueRevision::findOrFail($module->published_revision_id)->replicate();
        $revision->save();
        foreach (CatalogueEntry::where('revision_id', $old->revision_id)->get() as $entry) {
            $copy = $entry->replicate();
            $copy->revision_id = $revision->id;
            $copy->save();
        }
        $module->update(['published_revision_id' => $revision->id]);
        $this->get($this->base.'/'.$old->id)->assertNotFound();
        $new = $this->createJob();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame($revision->id, $new->revision_id);
        $this->assertSame(2, ArchiveEntry::where('archive_job_id', $new->id)->count());
    }

    public function test_dead_attempt_is_failed_and_cleanup_retried_before_user_restarts(): void
    {
        $job = $this->createJob();
        $attempt = (string) Str::uuid();
        $key = 'archives/'.$job->id.'/'.$attempt.'.zip';
        $job->update(['state' => 'processing', 'attempt_id' => $attempt, 'object_key' => $key, 'lease_until' => now()->subSecond()]);
        DB::table('archive_attempts')->insert(['id' => $attempt, 'archive_job_id' => $job->id, 'object_key' => $key, 'cleanup_pending' => true]);
        $this->disk->failCleanup = true;
        app(ArchiveRecovery::class)->sweep();
        $this->assertSame('archive_worker_interrupted', $job->fresh()->failure);
        $this->disk->failCleanup = false;
        $this->assertSame('ready', $this->build($this->createJob())->state);
        app(ArchiveRecovery::class)->sweep();
        $this->assertContains($key, $this->disk->cleaned);
    }

    public function test_request_job_and_manifest_roll_back_with_queue_insertion(): void
    {
        Queue::swap($this->queueManager);
        DB::beginTransaction();
        try {
            $this->createJob();
            $this->assertSame(1, DB::table('jobs')->where('queue', 'archives')->count());
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, ArchiveJob::count());
        $this->assertSame(0, ArchiveEntry::count());
        $this->assertSame(0, DB::table('jobs')->where('queue', 'archives')->count());
    }

    public function test_missing_provider_configuration_is_a_visible_failed_attempt_with_tracked_cleanup(): void
    {
        $job = $this->createJob();
        app()->bind(BuildStreamingZip::class, fn () => throw new ProviderFailure('r2_credentials_missing'));
        $job = $this->build($job);
        $this->assertSame('failed', $job->state);
        $this->assertSame('r2_credentials_missing', $job->failure);
        $this->assertNotNull($job->attempt_id);
        $this->getJson($this->base)->assertJsonPath('archive.state', 'failed');
    }

    public function test_recreation_between_cleanup_read_and_state_update_cannot_be_overwritten(): void
    {
        $job = $this->build($this->createJob());
        $job->update(['expires_at' => now()]);
        $interleave = true;
        // Commit a real recreation immediately after maintenance reads its old ready
        // row. Its stale model must not overwrite the newly queued attempt generation.
        DB::listen(function ($query) use (&$interleave) {
            if ($interleave && $query->sql === 'select * from "archive_jobs" where "archive_jobs"."id" = ? limit 1') {
                $interleave = false;
                $this->createJob();
            }
        });
        try {
            app(ArchiveRecovery::class)->cleanAttempt($job->attempt_id);
        } finally {
            $interleave = false;
        }
        $this->assertSame('queued', $job->fresh()->state);
        $this->assertNull($job->fresh()->attempt_id);
        $this->assertSame('ready', $this->build($job)->state);
    }
}
