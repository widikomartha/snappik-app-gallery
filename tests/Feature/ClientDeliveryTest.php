<?php

namespace Tests\Feature;

use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveRangeSource;
use App\Contracts\DriveSource;
use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\Project;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DeliveryFixture;
use Tests\Support\DriveFixtures as F;
use Tests\TestCase;

class ClientDeliveryTest extends TestCase
{
    use DeliveryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function base(Project $project): string
    {
        return '/g/'.$project->share_token.'/delivery';
    }

    private function photo(Project $project): CatalogueEntry
    {
        return CatalogueEntry::where('revision_id', $project->modules()->where('type', 'delivery')->value('published_revision_id'))->where('kind', 'photo')->firstOrFail();
    }

    private function bind(array $responses): void
    {
        $source = new PublicDriveSource(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]), 'synthetic-key');
        foreach ([DriveSource::class, DriveRangeSource::class, DrivePreviewSource::class] as $contract) {
            $this->app->instance($contract, $source);
        }
    }

    public function test_download_matches_all_original_bytes_and_uses_private_attachment_headers(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $this->bind([F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($photo->metadata)]);
        $response = $this->get($this->base($project).'/media/'.$photo->id.'?download=1')->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Content-Length', '12')->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Disposition', 'attachment; filename=same.jpg')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame('original-one', $response->streamedContent());
        $this->assertSame(md5('original-one'), md5($response->streamedContent()));
    }

    public function test_passwords_deadlines_and_account_expiry_are_independent(): void
    {
        $project = $this->deliveryProject();
        $selection = $project->modules()->where('type', 'selection')->firstOrFail();
        $delivery = $project->modules()->where('type', 'delivery')->firstOrFail();
        $selection->update(['password_hash' => Hash::make('Selection123')]);
        $delivery->update(['password_hash' => Hash::make('Delivery123')]);
        $this->post('/g/'.$project->share_token.'/selection/password', ['gallery_password' => 'Selection123'])->assertRedirect();
        $this->getJson($this->base($project).'/data')->assertNotFound();
        $this->post('/g/'.$project->share_token.'/delivery/password', ['gallery_password' => 'Selection123'])->assertSessionHasErrors('gallery_password');
        $this->post('/g/'.$project->share_token.'/delivery/password', ['gallery_password' => 'Delivery123'])->assertRedirect();
        $selection->update(['deadline' => now()]);
        AccountAccess::whereKey($project->user_id)->update(['access_ends_at' => now()]);
        $this->getJson('/g/'.$project->share_token.'/selection/data')->assertNotFound();
        $this->getJson($this->base($project).'/data')->assertOk()->assertJsonPath('photos.total', 2);
        $photo = $this->photo($project);
        $this->bind([F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($photo->metadata)]);
        $response = $this->get($this->base($project).'/media/'.$photo->id.'?download=1')->assertOk();
        $this->assertSame('original-one', $response->streamedContent());
        $delivery->update(['deadline' => now()]);
        $this->get($this->base($project).'/media/'.$photo->id, ['Range' => 'bytes=0-2'])->assertNotFound();
    }

    public function test_new_requests_recheck_every_gate_before_provider_work(): void
    {
        $project = $this->deliveryProject();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $photo = $this->photo($project);
        $base = $this->base($project);
        $this->bind([]); // Any unexpected upstream request fails the test's expected 404.
        foreach (['deadline', 'password', 'suspension', 'archive', 'capability', 'publication'] as $gate) {
            match ($gate) {
                'deadline' => $module->update(['deadline' => now()]),
                'password' => $module->update(['password_hash' => Hash::make('New123')]),
                'suspension' => $project->photographer->forceFill(['suspended_at' => now()])->save(),
                'archive' => $project->update(['archived_at' => now()]),
                'capability' => $project->update(['share_digest' => hash('sha256', random_bytes(32))]),
                'publication' => $module->update(['published' => false]),
            };
            $this->get($base)->assertNotFound();
            $this->getJson($base.'/data')->assertNotFound();
            $this->get($base.'/media/'.$photo->id.'?download=1', ['Range' => 'bytes=0-2'])->assertNotFound();
            $this->head($base.'/media/'.$photo->id.'?download=1')->assertNotFound();
            $module->update(['deadline' => null, 'password_hash' => null, 'published' => true]);
            $project->photographer->forceFill(['suspended_at' => null])->save();
            $project->update(['archived_at' => null, 'share_digest' => hash('sha256', $project->share_token)]);
        }
    }

    public function test_other_projects_selection_files_and_old_revisions_cannot_be_substituted(): void
    {
        $project = $this->deliveryProject();
        $other = $this->deliveryProject();
        $photo = $this->photo($project);
        $this->bind([]);
        $this->get($this->base($other).'/media/'.$photo->id.'?download=1')->assertNotFound();
        $selection = CatalogueEntry::where('revision_id', $project->modules()->where('type', 'selection')->value('published_revision_id'))->where('kind', 'photo')->firstOrFail();
        $this->get($this->base($project).'/media/'.$selection->id)->assertNotFound();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $revision = CatalogueRevision::findOrFail($module->published_revision_id)->replicate();
        $revision->save();
        $module->update(['published_revision_id' => $revision->id]);
        $this->get($this->base($project).'/media/'.$photo->id)->assertNotFound();
    }

    public function test_range_bytes_and_if_range_full_fallback(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $url = $this->base($project).'/media/'.$photo->id.'?download=1';
        foreach (['bytes=2-5' => [2, 5], 'bytes=9-' => [9, 11], 'bytes=-3' => [9, 11], 'bytes=0-9999999999999999999999' => [0, 11]] as $range => [$start, $end]) {
            $bytes = substr('original-one', $start, $end - $start + 1);
            $this->bind([F::json($photo->metadata), new Response(206, ['Content-Type' => 'image/jpeg', 'Content-Range' => "bytes $start-$end/12"], $bytes), F::json($photo->metadata)]);
            $response = $this->get($url, ['Range' => $range])->assertStatus(206)->assertHeader('Content-Range', "bytes $start-$end/12");
            $this->assertSame($bytes, $response->streamedContent());
            $etag = $response->headers->get('ETag');
        }
        foreach (['"stale"', 'W/'.$etag, 'Thu, 22 Oct 2026 00:00:00 GMT'] as $validator) {
            $this->bind([F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($photo->metadata)]);
            $response = $this->get($url, ['Range' => 'bytes=1-2', 'If-Range' => $validator])->assertOk();
            $this->assertSame('original-one', $response->streamedContent());
        }
        $this->bind([F::json($photo->metadata), new Response(206, ['Content-Type' => 'image/jpeg', 'Content-Range' => 'bytes 1-2/12'], 'ri'), F::json($photo->metadata)]);
        $this->assertSame('ri', $this->get($url, ['Range' => 'bytes=1-2', 'If-Range' => $etag])->assertStatus(206)->streamedContent());
        foreach (['bytes=12-', 'bytes=-0', 'bytes=5-2', 'bytes=999999999999999999999-'] as $range) {
            $this->getJson($url, ['Range' => $range])->assertStatus(416)->assertHeader('Content-Range', 'bytes */12');
        }
    }

    public function test_provider_revocation_html_change_and_truncation_are_visible_text_failures(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $failures = [[F::json(['error' => []], 404)], [F::json([...$photo->metadata, 'capabilities' => ['canDownload' => false]])],
            [F::json([...$photo->metadata, 'version' => '8'])],
            [F::json($photo->metadata), new Response(403, ['Content-Type' => 'text/html'], '<html>private-provider-marker</html>')],
            [F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], '<html>login!</html>')],
            [F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], 'short')]];
        foreach ($failures as $responses) {
            $this->bind($responses);
            $response = $this->get($this->base($project).'/media/'.$photo->id.'?download=1')->assertStatus(502)
                ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('hubungi fotografer')->assertDontSee('private-provider-marker');
            $this->assertFalse($response->headers->has('Content-Disposition'));
        }
    }

    public function test_safe_unicode_filename_and_head_do_not_fetch_media(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $metadata = [...$photo->metadata, 'name' => "../é\\unsafe\r\n.html"];
        $photo->update(['metadata' => $metadata]);
        $this->bind([F::json($metadata)]);
        $response = $this->head($this->base($project).'/media/'.$photo->id.'?download=1')->assertOk()->assertHeader('Content-Length', '12');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename*=', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString('\\', $disposition);
        $this->assertStringContainsString('.jpg', $disposition);
    }

    public function test_delivery_thumbnails_use_the_proxy_and_original_enlargement_stays_inline(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $this->bind([F::json([...$photo->metadata, 'thumbnailLink' => 'https://lh3.googleusercontent.com/synthetic-image']),
            new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => '9'], 'thumbnail')]);
        $response = $this->get($this->base($project).'/media/'.$photo->id.'?thumbnail=1')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('thumbnail', $response->streamedContent());
        $this->bind([F::json($photo->metadata), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($photo->metadata)]);
        $response = $this->get($this->base($project).'/media/'.$photo->id)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=same.jpg');
        $this->assertSame('original-one', $response->streamedContent());
    }

    public function test_safe_original_extensions_are_preserved_and_device_names_are_sanitized(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        foreach (['Portrait.JPEG' => 'Portrait.JPEG', 'CON.jpg' => '_CON.jpg', 'photo.exe' => 'photo.exe.jpg'] as $name => $safe) {
            $metadata = [...$photo->metadata, 'name' => $name];
            $photo->update(['metadata' => $metadata]);
            $this->bind([F::json($metadata)]);
            $this->head($this->base($project).'/media/'.$photo->id.'?download=1')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename='.$safe);
        }
    }

    public function test_late_checksum_failure_cannot_complete_the_declared_download(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        $bytes = str_repeat('synthetic-original-', 20000);
        $metadata = F::photo('finished-one', $bytes);
        $photo->update(['metadata' => $metadata]);
        $bad = substr($bytes, 0, -1).'!';
        $this->bind([F::json($metadata), new Response(200, ['Content-Type' => 'image/jpeg'], $bad)]);
        $response = $this->get($this->base($project).'/media/'.$photo->id.'?download=1')->assertOk()->assertHeader('Content-Length', (string) strlen($bytes));
        $sent = $response->streamedContent();
        $this->assertGreaterThan(0, strlen($sent));
        $this->assertLessThan(strlen($bytes), strlen($sent));
        $this->assertSame(substr($bytes, 0, strlen($sent)), $sent);
    }

    public function test_authorized_response_can_finish_but_password_generation_blocks_the_next_range(): void
    {
        $project = $this->deliveryProject();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $module->update(['password_hash' => Hash::make('Delivery123')]);
        $photo = $this->photo($project);
        $bytes = str_repeat('synthetic-original-', 20000);
        $metadata = F::photo('finished-one', $bytes);
        $photo->update(['metadata' => $metadata]);
        $this->bind([F::json($metadata), new Response(200, ['Content-Type' => 'image/jpeg'], $bytes), F::json($metadata)]);
        $url = $this->base($project).'/media/'.$photo->id.'?download=1';
        $response = $this->withSession(['gallery.'.$module->id => $module->gate_version])->get($url)->assertOk();
        $module->increment('gate_version');
        $this->assertSame($bytes, $response->streamedContent());
        $this->get($url, ['Range' => 'bytes=1-2'])->assertNotFound();
    }

    public function test_delivery_is_paginated_and_folder_ids_are_scoped(): void
    {
        $project = $this->deliveryProject();
        $photo = $this->photo($project);
        for ($i = 0; $i < 75; $i++) {
            $copy = $photo->replicate();
            $copy->file_id = 'large-'.$i;
            $copy->save();
        }
        $this->getJson($this->base($project).'/data')->assertOk()->assertJsonCount(36, 'photos.data')->assertJsonPath('photos.total', 77)->assertDontSee('resourceKey');
        $this->getJson($this->base($project).'/data?page=3')->assertOk()->assertJsonCount(5, 'photos.data');
        $folder = CatalogueEntry::where('revision_id', $photo->revision_id)->where('kind', 'folder')->whereNotNull('parent_file_id')->firstOrFail();
        $this->getJson($this->base($project).'/data?folder='.$folder->id)->assertOk()->assertJsonPath('photos.total', 77);
        $other = $this->deliveryProject();
        $this->getJson($this->base($other).'/data?folder='.$folder->id)->assertNotFound();
    }
}
