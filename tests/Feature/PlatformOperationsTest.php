<?php

namespace Tests\Feature;

use App\Actions\Admin\OperatePlatform;
use App\Actions\Auth\SetAccountSuspension;
use App\Actions\Billing\Checkout;
use App\Jobs\BuildDeliveryArchive;
use App\Models\ArchiveJob;
use App\Models\CatalogueRevision;
use App\Models\GalleryModule;
use App\Services\Access\AccountPolicy;
use App\Services\Archives\DeliveryArchives;
use App\Services\Billing\PaymentProcessing;
use App\Services\Drive\ImportProjectCatalogue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\DeliveryFixture;
use Tests\Support\PaymentFixture as P;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use DeliveryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
    }

    private function version(): int
    {
        return (int) DB::table('plans')->where('code', 'pro')->value('current_version_id');
    }

    private function payload(int $version = 0): array
    {
        return ['expected_version' => $version, 'reason' => 'Synthetic operator investigation'];
    }

    public function test_every_operations_route_requires_current_verified_unsuspended_admin(): void
    {
        $target = F::buyer();
        $order = F::reserve($target);
        $reads = ['/admin', '/admin/accounts', '/admin/accounts/'.$target->id, '/admin/plan', '/admin/orders', '/admin/orders/'.$order->id, '/admin/jobs'];
        foreach ($reads as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $admin = V::admin();
        foreach ([$target, $admin] as $actor) {
            if ($actor->id === $admin->id) {
                // A stale authenticated model cannot retain a role removed in persistence.
                DB::table('users')->where('id', $admin->id)->update(['role' => 'photographer']);
            }
            $this->actingAs($actor);
            foreach ($reads as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->putJson('/admin/accounts/'.$target->id.'/suspension', [...$this->payload(), 'suspended' => true])->assertForbidden();
            $this->putJson('/admin/plan', [...$this->payload($this->version()), 'price_idr' => 49000])->assertForbidden();
            foreach (['import', 'archive'] as $type) {
                $this->postJson('/admin/jobs/'.$type.'/'.Str::uuid().'/retry', $this->payload())->assertForbidden();
            }
            $this->postJson('/admin/operations/orders/'.$order->id.'/recheck', ['reason' => 'test'])->assertForbidden();
        }
        DB::table('users')->where('id', $admin->id)->update(['role' => 'admin', 'suspended_at' => now()]);
        $this->actingAs($admin)->get('/admin')->assertForbidden();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_price_versions_preserve_existing_orders_reject_stale_quotes_and_forms(): void
    {
        $buyer = F::buyer();
        $old = F::reserve($buyer);
        $quote = app(Checkout::class)->quote($buyer, null);
        $admin = V::admin();
        $version = $this->version();
        $this->actingAs($admin)->from('/admin/plan')->put('/admin/plan', [...$this->payload($version), 'price_idr' => 49000])->assertRedirect('/admin/plan');
        $this->assertSame(29000, $old->fresh()->payable_idr);
        $this->assertSame(49000, F::reserve($buyer)->payable_idr);
        $this->putJson('/admin/plan', [...$this->payload($version), 'price_idr' => 99000])->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->actingAs($buyer)->postJson('/billing/orders', ['idempotency_key' => (string) Str::uuid(), 'quote_token' => $quote['quote_token']])->assertUnprocessable()->assertJsonValidationErrors('quote_token');
        $this->assertDatabaseCount('plan_versions', 2);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'subject_type' => 'plan', 'subject_id' => 'pro', 'before_reference' => (string) $version, 'after_reference' => (string) $this->version()]);
    }

    public function test_invalid_price_and_missing_reason_or_version_are_rejected(): void
    {
        $this->actingAs(V::admin());
        foreach ([0, -1, 1.5, '9007199254740992', 'not-money'] as $price) {
            $this->putJson('/admin/plan', [...$this->payload($this->version()), 'price_idr' => $price])->assertUnprocessable()->assertJsonValidationErrors('price_idr');
        }
        $this->putJson('/admin/plan', ['price_idr' => 49000, 'reason' => '  '])->assertUnprocessable()->assertJsonValidationErrors(['expected_version', 'reason']);
        $this->assertDatabaseCount('plan_versions', 1);
    }

    public function test_invalid_admin_forms_return_to_their_explicit_form_without_referrer(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $this->actingAs(V::admin());
        foreach ([['put', '/admin/plan', '/admin/plan'], ['put', '/admin/accounts/'.$buyer->id.'/suspension', '/admin/accounts/'.$buyer->id],
            ['post', '/admin/operations/orders/'.$order->id.'/recheck', '/admin/orders/'.$order->id],
            ['post', '/admin/jobs/import/'.Str::uuid().'/retry', '/admin/jobs']] as [$method, $url, $form]) {
            $this->{$method}($url, [])->assertRedirect($form)->assertSessionHasErrors('reason');
        }
    }

    public function test_failed_queue_insert_rolls_back_retry_state_and_audit_together(): void
    {
        $project = $this->deliveryProject();
        $revision = CatalogueRevision::find($project->modules()->where('type', 'selection')->value('requested_revision_id'));
        $revision->update(['state' => 'failed']);
        $admin = V::admin();
        DB::unprepared("CREATE FUNCTION reject_operations_queue() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic queue failure'; END; $$;
            CREATE TRIGGER reject_operations_queue BEFORE INSERT ON jobs FOR EACH ROW EXECUTE FUNCTION reject_operations_queue();");
        try {
            try {
                app(OperatePlatform::class)->retry($admin, 'import', $revision->id, 0, 'Synthetic atomicity verification');
                $this->fail('Queue insertion must fail.');
            } catch (QueryException) {
                $this->assertSame('failed', $revision->fresh()->state);
                $this->assertSame(0, $revision->fresh()->operations_version);
                $this->assertSame(0, DB::table('audit_events')->where('action', 'import.retried')->count());
            }
        } finally {
            DB::unprepared('DROP TRIGGER reject_operations_queue ON jobs; DROP FUNCTION reject_operations_queue();');
        }
    }

    public function test_suspension_and_reactivation_recheck_new_gallery_requests_and_reject_stale_form(): void
    {
        $project = $this->deliveryProject();
        $owner = $project->photographer;
        $admin = V::admin();
        $client = '/g/'.$project->share_token.'/delivery/data';
        $end = $owner->accountAccess->access_ends_at;
        $this->getJson($client)->assertOk();
        $this->actingAs($admin)->put('/admin/accounts/'.$owner->id.'/suspension', [...$this->payload(), 'suspended' => true])->assertRedirect();
        $this->getJson($client)->assertNotFound();
        $this->actingAs($owner)->get('/billing')->assertOk();
        $this->actingAs($admin)->putJson('/admin/accounts/'.$owner->id.'/suspension', [...$this->payload(), 'suspended' => false])->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->put('/admin/accounts/'.$owner->id.'/suspension', [...$this->payload(1), 'suspended' => false])->assertRedirect();
        $this->getJson($client)->assertOk();
        $this->assertTrue($end->equalTo($owner->fresh()->accountAccess->access_ends_at));
        $this->assertDatabaseHas('audit_events', ['action' => 'account.suspended', 'before_reference' => 'active:0', 'after_reference' => 'suspended:1']);
        $this->putJson('/admin/accounts/'.$admin->id.'/suspension', [...$this->payload(), 'suspended' => true])->assertForbidden();
    }

    public function test_overview_counts_verified_grants_once_and_separates_pending_and_review(): void
    {
        $buyer = F::buyer();
        $paid = F::reserve($buyer);
        F::reserve($buyer);
        P::gateway([P::response($paid), P::response($paid)]);
        foreach ([1, 2] as $duplicate) {
            $event = app(PaymentProcessing::class)->record($paid, 'operator', queue: false);
            app(PaymentProcessing::class)->process($event->id);
        }
        $paid->refresh()->update(['review_reason' => 'midtrans_refund']);
        $this->actingAs(V::admin())->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/dashboard')
            ->where('overview.paidIdr', 29000)->where('overview.paidOrders', 1)->where('overview.pendingIdr', 29000)
            ->where('overview.pendingOrders', 1)->where('overview.reviewOrders', 1)->where('overview.accounts.pro', 1));
        $this->get('/admin/orders?review=1')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $paid->id));
    }

    public function test_import_retry_is_idempotent_audited_and_resumes_current_revision(): void
    {
        $project = $this->deliveryProject();
        $module = $project->modules()->where('type', 'selection')->first();
        $revision = CatalogueRevision::find($module->requested_revision_id);
        $revision->update(['state' => 'failed', 'failure' => 'catalogue_worker_failed']);
        $before = DB::table('jobs')->where('queue', 'catalogues')->count();
        $url = '/admin/jobs/import/'.$revision->id.'/retry';
        $this->actingAs(V::admin())->post($url, $this->payload())->assertRedirect();
        $this->post($url, $this->payload())->assertRedirect();
        $this->assertSame($before + 1, DB::table('jobs')->where('queue', 'catalogues')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'import.retried')->count());
        app(ImportProjectCatalogue::class)->handle($revision->id);
        $this->assertSame('complete', $revision->fresh()->state);
        $this->assertSame($revision->id, $module->fresh()->published_revision_id);
        $revision->refresh()->update(['state' => 'failed']);
        $this->postJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('operation');
    }

    public function test_import_retry_rejects_superseded_source_archived_and_expired_owner(): void
    {
        $project = $this->deliveryProject();
        $module = $project->modules()->where('type', 'selection')->first();
        $old = CatalogueRevision::find($module->requested_revision_id);
        $old->update(['state' => 'failed']);
        $admin = V::admin();
        $this->actingAs($admin);
        $url = '/admin/jobs/import/'.$old->id.'/retry';
        $module->update(['requested_revision_id' => null]);
        $this->postJson($url, $this->payload())->assertUnprocessable();
        $module->update(['requested_revision_id' => $old->id, 'source' => ['id' => 'other-source', 'resourceKey' => null]]);
        $this->postJson($url, $this->payload())->assertUnprocessable();
        $module->update(['source' => $old->source]);
        $project->update(['archived_at' => now()]);
        $this->postJson($url, $this->payload())->assertUnprocessable();
        $project->update(['archived_at' => null]);
        $project->photographer->accountAccess->forceFill(['access_ends_at' => now()->subSecond()])->save();
        $this->postJson($url, $this->payload())->assertForbidden();
        $this->assertSame('failed', $old->fresh()->state);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'import.retried')->count());
    }

    private function failedArchive(): ArchiveJob
    {
        $project = $this->deliveryProject();
        $job = DB::transaction(fn () => app(DeliveryArchives::class)->request($project->modules()->where('type', 'delivery')->first()));
        $job->update(['state' => 'failed', 'failure' => 'archive_worker_interrupted']);

        return $job;
    }

    public function test_zip_retry_deduplicates_retains_manifest_and_allows_ordinary_account_expiry(): void
    {
        $job = $this->failedArchive();
        $module = GalleryModule::find($job->module_id);
        $module->project->photographer->accountAccess->forceFill(['access_ends_at' => now()->subSecond()])->save();
        $before = DB::table('jobs')->where('queue', 'archives')->count();
        $url = '/admin/jobs/archive/'.$job->id.'/retry';
        $this->actingAs(V::admin())->post($url, $this->payload())->assertRedirect();
        $this->post($url, $this->payload())->assertRedirect();
        $this->assertSame($before + 1, DB::table('jobs')->where('queue', 'archives')->count());
        $this->assertSame($job->manifest_digest, $job->fresh()->manifest_digest);
        $this->assertSame($job->total_files, $job->fresh()->total_files);
        $this->assertDatabaseHas('audit_events', ['subject_type' => 'archive', 'subject_id' => $job->id, 'before_reference' => 'failed:0', 'after_reference' => 'queued:1']);
    }

    public function test_zip_retry_rejects_changed_gates_and_worker_rechecks_after_enqueue(): void
    {
        $job = $this->failedArchive();
        $module = GalleryModule::find($job->module_id);
        $admin = V::admin();
        $url = '/admin/jobs/archive/'.$job->id.'/retry';
        $this->actingAs($admin);
        foreach ([['published' => false], ['gate_version' => $module->gate_version + 1], ['deadline' => now()->subSecond()], ['published_revision_id' => null]] as $change) {
            $saved = $module->getAttributes();
            $module->update($change);
            $this->postJson($url, $this->payload())->assertUnprocessable();
            $module->setRawAttributes($saved)->save();
        }
        $this->post($url, $this->payload())->assertRedirect();
        app(SetAccountSuspension::class)->handle($admin, $module->project->photographer, true, 'Synthetic worker gate race');
        (new BuildDeliveryArchive($job->id))->handle(app(DeliveryArchives::class));
        $this->assertSame('failed', $job->fresh()->state);
        $this->assertSame('archive_gallery_closed', $job->fresh()->failure);
        $this->assertDatabaseCount('archive_attempts', 0);
    }

    public function test_payment_recheck_is_audited_deduplicated_and_never_grants_from_submitted_state(): void
    {
        $order = F::reserve(F::buyer());
        $admin = V::admin();
        $url = '/admin/operations/orders/'.$order->id.'/recheck';
        $this->actingAs($admin)->post($url, ['reason' => 'Synthetic provider investigation', 'state' => 'paid', 'payable_idr' => 1])->assertRedirect();
        $this->post($url, ['reason' => 'Duplicate recheck'])->assertRedirect();
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('entitlement_grants', 0);
        $this->assertSame('created', $order->fresh()->state);
        $this->assertSame(29000, $order->fresh()->payable_idr);
        $this->assertDatabaseHas('payment_events', ['order_id' => $order->id, 'actor_id' => $admin->id, 'source' => 'admin']);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'payment.retried')->count());
    }

    public function test_read_models_are_paginated_and_exclude_credentials_capabilities_file_payloads_and_raw_failures(): void
    {
        $job = $this->failedArchive();
        $module = GalleryModule::find($job->module_id);
        $project = $module->project;
        $order = F::reserve($project->photographer);
        $order->update(['failure' => 'synthetic-raw-secret', 'review_reason' => 'synthetic-raw-secret', 'snap_token' => 'synthetic-private-checkout']);
        $this->actingAs(V::admin());
        foreach (['/admin', '/admin/accounts', '/admin/accounts/'.$project->user_id, '/admin/plan', '/admin/orders', '/admin/orders/'.$order->id, '/admin/jobs'] as $url) {
            $response = $this->get($url)->assertOk();
            foreach ([$project->share_token, 'synthetic-private-checkout', 'synthetic-raw-secret', 'synthetic-test-secret'] as $secret) {
                $response->assertDontSee($secret, false);
            }
        }
        $this->get('/admin/accounts/'.$project->user_id)->assertInertia(fn (Assert $p) => $p->component('admin/account')->has('projects.data', 1)->missing('projects.data.0.share_token')->missing('projects.data.0.contact'));
        $this->get('/admin/jobs')->assertInertia(fn (Assert $p) => $p->has('archives.data', 1)->missing('archives.data.0.object_key')->missing('archives.data.0.failure'));
        $this->assertSame('trial', app(AccountPolicy::class)->state($project->photographer));
    }
}
