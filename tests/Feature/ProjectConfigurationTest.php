<?php

namespace Tests\Feature;

use App\Actions\Auth\VerifyAccount;
use App\Actions\Projects\ManageProject;
use App\Contracts\DriveSource;
use App\Jobs\ImportCatalogue;
use App\Models\AccountAccess;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Models\User;
use App\Services\Drive\DriveItem;
use App\Services\Drive\ImportProjectCatalogue;
use App\Services\ProviderFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogueDrive;
use Tests\Support\DriveFixtures as F;
use Tests\TestCase;

class ProjectConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private CatalogueDrive $drive;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->owner = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($this->owner);
        $this->owner->refresh();
        $this->drive = new CatalogueDrive;
        $this->app->instance(DriveSource::class, $this->drive);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Synthetic project', 'allowance' => 2,
            'selection_source' => 'https://drive.google.com/drive/folders/select-root', 'delivery_source' => ''], $overrides);
    }

    private function project(array $overrides = []): Project
    {
        return app(ManageProject::class)->create($this->owner, $this->data($overrides));
    }

    private function import(Project $project, string $type = 'selection'): CatalogueRevision
    {
        $id = $project->modules()->where('type', $type)->firstOrFail()->requested_revision_id;
        (new ImportCatalogue($id))->handle(app(ImportProjectCatalogue::class));

        return CatalogueRevision::findOrFail($id);
    }

    private function command(Project $project, string $command, ?string $module = null)
    {
        return $this->actingAs($this->owner)->post('/projects/'.$project->id.'/commands', [
            'version' => $project->fresh()->version, 'command' => $command, 'module' => $module,
        ]);
    }

    public function test_create_without_contact_queues_only_ids_encrypts_secrets_and_ignores_owner_injection(): void
    {
        $this->actingAs($this->owner)->post('/projects', $this->data(['user_id' => 999, 'selection_password' => 'SyntheticSecret123',
            'selection_source' => 'https://drive.google.com/drive/folders/select-root?resourcekey=synthetic-key']))->assertRedirect();
        $project = Project::firstOrFail();
        $this->assertSame($this->owner->id, $project->user_id);
        $this->assertNull($project->contact);
        $this->assertSame(2, $project->modules()->count());
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $this->assertTrue(Hash::check('SyntheticSecret123', $module->password_hash));
        $raw = DB::table('gallery_modules')->where('id', $module->id)->first();
        $this->assertStringNotContainsString('synthetic-key', $raw->source);
        $this->assertStringNotContainsString($project->share_token, DB::table('projects')->value('share_token'));
        $this->assertArrayNotHasKey('share_token', $project->toArray());
        Queue::assertPushed(ImportCatalogue::class, fn ($job) => $job->revisionId === $module->requested_revision_id && $job->connection === 'catalogues' && $job->queue === 'catalogues');
        $this->assertDatabaseCount('catalogue_revisions', 1);
    }

    public function test_nested_only_root_pagination_unsupported_and_shortcuts_are_complete_and_bounded_in_page_state(): void
    {
        $project = $this->project();
        $this->drive->pages = ['select-root:' => [[F::folder('nested', 'nested-key')]],
            'nested:' => [[F::photo('one'), ['id' => 'raw', 'name' => 'camera.raw', 'mimeType' => 'image/x-raw']], 'page-two'],
            'nested:page-two' => [[F::photo('two'), ['id' => 'shortcut', 'name' => 'Outside shortcut', 'mimeType' => DriveItem::SHORTCUT]]]];
        $revision = $this->import($project);
        $this->assertSame('complete', $revision->state);
        $this->assertCount(6, $revision->entries()->get());
        $this->assertSame(2, $revision->entries()->where('kind', 'photo')->count());
        $this->assertSame(3, DB::table('catalogue_pages')->count());
        $this->assertContains(['nested', 'nested-key'], $this->drive->calls);
        $this->assertNotNull($revision->manifest_digest);
        $this->actingAs($this->owner)->get('/projects/'.$project->id)->assertInertia(fn (Assert $page) => $page
            ->where('project.modules.0.unsupported', 2)->where('project.modules.0.photos', 2));
        $this->command($project, 'publish', 'selection')->assertSessionHasNoErrors();
        $this->assertTrue($project->modules()->where('type', 'selection')->first()->published);
    }

    public function test_failed_second_page_keeps_previous_revision_and_choices_and_marks_stale(): void
    {
        $project = $this->project();
        $this->drive->pages = ['select-root:' => [[F::photo('one')]]];
        $old = $this->import($project);
        DB::table('choices')->insert(['project_id' => $project->id, 'file_id' => 'one', 'name' => 'same.jpg', 'path' => 'same.jpg', 'revision_id' => $old->id]);
        $this->command($project, 'publish', 'selection');
        $this->command($project, 'refresh', 'selection');
        $this->drive->pages = ['select-root:' => [[F::photo('two')], 'next'], 'select-root:next' => new ProviderFailure('drive_source_unavailable')];
        $failed = $this->import($project);
        $this->assertSame('failed', $failed->state);
        $this->assertSame($old->id, $project->modules()->where('type', 'selection')->first()->published_revision_id);
        $this->actingAs($this->owner)->get('/projects/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.modules.0.stale', true)->where('project.modules.0.missingChoices', 0));
        $this->assertDatabaseCount('choices', 1);
        $this->command($project, 'refresh', 'selection');
        $this->drive->pages = ['select-root:' => [[F::photo('two')]]];
        $this->assertSame('complete', $this->import($project)->state);
        $this->actingAs($this->owner)->get('/projects/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.modules.0.missingChoices', 1)->where('project.modules.0.missingNames.0', 'same.jpg'));
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_source_overlap_is_rejected_for_same_root_nested_root_and_shared_photo_identity(): void
    {
        $this->actingAs($this->owner)->post('/projects', $this->data(['delivery_source' => $this->data()['selection_source']]))->assertSessionHasErrors('delivery_source');
        $this->assertDatabaseCount('projects', 0);
        $project = $this->project(['delivery_source' => 'https://drive.google.com/drive/folders/delivery-root']);
        $this->drive->pages = ['select-root:' => [[F::photo('shared')]], 'delivery-root:' => [[F::photo('shared')]]];
        $this->assertSame('complete', $this->import($project)->state);
        $this->assertSame('catalogue_sources_overlap', $this->import($project, 'delivery')->failure);
        $this->command($project, 'refresh', 'delivery');
        $this->drive->pages['delivery-root:'] = [[F::folder('select-root')]];
        $this->assertSame('catalogue_sources_overlap', $this->import($project, 'delivery')->failure);
        $this->assertNull($project->modules()->where('type', 'delivery')->first()->published_revision_id);
    }

    public function test_locked_selection_source_and_allowance_below_choices_are_rejected_but_delivery_can_change(): void
    {
        $project = $this->project();
        $this->drive->pages = ['select-root:' => [[F::photo('one'), F::photo('two')]]];
        $revision = $this->import($project);
        foreach (['one', 'two'] as $id) {
            DB::table('choices')->insert(['project_id' => $project->id, 'file_id' => $id, 'name' => 'same.jpg', 'path' => 'same.jpg', 'revision_id' => $revision->id]);
        }
        $project->update(['selection_locked_at' => now()]);
        $this->actingAs($this->owner)->put('/projects/'.$project->id, $this->data(['version' => $project->version, 'allowance' => 1]))->assertSessionHasErrors('allowance');
        $this->put('/projects/'.$project->id, $this->data(['version' => $project->version, 'selection_source' => 'https://drive.google.com/drive/folders/new-root']))->assertSessionHasErrors('selection_source');
        $this->put('/projects/'.$project->id, $this->data(['version' => $project->version, 'delivery_source' => 'https://drive.google.com/drive/folders/delivery-root']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('choices', 2);
    }

    public function test_cross_tenant_project_and_commands_are_denied_and_job_rechecks_account_state(): void
    {
        $project = $this->project();
        $other = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($other);
        $other->refresh();
        $this->actingAs($other)->get('/projects/'.$project->id)->assertNotFound();
        $this->put('/projects/'.$project->id, $this->data(['version' => 1]))->assertNotFound();
        $this->post('/projects/'.$project->id.'/commands', ['version' => 1, 'command' => 'archive'])->assertNotFound();
        $other->forceFill(['role' => 'admin'])->save();
        $this->get('/projects/'.$project->id)->assertNotFound();
        AccountAccess::findOrFail($this->owner->id)->forceFill(['access_ends_at' => now()])->save();
        $this->actingAs($this->owner)->get('/projects/'.$project->id)->assertOk();
        $this->put('/projects/'.$project->id, $this->data(['version' => 1]))->assertNotFound();
        $this->assertSame('catalogue_access_closed', $this->import($project)->failure);
        $this->assertSame([], $this->drive->calls);
        $this->owner->forceFill(['suspended_at' => now()])->save();
        $this->get('/projects/'.$project->id)->assertNotFound();
        $this->get('/projects')->assertForbidden();
    }

    public function test_stale_forms_superseded_imports_and_premature_publication_are_rejected(): void
    {
        $project = $this->project();
        $old = $project->modules()->where('type', 'selection')->first()->requested_revision_id;
        $this->command($project, 'publish', 'selection')->assertSessionHasErrors('project');
        $this->command($project, 'refresh', 'selection')->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put('/projects/'.$project->id, $this->data(['version' => 1]))->assertSessionHasErrors('project');
        app(ImportProjectCatalogue::class)->handle($old);
        $this->assertSame('superseded', CatalogueRevision::find($old)->state);
        $this->assertSame([], $this->drive->calls);
    }

    public function test_unsafe_url_and_invalid_deadline_or_allowance_fail_without_creating_slots(): void
    {
        foreach (['http://127.0.0.1/private', 'https://drive.google.com.evil.test/drive/folders/root', 'https://drive.google.com:443/drive/folders/root'] as $source) {
            $this->actingAs($this->owner)->post('/projects', $this->data(['selection_source' => $source]))->assertSessionHasErrors('selection_source');
        }
        $this->post('/projects', $this->data(['selection_deadline' => '2026-10-23T12:00', 'allowance' => 0]))->assertSessionHasErrors(['selection_deadline', 'allowance']);
        foreach ([str_repeat('é', 37), "bad\0password"] as $password) {
            $this->post('/projects', $this->data(['selection_password' => $password]))->assertSessionHasErrors('selection_password');
        }
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_diagnostics_paginate_all_excluded_files_and_are_tenant_scoped(): void
    {
        $project = $this->project();
        $files = [F::photo('one')];
        for ($i = 0; $i < 25; $i++) {
            $files[] = ['id' => 'raw-'.$i, 'name' => 'unsupported-'.$i.'.raw', 'mimeType' => 'image/x-raw'];
        }
        $this->drive->pages = ['select-root:' => [$files]];
        $this->import($project);
        $url = '/projects/'.$project->id.'/diagnostics?module=selection&kind=unsupported';
        $this->actingAs($this->owner)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('records.total', 25)->has('records.data', 20));
        $this->get($url.'&page=2')->assertInertia(fn (Assert $page) => $page->has('records.data', 5));
        $other = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($other);
        $this->actingAs($other->fresh())->get($url)->assertNotFound();
    }

    public function test_checkpoint_resume_skips_committed_pages_and_duplicate_jobs_do_no_provider_work(): void
    {
        $project = $this->project();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $revision = CatalogueRevision::findOrFail($module->requested_revision_id);
        $revision->update(['state' => 'importing']);
        $folder = $revision->entries()->create(['file_id' => 'select-root', 'kind' => 'folder', 'name' => 'select-root', 'path' => '',
            'metadata' => F::folder('select-root'), 'page_token' => 'resume-page']);
        DB::table('catalogue_pages')->insert(['entry_id' => $folder->id, 'token_digest' => hash('sha256', '')]);
        $photo = F::photo('before-crash');
        $revision->entries()->create(['file_id' => $photo['id'], 'kind' => 'photo', 'name' => $photo['name'], 'path' => $photo['name'], 'metadata' => $photo, 'bytes' => (int) $photo['size']]);
        $this->drive->pages = ['select-root:resume-page' => [[F::photo('after-crash')]]];
        $this->assertSame('complete', $this->import($project)->state);
        $this->assertSame(2, $revision->entries()->where('kind', 'photo')->count());
        $this->drive->calls = [];
        $this->import($project);
        $this->assertSame([], $this->drive->calls);
    }

    public function test_cycle_empty_source_and_expiry_during_import_never_publish_partial_data(): void
    {
        $project = $this->project();
        $this->drive->pages = ['select-root:' => [[F::photo('one')], 'repeat'], 'select-root:repeat' => [[], 'repeat']];
        $this->assertSame('drive_pagination_cycle', $this->import($project)->failure);
        $this->command($project, 'refresh', 'selection');
        $this->drive->pages = ['select-root:' => [[]]];
        $this->assertSame('drive_no_supported_photos', $this->import($project)->failure);
        $this->command($project, 'refresh', 'selection');
        $this->drive->pages = ['select-root:' => [[F::photo('one')]]];
        $this->drive->beforePage = function () {
            AccountAccess::findOrFail($this->owner->id)->forceFill(['access_ends_at' => now()])->save();
        };
        $this->assertSame('catalogue_access_closed', $this->import($project)->failure);
        $this->assertNull($project->modules()->where('type', 'selection')->first()->published_revision_id);
    }

    public function test_trial_archive_restore_is_retained_and_cannot_bypass_three_draft_slots(): void
    {
        $first = $this->project();
        $this->project();
        $this->project();
        $this->actingAs($this->owner)->post('/projects', $this->data())->assertSessionHasErrors('project');
        $this->command($first, 'archive')->assertSessionHasNoErrors();
        $this->project();
        $this->command($first, 'restore')->assertSessionHasErrors('project');
        $this->assertDatabaseCount('projects', 4);
        $this->assertNotNull($first->fresh()->archived_at);
        $this->assertSame(3, Project::whereNull('archived_at')->count());
    }

    public function test_paid_metadata_exceeds_all_rejected_thresholds_without_quotas(): void
    {
        AccountAccess::findOrFail($this->owner->id)->forceFill(['has_paid_access' => true, 'access_ends_at' => now()->addDays(30)])->save();
        for ($i = 0; $i < 51; $i++) {
            $project = $this->project();
        }
        $this->assertDatabaseCount('projects', 51);
        $photos = [];
        for ($i = 0; $i < 2001; $i++) {
            $photos[] = array_replace(F::photo('photo-'.$i), ['size' => (string) (30 * 1024 * 1024)]);
        }
        $this->drive->pages = ['select-root:' => [array_slice($photos, 0, 1000), 'p2'],
            'select-root:p2' => [array_slice($photos, 1000, 1000), 'p3'], 'select-root:p3' => [array_slice($photos, 2000)]];
        $revision = $this->import($project);
        $this->assertSame('complete', $revision->state);
        $this->assertSame(2001, $revision->entries()->where('kind', 'photo')->count());
        $this->assertGreaterThan(20 * 1024 ** 3, (int) $revision->entries()->sum('bytes'));
        $this->assertGreaterThan(25 * 1024 ** 2, $revision->entries()->where('kind', 'photo')->first()->bytes);
    }

    public function test_capability_rotation_archive_password_generations_and_deadlines_are_independent(): void
    {
        $project = $this->project(['delivery_source' => 'https://drive.google.com/drive/folders/delivery-root',
            'selection_password' => 'SelectSecret123', 'delivery_password' => 'DeliverySecret123']);
        $this->drive->pages = ['select-root:' => [[F::photo('one')]], 'delivery-root:' => [[F::photo('two')]]];
        $this->import($project);
        $this->import($project, 'delivery');
        $this->command($project, 'publish', 'selection');
        $this->command($project, 'publish', 'delivery');
        $path = '/g/'.$project->share_token;
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertInertia(fn (Assert $page) => $page
            ->where('modules.0.locked', true)->where('modules.0.photos', null)->where('modules.1.locked', true));
        $this->from($path)->post($path.'/selection/password', ['gallery_password' => 'wrong'])->assertSessionHasErrors('gallery_password');
        $this->post($path.'/selection/password', ['gallery_password' => 'SelectSecret123'])->assertSessionHasNoErrors();
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('modules.0.locked', false)->where('modules.1.locked', true));
        $module = $project->modules()->where('type', 'selection')->first();
        $module->increment('gate_version');
        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('modules.0.locked', true));
        $module->update(['deadline' => now()]);
        AccountAccess::findOrFail($this->owner->id)->forceFill(['access_ends_at' => now()])->save();
        $this->get($path)->assertInertia(fn (Assert $page) => $page->has('modules', 1)->where('modules.0.type', 'delivery'));
        $this->post($path.'/selection/password', ['gallery_password' => 'SelectSecret123'])->assertNotFound();
        AccountAccess::findOrFail($this->owner->id)->forceFill(['access_ends_at' => now()->addDays(30)])->save();
        $this->command($project, 'rotate');
        $this->get($path)->assertNotFound();
        $new = '/g/'.$project->fresh()->share_token;
        $this->get($new)->assertOk();
        $this->command($project, 'archive');
        $this->get($new)->assertNotFound();
        $this->assertDatabaseCount('catalogue_entries', 4);
        $this->command($project, 'restore');
        $this->get($new)->assertOk();
        $this->owner->forceFill(['suspended_at' => now()])->save();
        $this->get($new)->assertNotFound();
    }
}
