<?php

namespace Tests\Feature;

use App\Contracts\ArchiveStorage;
use App\Contracts\DriveSource;
use App\Jobs\BuildDriveProofArchive;
use App\Models\DriveProofRun;
use App\Models\User;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Archives\VerifyDriveProof;
use App\Services\Drive\DriveReference;
use App\Services\Drive\ImportDriveProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProofAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_owner_is_denied_before_import_job_or_media_provider_calls(): void
    {
        $owner = User::factory()->create();
        $run = DriveProofRun::create(['user_id' => $owner->id, 'root' => ['id' => 'synthetic'], 'state' => 'imported']);
        $owner->forceFill(['suspended_at' => now()])->save();
        $source = $this->mock(DriveSource::class);
        $source->shouldNotReceive('metadata');
        $storage = $this->mock(ArchiveStorage::class);
        $storage->shouldNotReceive('read');
        $builder = new BuildStreamingZip($source, $storage);
        $actions = [
            fn () => (new ImportDriveProof($source))->start($owner, new DriveReference('synthetic')),
            fn () => (new ImportDriveProof($source))->resume($run, $owner->id),
            fn () => (new BuildDriveProofArchive($run->id))->handle($builder),
            fn () => (new VerifyDriveProof($storage, $source))->verify($run, $owner->id),
        ];
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Suspended provider operation must be denied.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
        $this->assertSame('imported', $run->fresh()->state);
    }

    public function test_unsaved_owner_substitution_cannot_resume_or_read_another_proof(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = DriveProofRun::create(['user_id' => $owner->id, 'root' => ['id' => 'synthetic'], 'state' => 'imported']);
        $run->user_id = $other->id;
        $source = $this->mock(DriveSource::class);
        $storage = $this->mock(ArchiveStorage::class);
        $source->shouldNotReceive('metadata');
        $storage->shouldNotReceive('read');
        foreach ([fn () => (new ImportDriveProof($source))->resume($run, $other->id), fn () => (new VerifyDriveProof($storage, $source))->verify($run, $other->id)] as $action) {
            try {
                $action();
                $this->fail('Persisted ownership must be checked.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }
}
