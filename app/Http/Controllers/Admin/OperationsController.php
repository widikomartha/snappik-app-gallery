<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\OperatePlatform;
use App\Actions\Auth\SetAccountSuspension;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\Admin\OperationsRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OperationsController extends Controller
{
    private function mutate(Request $request, string $form, array $rules, \Closure $action, string $status)
    {
        try {
            $action($request->validate($rules));
        } catch (ValidationException $error) {
            throw $error->redirectTo($form);
        }

        return redirect($form)->with('status', $status);
    }

    public function index(OperationsRead $read)
    {
        return Inertia::render('admin/dashboard', ['overview' => $read->overview(), 'audit' => $read->audit()]);
    }

    public function accounts(OperationsRead $read)
    {
        return Inertia::render('admin/accounts', ['accounts' => User::where('role', 'photographer')->orderByDesc('id')->paginate(20)->through($read->account(...))]);
    }

    public function account(User $user, OperationsRead $read)
    {
        abort_unless($user->role === 'photographer', 404);

        return Inertia::render('admin/account', ['account' => $read->account($user),
            'projects' => Project::where('user_id', $user->id)->orderByDesc('created_at')->paginate(20, ['id', 'name', 'archived_at', 'selection_locked_at', 'created_at'], 'projects_page')->withQueryString(),
            'orders' => $read->orders($user->id)->orderByDesc('o.created_at')->paginate(20, ['*'], 'orders_page')->withQueryString()->through($read->order(...)),
            'grants' => DB::table('entitlement_grants')->where('user_id', $user->id)->orderByDesc('created_at')->paginate(20, ['order_id', 'paid_at', 'starts_at', 'ends_at', 'duration_days'], 'grants_page')->withQueryString(),
            'audit' => $read->audit('account', (string) $user->id)]);
    }

    public function suspend(Request $request, User $user, SetAccountSuspension $action)
    {
        return $this->mutate($request, '/admin/accounts/'.$user->id,
            ['suspended' => ['required', 'boolean'], 'expected_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:500']],
            fn ($data) => $action->handle($request->user(), $user, $data['suspended'], $data['reason'], $data['expected_version']), 'Status akun disimpan.');
    }

    public function plan()
    {
        return Inertia::render('admin/plan', ['plan' => DB::table('plan_versions')->find(DB::table('plans')->where('code', 'pro')->value('current_version_id')),
            'versions' => DB::table('plan_versions')->orderByDesc('id')->paginate(20), 'audit' => app(OperationsRead::class)->audit('plan', 'pro')]);
    }

    public function price(Request $request, OperatePlatform $action)
    {
        return $this->mutate($request, '/admin/plan', ['price_idr' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:500']],
            fn ($data) => $action->price($request->user(), $data['expected_version'], $data['price_idr'], $data['reason']), 'Versi harga baru disimpan. Pesanan lama tetap.');
    }

    public function orders(Request $request, OperationsRead $read)
    {
        $data = $request->validate(['review' => ['nullable', 'in:1']]);

        return Inertia::render('admin/orders', ['orders' => $read->orders()->when(isset($data['review']), fn ($q) => $q->whereNotNull('o.review_reason'))
            ->orderByDesc('o.created_at')->paginate(20)->withQueryString()->through($read->order(...))]);
    }

    public function order(string $order, OperationsRead $read)
    {
        return Inertia::render('admin/order', ['order' => $read->order($read->orders()->where('o.id', $order)->firstOrFail()),
            'events' => DB::table('payment_events')->where('order_id', $order)->orderByDesc('created_at')->paginate(20,
                ['id', 'source', 'actor_id', 'state', 'failure', 'attempts', 'created_at', 'processed_at'], 'events_page')->withQueryString()
                ->through(function ($event) use ($read) {
                    $event->diagnostic = $read->diagnostic($event->failure);
                    unset($event->failure);

                    return $event;
                }),
            'audit' => $read->audit('payment', $order)]);
    }

    public function recheck(Request $request, string $order, OperatePlatform $action)
    {
        return $this->mutate($request, '/admin/orders/'.$order, ['reason' => ['required', 'string', 'max:500']],
            fn ($data) => $action->recheck($request->user(), $order, $data['reason']), 'Pemeriksaan penyedia diantrikan atau masih berlangsung. Akses belum berubah.');
    }

    public function jobs(OperationsRead $read)
    {
        $rows = function (string $table, string $type) use ($read) {
            return DB::table($table.' as j')->join('gallery_modules as m', 'm.id', '=', 'j.module_id')->join('projects as p', 'p.id', '=', 'm.project_id')
                ->whereIn('j.state', ['failed', 'queued', 'processing', 'importing'])->orderByDesc('j.updated_at')
                ->paginate(20, ['j.id', 'j.state', 'j.failure', 'j.operations_version', 'j.updated_at', 'm.type', 'p.name', 'p.user_id'], $type.'_page')->withQueryString()
                ->through(function ($row) use ($read) {
                    $row->diagnostic = $read->diagnostic($row->failure);
                    unset($row->failure);

                    return $row;
                });
        };

        return Inertia::render('admin/jobs', ['imports' => $rows('catalogue_revisions', 'import'), 'archives' => $rows('archive_jobs', 'archive'),
            'cleanup' => DB::table('archive_attempts')->where('cleanup_pending', true)->orderBy('created_at')->paginate(20,
                ['id', 'archive_job_id', 'created_at', 'cleanup_failure'], 'cleanup_page')->withQueryString()
                ->through(function ($row) use ($read) {
                    $row->diagnostic = $read->diagnostic($row->cleanup_failure);
                    unset($row->cleanup_failure);

                    return $row;
                }),
            'audit' => $read->audit()]);
    }

    public function retry(Request $request, string $type, string $job, OperatePlatform $action)
    {
        return $this->mutate($request, '/admin/jobs', ['expected_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:500']],
            fn ($data) => $action->retry($request->user(), $type, $job, $data['expected_version'], $data['reason']), 'Percobaan ulang diantrikan atau masih berlangsung. Pekerja akan memeriksa akses lagi.');
    }
}
