<?php

namespace Tests\Feature;

use App\Actions\Selections\ManageSelection;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SelectionFixture;
use Tests\TestCase;

class PhotographerProgressTest extends TestCase
{
    use RefreshDatabase, SelectionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function choose(Project $project, string $file = 'one', bool $selected = true): void
    {
        $this->postJson('/g/'.$project->share_token.'/selection/choices', $this->selectionPayload($project, $file, $selected))->assertOk();
    }

    private function confirm(Project $project): string
    {
        $payload = array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]);

        return $this->postJson('/g/'.$project->share_token.'/selection/confirm', $payload)->assertOk()->json('result.confirmationId');
    }

    public function test_draft_confirm_reopen_and_later_edit_distinguish_current_state_from_immutable_history(): void
    {
        $project = $this->selectionProject();
        $url = '/projects/'.$project->id.'/results';
        $this->actingAs($project->photographer)->getJson($url.'/data')->assertOk()
            ->assertJsonPath('progress.status', 'not_started')->assertJsonPath('progress.updatedAt', null);
        $this->travel(1)->minutes();
        $this->choose($project);
        $acknowledged = $project->fresh()->selection_updated_at->toIso8601String();
        $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('projects.data.0.status', 'selecting')
            ->assertJsonPath('projects.data.0.count', 1)->assertJsonPath('projects.data.0.allowance', 2)
            ->assertJsonPath('projects.data.0.updatedAt', $acknowledged)->assertJsonPath('projects.data.0.confirmationCount', 0);
        // Configuration timestamps cannot pretend a client selection was just saved.
        $this->travel(1)->minutes();
        $project->fresh()->update(['name' => 'Renamed project']);
        $this->getJson($url.'/data')->assertJsonPath('progress.updatedAt', $acknowledged)->assertJsonPath('records.data.0.name', 'same.jpg');
        $id = $this->confirm($project);
        $this->getJson($url.'/data')->assertJsonPath('progress.status', 'confirmed')->assertJsonPath('history.data.0.id', $id);
        app(ManageSelection::class)->reopen($project->photographer, $project->id,
            ['epoch' => 1, 'version' => $project->fresh()->selection_version]);
        $this->getJson($url.'/data')->assertJsonPath('progress.status', 'selecting')->assertJsonPath('progress.confirmedAt', null)
            ->assertJsonPath('progress.epoch', 2)->assertJsonPath('progress.confirmationCount', 1);
        $this->choose($project, 'one', false);
        $this->getJson($url.'/data?confirmation='.$id)->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('snapshot.id', $id);
        $this->getJson($url.'/data')->assertJsonPath('records.total', 0);
        $this->assertSame("same.jpg\n", $this->get($url.'/export?confirmation='.$id)->assertOk()->streamedContent());
    }

    public function test_expiry_closure_and_missing_originals_preserve_readable_copyable_history_without_mutation(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $id = $this->confirm($project);
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        CatalogueEntry::where('revision_id', $revision)->where('file_id', 'one')->delete();
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()]);
        $project->update(['archived_at' => now()]);
        $project->modules()->where('type', 'selection')->update(['deadline' => now(), 'published' => false]);
        $url = '/projects/'.$project->id.'/results';
        $this->actingAs($project->photographer)->getJson($url.'/data?confirmation='.$id)->assertOk()
            ->assertJsonPath('progress.status', 'confirmed')->assertJsonPath('progress.context', ['expired', 'archived', 'unpublished', 'deadline'])
            ->assertJsonPath('records.data.0.available', 0)->assertJsonPath('records.data.0.name', 'same.jpg');
        $response = $this->get($url.'/export?confirmation='.$id)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("same.jpg\n", $response->streamedContent());
        $this->postJson('/projects/'.$project->id.'/selection/reopen', ['epoch' => 1, 'version' => $project->fresh()->selection_version])->assertNotFound();
        $this->assertDatabaseCount('selection_confirmations', 1);
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_results_and_exports_scope_both_project_and_confirmation_and_suspension_hides_metadata(): void
    {
        $mine = $this->selectionProject();
        $other = $this->selectionProject();
        $this->choose($other);
        $foreign = $this->confirm($other);
        $this->actingAs($mine->photographer);
        foreach (['', '/data', '/export'] as $suffix) {
            $this->getJson('/projects/'.$other->id.'/results'.$suffix)->assertNotFound()->assertDontSee('same.jpg');
            $this->getJson('/projects/'.$mine->id.'/results'.$suffix.'?confirmation='.$foreign)->assertNotFound();
        }
        $this->getJson('/projects/data')->assertJsonPath('projects.total', 1)->assertJsonPath('projects.data.0.id', $mine->id);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/projects/'.$mine->id.'/results/export')->assertNotFound();
        $mine->photographer->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($mine->photographer)->getJson('/dashboard/data')->assertOk()->assertJsonPath('projects', null);
        $this->getJson('/projects/'.$mine->id.'/results/data')->assertNotFound();
        $this->getJson('/projects/'.$mine->id.'/results/export')->assertNotFound();
    }

    public function test_export_covers_every_page_and_chunk_disambiguates_names_and_escapes_line_breaks(): void
    {
        $project = $this->selectionProject(600);
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        $rows = [];
        for ($i = 0; $i < 520; $i++) {
            $name = $i === 0 || $i === 519 ? 'duplicate.jpg' : 'photo-'.$i.'.jpg';
            $rows[] = ['project_id' => $project->id, 'file_id' => 'export-'.$i, 'name' => $name,
                'path' => ($i === 0 ? 'first/' : 'last/').$name, 'revision_id' => $revision, 'created_at' => now(), 'updated_at' => now()];
        }
        $rows[] = [...$rows[0], 'file_id' => 'same-path'];
        $rows[] = [...$rows[0], 'file_id' => 'line-break', 'name' => "full\\original\r\nname.jpg", 'path' => "full\\original\r\nname.jpg"];
        DB::table('choices')->insert($rows);
        $url = '/projects/'.$project->id.'/results';
        $page = $this->actingAs($project->photographer)->getJson($url.'/data')->assertOk()->assertJsonCount(20, 'records.data')->assertJsonPath('records.total', 522);
        $this->assertStringNotContainsString('/data', $page->json('records.next_page_url'));
        $this->get($page->json('records.next_page_url'))->assertInertia(fn (Assert $page) => $page->component('photographer/results')->has('records.data', 20));
        $output = $this->get($url.'/export')->assertOk()->streamedContent();
        $lines = explode("\n", rtrim($output, "\n"));
        $this->assertCount(522, $lines);
        $this->assertSame('first/duplicate.jpg [ID: export-0]', $lines[0]);
        $this->assertSame('last/duplicate.jpg', $lines[519]);
        $this->assertSame('first/duplicate.jpg [ID: same-path]', $lines[520]);
        $this->assertSame('full\\\\original\\r\\nname.jpg', $lines[521]);
        $this->assertDatabaseCount('selection_confirmations', 0);
        $this->assertSame(1, $project->fresh()->selection_version);
    }

    public function test_failed_catalogue_refresh_is_stale_and_api_pagination_still_links_to_html_pages(): void
    {
        $project = $this->selectionProject();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        $failed = CatalogueRevision::findOrFail($module->published_revision_id)->replicate();
        $failed->state = 'failed';
        $failed->save();
        $module->update(['requested_revision_id' => $failed->id]);
        $this->actingAs($project->photographer)->get('/projects/'.$project->id.'/results')
            ->assertInertia(fn (Assert $page) => $page->component('photographer/results')->where('progress.catalogueStale', true));
        $this->getJson('/projects/data')->assertOk()->assertJsonPath('projects.data.0.catalogueStale', true);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('photographer/dashboard')->has('projects.data', 1));
    }

    public function test_stale_current_export_requires_refresh_but_immutable_snapshot_remains_available(): void
    {
        $project = $this->selectionProject();
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        $this->choose($project);
        $url = '/projects/'.$project->id.'/results/export';
        $this->actingAs($project->photographer)->get($url.'?version=1&revision='.$revision)->assertConflict();
        $this->assertSame("same.jpg\n", $this->get($url.'?version=2&revision='.$revision)->assertOk()->streamedContent());
        $this->get($url.'?version=2')->assertConflict();
    }

    public function test_confirmation_history_paginates_without_changing_the_selected_snapshot(): void
    {
        $project = $this->selectionProject();
        $this->choose($project);
        $first = null;
        for ($i = 0; $i < 12; $i++) {
            $id = $this->confirm($project);
            $first ??= $id;
            app(ManageSelection::class)->reopen($project->photographer, $project->id,
                ['epoch' => $project->fresh()->selection_epoch, 'version' => $project->fresh()->selection_version]);
        }
        $url = '/projects/'.$project->id.'/results/data?confirmation='.$first;
        $page = $this->actingAs($project->photographer)->getJson($url)->assertOk()
            ->assertJsonPath('history.total', 12)->assertJsonCount(10, 'history.data')->assertJsonPath('snapshot.id', $first);
        $this->assertStringNotContainsString('/data', $page->json('history.next_page_url'));
        $this->get($page->json('history.next_page_url'))->assertInertia(fn (Assert $page) => $page->component('photographer/results')
            ->has('history.data', 2)->where('snapshot.id', $first));
    }
}
