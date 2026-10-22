<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\ManageVoucher;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoucherConfiguration;
use App\Models\Voucher;
use App\Services\Billing\VoucherUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::with('currentVersion')->orderByDesc('id')->paginate(20);
        // Two grouped counts for this response window, rather than per-record queries.
        $ids = $vouchers->getCollection()->pluck('id');
        $held = DB::table('voucher_reservations')->whereIn('voucher_id', $ids)->where('state', 'held')->selectRaw('voucher_id, count(*) as count')->groupBy('voucher_id')->pluck('count', 'voucher_id');
        $used = DB::table('voucher_redemptions as d')->join('voucher_reservations as r', 'r.id', '=', 'd.reservation_id')->whereIn('r.voucher_id', $ids)->selectRaw('r.voucher_id, count(*) as count')->groupBy('r.voucher_id')->pluck('count', 'r.voucher_id');
        $vouchers->through(fn (Voucher $v) => ['id' => $v->id, 'code' => $v->code, 'terms' => $v->currentVersion,
            'held' => (int) ($held[$v->id] ?? 0), 'used' => (int) ($used[$v->id] ?? 0)]);

        return Inertia::render('admin/vouchers', ['vouchers' => $vouchers]);
    }

    public function create()
    {
        return Inertia::render('admin/voucher', ['voucher' => null]);
    }

    public function store(VoucherConfiguration $request, ManageVoucher $action)
    {
        try {
            $voucher = $action->save($request->user(), $request->validated());
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('admin.vouchers.create'));
        }

        return to_route('admin.vouchers.show', $voucher);
    }

    public function show(Voucher $voucher, VoucherUsage $usage)
    {
        return Inertia::render('admin/voucher', [
            'voucher' => ['id' => $voucher->id, 'code' => $voucher->code, 'terms' => $voucher->currentVersion],
            'usage' => $usage->summary($voucher->id),
            'versions' => $voucher->versions()->orderByDesc('number')->paginate(10, ['*'], 'versions_page')->withQueryString(),
            'reservations' => $usage->reservations($voucher->id)->join('users as u', 'u.id', '=', 'r.user_id')->join('voucher_versions as v', 'v.id', '=', 'r.voucher_version_id')
                ->orderByDesc('r.id')->paginate(20, ['r.id', 'r.order_id', 'u.email', 'r.user_id', 'v.number', 'r.state', 'r.created_at', 'r.resolved_at'], 'reservations_page')->withQueryString(),
            'redemptions' => $usage->redemptions($voucher->id)->join('users as u', 'u.id', '=', 'r.user_id')->join('voucher_versions as v', 'v.id', '=', 'r.voucher_version_id')
                ->orderByDesc('d.id')->paginate(20, ['d.id', 'r.order_id', 'u.email', 'r.user_id', 'v.number', 'd.created_at'], 'redemptions_page')->withQueryString(),
            'audit' => DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->join('voucher_versions as v', 'v.id', '=', 'a.voucher_version_id')
                ->where('a.voucher_id', $voucher->id)->orderByDesc('a.id')->paginate(10, ['a.id', 'a.action', 'a.reason', 'a.created_at', 'u.email', 'v.number'], 'audit_page')->withQueryString(),
        ]);
    }

    public function update(VoucherConfiguration $request, Voucher $voucher, ManageVoucher $action)
    {
        try {
            $action->save($request->user(), $request->validated(), $voucher->id);
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('admin.vouchers.show', $voucher));
        }

        return to_route('admin.vouchers.show', $voucher);
    }
}
