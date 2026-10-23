<?php

namespace App\Actions\Admin;

use App\Actions\Billing\Checkout;
use App\Jobs\ImportCatalogue;
use App\Models\ArchiveJob;
use App\Models\CatalogueRevision;
use App\Models\GalleryModule;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Archives\DeliveryArchives;
use App\Services\Billing\PaymentProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperatePlatform
{
    public function __construct(private Checkout $actors, private DeliveryArchives $archives) {}

    private function stale(bool $valid): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['operation' => 'Versi atau akses telah berubah. Muat ulang dan periksa pengaturan galeri sebelum mencoba lagi.']);
        }
    }

    private function audit(User $admin, string $type, string $id, string $before, string $after, string $reason): void
    {
        DB::table('audit_events')->insert(['actor_id' => $admin->id, 'subject_type' => $type, 'subject_id' => $id,
            'action' => $type.'.'.($type === 'plan' ? 'versioned' : 'retried'), 'reason' => trim($reason),
            'before_reference' => $before, 'after_reference' => $after]);
    }

    public function price(User $admin, int $expected, int $price, string $reason): void
    {
        DB::transaction(function () use ($admin, $expected, $price, $reason) {
            $admin = $this->actors->actor($admin, AccountAction::Admin);
            $plan = DB::table('plans')->where('code', 'pro')->lockForUpdate()->first();
            $this->stale((int) $plan->current_version_id === $expected);
            $version = DB::table('plan_versions')->insertGetId(['price_idr' => $price, 'duration_days' => 30]);
            DB::table('plans')->where('code', 'pro')->update(['current_version_id' => $version]);
            $this->audit($admin, 'plan', 'pro', (string) $expected, (string) $version, $reason);
        }, 3);
    }

    public function retry(User $admin, string $type, string $id, int $expected, string $reason): void
    {
        DB::transaction(function () use ($admin, $type, $id, $expected, $reason) {
            $admin = $this->actors->actor($admin, AccountAction::Admin);
            $model = $type === 'import' ? CatalogueRevision::class : ArchiveJob::class;
            $snapshot = $model::findOrFail($id);
            $module = GalleryModule::findOrFail($snapshot->module_id);
            $project = Project::findOrFail($module->project_id);
            // Use the same user -> access -> project order as photographer mutations.
            $this->actors->actor(User::findOrFail($project->user_id), $type === 'import' ? AccountAction::Mutate : AccountAction::Download);
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $module = GalleryModule::whereKey($module->id)->lockForUpdate()->firstOrFail();
            $job = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->stale(! $project->archived_at);
            if ($type === 'import') {
                $this->stale($module->requested_revision_id === $job->id && $module->source === $job->source && $module->source !== null);
            } else {
                $this->stale($this->archives->eligible($job));
            }
            // A repeated submission attaches to its already queued retry without another dispatch/audit.
            if ($job->operations_version === $expected + 1 && in_array($job->state, ['queued', 'importing', 'processing', 'complete', 'ready'], true)) {
                return;
            }
            $this->stale($job->operations_version === $expected && $job->state === 'failed');
            if ($type === 'import') {
                $job->update(['state' => 'queued', 'failure' => null, 'operations_version' => $expected + 1]);
                ImportCatalogue::dispatch($job->id)->onConnection('catalogues')->onQueue('catalogues');
            } else {
                $this->archives->request($module);
                $job->update(['operations_version' => $expected + 1]);
            }
            $this->audit($admin, $type, $id, 'failed:'.$expected, 'queued:'.($expected + 1), $reason);
        }, 3);
    }

    public function recheck(User $admin, string $id, string $reason): void
    {
        DB::transaction(function () use ($admin, $id, $reason) {
            $admin = $this->actors->actor($admin, AccountAction::Admin);
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->paymentEvents()->whereIn('state', ['pending', 'processing', 'retry'])->exists()) {
                return;
            }
            $event = app(PaymentProcessing::class)->record($order, 'admin', actor: $admin->id);
            $this->audit($admin, 'payment', $id, $order->state, $event->id, $reason);
        });
    }
}
