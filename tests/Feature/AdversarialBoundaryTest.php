<?php

namespace Tests\Feature;

use App\Actions\Billing\Checkout;
use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveRangeSource;
use App\Contracts\DriveSource;
use App\Models\CatalogueEntry;
use App\Services\Archives\DeliveryArchives;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\DeliveryFixture;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class AdversarialBoundaryTest extends TestCase
{
    use DeliveryFixture, RefreshDatabase;

    public function test_substituted_identifiers_cannot_cross_photographer_client_media_archive_and_billing_boundaries(): void
    {
        Queue::fake();
        F::configure();
        $victim = $this->deliveryProject();
        $other = $this->deliveryProject();
        $order = F::reserve($victim->photographer);
        $archives = app(DeliveryArchives::class);
        $request = Request::create('/synthetic', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $archive = $archives->authorized($request, $victim->share_token, fn ($module) => $archives->request($module));
        $entry = CatalogueEntry::where('revision_id', $victim->modules()->where('type', 'delivery')->value('published_revision_id'))->where('kind', 'photo')->firstOrFail();
        $selectionEntry = CatalogueEntry::where('revision_id', $victim->modules()->where('type', 'selection')->value('published_revision_id'))->where('kind', 'photo')->firstOrFail();
        $source = \Mockery::mock(DriveSource::class.', '.DrivePreviewSource::class.', '.DriveRangeSource::class);
        foreach (['metadata', 'children', 'original', 'thumbnail', 'originalRange'] as $method) {
            $source->shouldNotReceive($method);
        }
        foreach ([DriveSource::class, DrivePreviewSource::class, DriveRangeSource::class] as $contract) {
            app()->instance($contract, $source);
        }

        $this->actingAs($other->photographer);
        foreach (['', '/diagnostics', '/results', '/results/data', '/results/export'] as $suffix) {
            $this->getJson('/projects/'.$victim->id.$suffix)->assertNotFound();
        }
        foreach (['', '/open'] as $suffix) {
            $this->getJson('/billing/orders/'.$order->id.$suffix)->assertNotFound();
        }
        $this->postJson('/billing/orders/'.$order->id.'/resume')->assertNotFound();
        $this->postJson('/projects/'.$victim->id.'/selection/reopen', ['epoch' => 1, 'version' => 1])->assertNotFound();
        $this->postJson('/projects/'.$victim->id.'/commands', ['version' => $victim->version, 'command' => 'archive'])->assertNotFound();
        foreach (['/admin', '/admin/accounts/'.$victim->user_id, '/admin/orders/'.$order->id, '/admin/jobs'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $base = '/g/'.$other->share_token;
        foreach (['GET', 'HEAD'] as $method) {
            $this->call($method, $base.'/delivery/media/'.$entry->id, server: ['HTTP_RANGE' => 'bytes=0-1'])->assertNotFound();
            $this->call($method, $base.'/delivery/archive/'.$archive->id, server: ['HTTP_RANGE' => 'bytes=0-1'])->assertNotFound();
        }
        $this->getJson($base.'/selection/preview/'.$selectionEntry->id)->assertNotFound();
        $this->getJson('/g/'.$victim->share_token.'/delivery/media/'.$selectionEntry->id)->assertNotFound();
        $this->getJson($base.'/delivery/data?folder='.$entry->id)->assertNotFound();
        $this->assertDatabaseCount('entitlement_grants', 0);
        $this->assertDatabaseCount('choices', 0);
        $this->assertNull($victim->fresh()->archived_at);

        $this->actingAs(V::admin());
        $this->getJson('/projects/'.$victim->id)->assertNotFound();
        $this->getJson('/billing/orders/'.$order->id)->assertForbidden();
    }

    public function test_foreign_quote_and_client_money_or_owner_fields_cannot_create_a_purchase(): void
    {
        F::configure();
        $owner = F::buyer();
        $other = F::buyer();
        $quote = app(Checkout::class)->quote($owner, null);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'quote_token' => $quote['quote_token']];
        $this->actingAs($other)->postJson('/billing/orders', $payload)->assertForbidden();
        $this->actingAs($owner);
        foreach (['user_id' => $other->id, 'payable_idr' => 1, 'discount_idr' => 29000, 'duration_days' => 365, 'price' => 1] as $key => $value) {
            $this->postJson('/billing/orders', [...$payload, $key => $value])->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertDatabaseCount('voucher_reservations', 0);
        $this->assertDatabaseCount('entitlement_grants', 0);
    }
}
