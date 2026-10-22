<?php

namespace Tests\Integration;

use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Jobs\BuildDriveProofArchive;
use App\Models\DriveProofRun;
use App\Models\User;
use App\Services\Archives\ArchivePath;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Archives\VerifyDriveProof;
use App\Services\Drive\DriveReference;
use App\Services\Drive\ImportDriveProof;
use App\Services\ProviderFailure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\DriveFixtures as F;
use Tests\Support\R2HttpFixture;
use Tests\TestCase;
use ZipArchive;

class DriveProofWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function source(array $responses): PublicDriveSource
    {
        return new PublicDriveSource(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]), 'synthetic-api-key', sleep: fn () => null);
    }

    private function fixture(): R2HttpFixture
    {
        $path = tempnam(sys_get_temp_dir(), 'snappik-proof-');
        $this->paths[] = $path;

        return new R2HttpFixture($path);
    }

    private function imported(string $firstBytes = 'original-one'): DriveProofRun
    {
        $source = $this->source([F::json(F::folder()), F::json(F::folder()), F::json(['files' => [F::photo('photo1', $firstBytes), F::photo('photo2', 'original-two')]])]);
        $importer = new ImportDriveProof($source);
        $owner = User::factory()->create();
        $run = $importer->start($owner, new DriveReference('root', 'synthetic-root-key'));
        $importer->resume($run, $owner->id);

        return $run->refresh();
    }

    public function test_nested_paginated_import_checkpoints_and_reports_exclusions(): void
    {
        $unsupported = ['id' => 'raw', 'name' => 'photo.raw', 'mimeType' => 'application/octet-stream'];
        $shortcut = ['id' => 'shortcut', 'name' => 'external', 'mimeType' => 'application/vnd.google-apps.shortcut'];
        $source = $this->source([F::json(F::folder()), F::json(F::folder()),
            F::json(['files' => [F::folder('nested', 'synthetic-nested-key')], 'nextPageToken' => 'page2']),
            F::json(F::folder()), F::json(['files' => [$unsupported, $shortcut]]),
            F::json(F::folder('nested')), F::json(['files' => [F::photo(), F::photo('photo2', 'original-two')]])]);
        $importer = new ImportDriveProof($source);
        $owner = User::factory()->create();
        $run = $importer->start($owner, new DriveReference('root', 'synthetic-root-key'));
        $importer->resume($run, $owner->id);
        $this->assertSame('imported', $run->fresh()->state);
        $this->assertSame(2, $run->nodes()->where('kind', 'photo')->count());
        $this->assertSame(1, $run->nodes()->where('kind', 'unsupported')->count());
        $this->assertSame(1, $run->nodes()->where('kind', 'shortcut')->count());
        $this->assertDatabaseCount('drive_proof_pages', 3);
        $this->assertStringStartsWith('nested__nested/', $run->nodes()->where('file_id', 'photo1')->first()->path);
        $this->assertStringNotContainsString('synthetic-root-key', $run->getRawOriginal('root'));
        $this->assertSame('synthetic-root-key', $run->fresh()->root['resourceKey']);
    }

    public function test_interrupted_descendant_import_resumes_from_last_committed_page(): void
    {
        $owner = User::factory()->create();
        $source = $this->source([F::json(F::folder()), F::json(F::folder()),
            F::json(['files' => [F::folder('nested')], 'nextPageToken' => 'next']),
            F::json(F::folder()), F::json(['files' => [F::photo()]]), new Response(404)]);
        $importer = new ImportDriveProof($source);
        $run = $importer->start($owner, new DriveReference('root'));
        try {
            $importer->resume($run, $owner->id);
            $this->fail('Expected inaccessible descendant');
        } catch (ProviderFailure) {
            $this->assertSame('import_failed', $run->fresh()->state);
            $this->assertSame(1, $run->nodes()->where('kind', 'photo')->count());
            $this->assertTrue($run->nodes()->where('file_id', 'root')->first()->listed);
        }
        $resume = new ImportDriveProof($this->source([F::json(F::folder('nested')), F::json(['files' => [F::photo('photo2')]])]));
        $resume->resume($run->fresh(), $owner->id);
        $this->assertSame('imported', $run->fresh()->state);
        $this->assertSame(2, $run->nodes()->where('kind', 'photo')->count());
    }

    public function test_repeated_page_token_and_tree_cycle_fail_closed(): void
    {
        foreach ([['files' => [F::photo()], 'nextPageToken' => 'repeat'], ['files' => [F::folder()]]] as $case) {
            $owner = User::factory()->create();
            $responses = [F::json(F::folder()), F::json(F::folder())];
            if (isset($case['nextPageToken'])) {
                $responses[] = F::json(['files' => [], 'nextPageToken' => 'repeat']);
                $responses[] = F::json(F::folder());
            }
            $responses[] = F::json($case);
            $importer = new ImportDriveProof($this->source($responses));
            $run = $importer->start($owner, new DriveReference('root'));
            try {
                $importer->resume($run, $owner->id);
                $this->fail('Expected cycle rejection');
            } catch (ProviderFailure $error) {
                $this->assertContains($error->reason, ['drive_pagination_cycle', 'drive_repeated_identity_or_cycle']);
                $this->assertSame('import_failed', $run->fresh()->state);
            }
        }
    }

    public function test_complete_archive_preserves_duplicate_names_identities_original_bytes_and_crc(): void
    {
        $run = $this->imported();
        $fixture = $this->fixture();
        $responses = [];
        foreach (['photo1' => 'original-one', 'photo2' => 'original-two'] as $id => $bytes) {
            $photo = F::photo($id, $bytes);
            $responses = [...$responses, F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg'], $bytes), F::json($photo)];
        }
        $builder = new BuildStreamingZip($this->source($responses), new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'));
        (new BuildDriveProofArchive($run->id))->handle($builder);
        $run->refresh();
        $this->assertSame('ready', $run->state);
        $this->assertSame(2, (int) $run->completed_files);
        $this->assertSame(24, (int) $run->completed_bytes);
        $this->assertSame(86400.0, $run->ready_at->diffInSeconds($run->expires_at));
        $this->assertSame(['create', 'part', 'complete', 'head'], $fixture->operations);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($fixture->path, ZipArchive::CHECKCONS));
        $this->assertSame(2, $zip->numFiles);
        foreach (['photo1' => 'original-one', 'photo2' => 'original-two'] as $id => $bytes) {
            $path = 'same__'.$id.'.jpg';
            $this->assertSame($bytes, $zip->getFromName($path));
            $this->assertSame(hash('crc32b', $bytes), sprintf('%08x', $zip->statName($path)['crc']));
        }
        $zip->close();
        $verification = (new VerifyDriveProof(new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'), $this->source([])))->verify($run, $run->user_id);
        $this->assertSame(2, $verification['verified_files']);
        $this->assertSame('passed', $verification['crc_and_original_md5']);
        $this->assertNotNull($run->fresh()->verified_at);
        $this->expectExceptionMessage('archive_attempt_not_claimable');
        (new BuildDriveProofArchive($run->id))->handle($builder);
    }

    public function test_failing_second_input_never_publishes_partial_archive(): void
    {
        $bytes = str_repeat('x', 9 * 1024 ** 2);
        $run = $this->imported($bytes);
        $fixture = $this->fixture();
        $photo = F::photo('photo1', $bytes);
        $drive = $this->source([F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg'], $bytes), F::json($photo), new Response(404)]);
        try {
            (new BuildDriveProofArchive($run->id))->handle(new BuildStreamingZip($drive, new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket')));
            $this->fail('Expected second source failure');
        } catch (ProviderFailure) {
            $this->assertSame('zip_failed', $run->fresh()->state);
            $this->assertNull($run->fresh()->ready_at);
            $this->assertSame(['create', 'part', 'abort', 'delete'], $fixture->operations);
            $this->assertFileDoesNotExist($fixture->path);
        }
    }

    public function test_corrupt_stream_swallowed_by_library_wrapper_cannot_be_ready(): void
    {
        $run = $this->imported();
        $fixture = $this->fixture();
        $photo = F::photo();
        $drive = $this->source([F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-BAD')]);
        try {
            (new BuildDriveProofArchive($run->id))->handle(new BuildStreamingZip($drive, new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket')));
            $this->fail('Expected checksum failure');
        } catch (ProviderFailure) {
            $this->assertSame('zip_failed', $run->fresh()->state);
            $this->assertFileDoesNotExist($fixture->path);
        }
    }

    public function test_cleanup_failure_is_persisted_without_ready_output(): void
    {
        $run = $this->imported();
        $fixture = $this->fixture();
        $fixture->failCleanup = true;
        try {
            (new BuildDriveProofArchive($run->id))->handle(new BuildStreamingZip($this->source([new Response(403)]), new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket')));
            $this->fail('Expected cleanup failure');
        } catch (ProviderFailure) {
            $this->assertSame('cleanup_required', $run->fresh()->state);
            $this->assertNull($run->fresh()->ready_at);
        }
    }

    public function test_cross_owner_resume_is_denied(): void
    {
        $run = $this->imported();
        $other = User::factory()->create();
        $this->expectException(HttpException::class);
        (new ImportDriveProof($this->source([])))->resume($run, $other->id);
    }

    public function test_command_reports_integration_blocker_and_rejects_production(): void
    {
        $owner = User::factory()->create();
        config(['drive_proof.api_key' => '']);
        $this->artisan('snappik:drive-proof', ['--owner' => $owner->id])->expectsOutputToContain('Integration blocked')->assertFailed();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('snappik:drive-proof', ['--owner' => $owner->id])->expectsOutputToContain('only in local/testing')->assertFailed();
    }

    public function test_archive_paths_are_safe_and_resist_suffix_collisions(): void
    {
        $first = ArchivePath::append('', '../../bad\\name.jpg', 'id1', true);
        $second = ArchivePath::append('', '../../bad\\name__id1.jpg', 'id2', true);
        $this->assertStringNotContainsString('/', $first);
        $this->assertStringNotContainsString('\\', $first);
        $this->assertStringNotContainsString('..', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_empty_folder_and_nonfolder_sources_are_never_imported(): void
    {
        $owner = User::factory()->create();
        $importer = new ImportDriveProof($this->source([F::json(F::folder()), F::json(F::folder()), F::json(['files' => []])]));
        $run = $importer->start($owner, new DriveReference('root'));
        try {
            $importer->resume($run, $owner->id);
            $this->fail('Expected empty source failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('drive_no_supported_photos', $error->reason);
            $this->assertSame('import_failed', $run->fresh()->state);
        }
        $this->expectExceptionMessage('drive_source_is_not_folder');
        (new ImportDriveProof($this->source([F::json(F::photo())])))->start($owner, new DriveReference('photo1'));
    }

    public function test_expired_archive_verification_is_denied_before_storage_read(): void
    {
        $run = $this->imported();
        $run->update(['state' => 'ready', 'expires_at' => now()->subSecond()]);
        $fixture = $this->fixture();
        $this->expectExceptionMessage('archive_not_ready_or_expired');
        (new VerifyDriveProof(new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'), $this->source([])))->verify($run, $run->user_id);
    }
}
