<?php

namespace Tests\Feature;

use App\Actions\Selections\ManageSelection;
use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveSource;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Models\User;
use App\Services\Drive\PreviewImage;
use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\SelectionFixture;
use Tests\TestCase;

class ClientSelectionTest extends TestCase
{
    use RefreshDatabase, SelectionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function path(Project $project, string $suffix = 'data'): string
    {
        return '/g/'.$project->share_token.'/selection/'.$suffix;
    }

    private function confirmPayload(Project $project): array
    {
        return array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]);
    }

    public function test_acknowledged_choices_are_shared_and_replayed_operations_do_not_change_version(): void
    {
        $project = $this->selectionProject();
        $data = $this->selectionPayload($project);
        $this->postJson($this->path($project, 'choices'), $data)->assertOk()->assertJsonPath('state.count', 1)->assertJsonPath('state.version', 2);
        $this->postJson($this->path($project, 'choices'), $data)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('state.version', 2);
        $this->getJson($this->path($project))->assertOk()->assertJsonPath('photos.data.0.selected', true)->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'choices'), [...$data, 'file_id' => 'two'])->assertConflict();
        $this->assertDatabaseCount('choices', 1);
        $this->assertDatabaseCount('mutation_receipts', 1);
        $this->actingAs($project->photographer)->get('/projects/'.$project->id)->assertOk();
        $this->assertDatabaseHas('choices', ['project_id' => $project->id, 'file_id' => 'one', 'name' => 'same.jpg']);
    }

    public function test_stale_edits_and_stale_review_are_rejected_without_erasing_choices(): void
    {
        $project = $this->selectionProject();
        $review = $this->confirmPayload($project);
        $stale = $this->selectionPayload($project, 'two');
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $this->postJson($this->path($project, 'choices'), $stale)->assertConflict()->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'confirm'), $review)->assertConflict();
        $this->assertDatabaseCount('selection_confirmations', 0);
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_allowance_and_eligibility_are_server_owned_and_zero_cannot_confirm(): void
    {
        $project = $this->selectionProject(1);
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertUnprocessable();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'foreign-photo'))->assertNotFound();
        $this->postJson($this->path($project, 'choices'), [...$this->selectionPayload($project), 'allowance' => 999])->assertOk();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'two'))->assertUnprocessable()->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'one', false))->assertOk()->assertJsonPath('state.count', 0);
        $this->assertDatabaseCount('choices', 0);
    }

    public function test_one_of_two_can_confirm_repeat_is_idempotent_and_snapshot_never_changes(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $confirm = $this->confirmPayload($project);
        $id = $this->postJson($this->path($project, 'confirm'), $confirm)->assertOk()->assertJsonPath('state.locked', true)->json('result.confirmationId');
        $this->postJson($this->path($project, 'confirm'), $confirm)->assertOk()->assertJsonPath('result.confirmationId', $id);
        $this->postJson($this->path($project, 'confirm'), [...$confirm, 'operation_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('result.confirmationId', $id);
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'two'))->assertForbidden();
        $this->assertDatabaseCount('selection_confirmations', 1);
        $this->assertDatabaseCount('confirmation_choices', 1);
        $this->assertDatabaseHas('confirmation_choices', ['confirmation_id' => $id, 'file_id' => 'one', 'name' => 'same.jpg']);
    }

    public function test_photographer_reopen_preserves_history_increments_epoch_and_denies_old_clients(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertOk();
        $old = $this->selectionPayload($project, 'two');
        $version = $project->selection_version;
        $this->actingAs(User::factory()->create())->post('/projects/'.$project->id.'/selection/reopen', ['epoch' => 1, 'version' => $version])->assertNotFound();
        $this->actingAs($project->photographer)->post('/projects/'.$project->id.'/selection/reopen', ['epoch' => 1, 'version' => $version])->assertRedirect();
        $this->getJson($this->path($project))->assertOk()->assertJsonPath('state.epoch', 2)->assertJsonPath('state.locked', false)->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'choices'), $old)->assertConflict();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'one', false))->assertOk();
        $this->assertDatabaseCount('confirmation_choices', 1);
        $this->assertDatabaseCount('selection_confirmations', 1);
        $this->assertDatabaseCount('choices', 0);
    }

    public function test_session_spanning_account_expiry_becomes_read_only_and_renewal_does_not_unlock_confirmation(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $pending = $this->selectionPayload($project, 'two');
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()]);
        $this->postJson($this->path($project, 'choices'), $pending)->assertForbidden()->assertJsonPath('state.accountState', 'expired')->assertJsonPath('state.canWrite', false);
        $this->getJson($this->path($project))->assertOk()->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertForbidden();
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()->addDay()]);
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertOk();
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()]);
        $this->actingAs($project->photographer)->post('/projects/'.$project->id.'/selection/reopen', ['epoch' => 1, 'version' => $project->fresh()->selection_version])->assertNotFound();
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()->addDay()]);
        $this->getJson($this->path($project))->assertOk()->assertJsonPath('state.locked', true)->assertJsonPath('state.canWrite', false);
    }

    public function test_deadline_suspension_archive_rotation_and_unpublication_deny_new_requests(): void
    {
        $project = $this->selectionProject();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $entry = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->firstOrFail();
        $payload = $this->selectionPayload($project);
        foreach (['deadline', 'suspended', 'archived', 'rotated', 'unpublished'] as $gate) {
            $owner = $project->photographer;
            if ($gate === 'deadline') {
                $module->update(['deadline' => now()]);
            }
            if ($gate === 'suspended') {
                $owner->forceFill(['suspended_at' => now()])->save();
            }
            if ($gate === 'archived') {
                $project->update(['archived_at' => now()]);
            }
            if ($gate === 'rotated') {
                $project->update(['share_digest' => hash('sha256', random_bytes(32))]);
            }
            if ($gate === 'unpublished') {
                $module->update(['published' => false]);
            }
            $this->getJson($this->path($project))->assertNotFound();
            $this->postJson($this->path($project, 'choices'), $payload)->assertNotFound();
            $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertNotFound();
            $this->get($this->path($project, 'preview/'.$entry->id), ['Range' => 'bytes=0-10'])->assertNotFound();
            $module->update(['deadline' => null, 'published' => true]);
            $owner->forceFill(['suspended_at' => null])->save();
            $project->update(['archived_at' => null, 'share_digest' => hash('sha256', $project->share_token)]);
        }
        $this->assertDatabaseCount('choices', 0);
    }

    public function test_password_sessions_are_scoped_and_invalidated_for_data_mutation_and_media(): void
    {
        $project = $this->selectionProject();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $module->update(['password_hash' => Hash::make('SyntheticPassword123')]);
        $data = $this->selectionPayload($project);
        $this->getJson($this->path($project))->assertNotFound();
        $this->postJson($this->path($project, 'choices'), $data)->assertNotFound();
        $this->withSession(['gallery.'.$module->id => $module->gate_version])->getJson($this->path($project))->assertOk();
        $module->increment('gate_version');
        $this->getJson($this->path($project))->assertNotFound();
        $this->postJson('/g/'.$project->share_token.'/selection/password', ['gallery_password' => 'SyntheticPassword123'])->assertRedirect();
        $this->postJson($this->path($project, 'choices'), $data)->assertOk();
        $other = $this->selectionProject();
        $otherModule = $other->modules()->where('type', 'selection')->firstOrFail();
        $otherModule->update(['password_hash' => Hash::make('SyntheticPassword123')]);
        $this->getJson($this->path($other))->assertNotFound();
    }

    public function test_preview_is_same_origin_scoped_and_provider_failure_is_safe(): void
    {
        $project = $this->selectionProject();
        $other = $this->selectionProject();
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        $entry = CatalogueEntry::where('revision_id', $revision)->where('kind', 'photo')->firstOrFail();
        $drive = \Mockery::mock(DriveSource::class);
        $drive->shouldReceive('original')->once()->andReturn(Utils::streamFor('original-one'));
        $this->app->instance(DriveSource::class, $drive);
        $this->get($this->path($other, 'preview/'.$entry->id))->assertNotFound();
        $response = $this->get($this->path($project, 'preview/'.$entry->id).'?original=1');
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame('original-one', $response->streamedContent());
        $thumbnails = \Mockery::mock(DrivePreviewSource::class);
        $thumbnails->shouldReceive('thumbnail')->once()->andReturn(new PreviewImage(Utils::streamFor('thumbnail'), 'image/png', 9));
        $this->app->instance(DrivePreviewSource::class, $thumbnails);
        $thumbnail = $this->get($this->path($project, 'preview/'.$entry->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('thumbnail', $thumbnail->streamedContent());
        $drive = \Mockery::mock(DriveSource::class);
        $drive->shouldReceive('original')->andThrow(new ProviderFailure('drive_download_forbidden'));
        $this->app->instance(DriveSource::class, $drive);
        $this->getJson($this->path($project, 'preview/'.$entry->id).'?original=1')->assertStatus(502)->assertDontSee('drive_download_forbidden');
        $json = $this->getJson($this->path($project))->assertOk()->getContent();
        $this->assertStringNotContainsString('google.com', $json);
        $this->assertStringNotContainsString('resourceKey', $json);
    }

    public function test_missing_choice_is_retained_in_review_and_blocks_confirmation_until_removed(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        CatalogueEntry::where('revision_id', $module->published_revision_id)->where('file_id', 'one')->delete();
        $this->getJson($this->path($project).'?review=1')->assertOk()->assertJsonPath('photos.data.0.entry_id', null)->assertJsonPath('state.count', 1);
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertUnprocessable();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project, 'one', false))->assertOk();
        $this->assertDatabaseCount('choices', 0);
    }

    public function test_refresh_invalidates_review_even_when_selection_version_is_unchanged(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $data = $this->confirmPayload($project);
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $revision = CatalogueRevision::findOrFail($module->published_revision_id)->replicate();
        $revision->save();
        $module->update(['published_revision_id' => $revision->id]);
        $this->postJson($this->path($project, 'confirm'), $data)->assertConflict();
    }

    public function test_reopen_does_not_bypass_deadline_and_stale_reopen_cannot_unlock_a_later_epoch(): void
    {
        $project = $this->selectionProject();
        $this->postJson($this->path($project, 'choices'), $this->selectionPayload($project))->assertOk();
        $this->postJson($this->path($project, 'confirm'), $this->confirmPayload($project))->assertOk();
        $data = ['epoch' => 1, 'version' => $project->fresh()->selection_version];
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $module->update(['deadline' => now()]);
        $this->actingAs($project->photographer)->post('/projects/'.$project->id.'/selection/reopen', $data)->assertForbidden();
        $module->update(['deadline' => null]);
        $this->post('/projects/'.$project->id.'/selection/reopen', $data)->assertRedirect();
        $this->post('/projects/'.$project->id.'/selection/reopen', $data)->assertConflict();
    }

    public function test_large_catalogue_and_duplicate_folder_names_are_paginated_by_stable_identity(): void
    {
        $project = $this->selectionProject();
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        $folder = CatalogueEntry::where('revision_id', $revision)->where('file_id', 'nested')->firstOrFail();
        $duplicate = $folder->replicate();
        $duplicate->file_id = 'other-nested';
        $duplicate->save();
        for ($i = 0; $i < 75; $i++) {
            $entry = CatalogueEntry::where('revision_id', $revision)->where('file_id', 'one')->firstOrFail()->replicate();
            $entry->file_id = 'page-photo-'.$i;
            $entry->parent_file_id = $duplicate->file_id;
            $entry->save();
        }
        $this->getJson($this->path($project))->assertOk()->assertJsonCount(36, 'photos.data')->assertJsonPath('photos.total', 77);
        $this->getJson($this->path($project).'?page=3')->assertOk()->assertJsonCount(5, 'photos.data');
        $this->getJson($this->path($project).'?folder='.$folder->id)->assertOk()->assertJsonPath('photos.total', 2);
        $this->getJson($this->path($project).'?folder='.$duplicate->id)->assertOk()->assertJsonCount(36, 'photos.data')->assertJsonPath('photos.total', 75);
        $other = $this->selectionProject();
        $this->getJson($this->path($other).'?folder='.$folder->id)->assertNotFound();
    }

    public function test_failed_receipt_insert_rolls_back_choice_and_version_together(): void
    {
        $project = $this->selectionProject();
        $request = Request::create('/', 'POST');
        $request->setLaravelSession(app('session')->driver());
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "mutation_receipts"')) {
                throw new \RuntimeException('Synthetic receipt failure');
            }
        });
        try {
            app(ManageSelection::class)->client($request, $project->share_token, $this->selectionPayload($project));
            $this->fail('Receipt failure must roll back.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('choices', 0);
            $this->assertDatabaseCount('mutation_receipts', 0);
            $this->assertSame(1, $project->fresh()->selection_version);
        }
    }
}
