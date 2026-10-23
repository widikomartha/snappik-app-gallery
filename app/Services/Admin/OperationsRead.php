<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\Access\AccountPolicy;
use Illuminate\Support\Facades\DB;

class OperationsRead
{
    // An allowlist converts operational failures to remedies, never provider payloads.
    public function diagnostic(?string $code): string
    {
        return match ($code) {
            null => 'Tidak ada kegagalan tercatat.',
            'catalogue_access_closed', 'archive_gallery_closed' => 'Akses ditutup. Periksa status akun, publikasi dan tenggat galeri.',
            'catalogue_superseded', 'catalogue_sources_overlap', 'catalogue_selection_locked' => 'Konfigurasi sumber berubah atau bertentangan. Fotografer perlu memeriksa pengaturan.',
            'drive_not_found', 'drive_not_found_or_resource_key_required', 'drive_forbidden', 'drive_access_denied', 'drive_download_restricted', 'drive_download_denied', 'drive_not_downloadable' => 'Izin sumber ditolak. Fotografer perlu memeriksa akses folder dan unduhan di Drive.',
            'drive_credentials_missing', 'drive_request_pacing_unavailable', 'r2_credentials_missing', 'r2_invalid_account_id', 'midtrans_configuration_mismatch' => 'Konfigurasi layanan belum tersedia atau tidak cocok. Operator perlu memeriksa konfigurasi server.',
            'drive_no_supported_photos', 'drive_source_is_not_folder' => 'Sumber tidak berisi folder foto yang didukung. Fotografer perlu memperbarui pengaturan sumber.',
            'drive_source_changed', 'drive_folder_changed', 'drive_identity_mismatch', 'drive_media_integrity_failed', 'archive_manifest_mismatch', 'archive_output_length_mismatch', 'r2_completed_object_mismatch' => 'Identitas atau integritas sumber/keluaran berubah. Fotografer perlu menyegarkan katalog sebelum meminta ZIP baru.',
            'drive_connection_failed', 'drive_temporarily_unavailable', 'drive_transfer_failed', 'r2_request_failed', 'r2_read_failed', 'archive_output_unavailable', 'midtrans_status_unavailable' => 'Layanan sementara tidak tersedia. Periksa koneksi server dan pekerja sebelum mencoba kembali.',
            'r2_native_object_capacity_exceeded', 'r2_native_part_capacity_exceeded' => 'Kapasitas objek penyedia tercapai. Operator perlu meninjau kapasitas layanan; ZIP tetap satu berkas dan tidak diterbitkan sebagian.',
            'drive_rate_limited', 'drive_download_quota_exceeded', 'drive_automated_requests_blocked' => 'Batas layanan Drive. Tunggu atau periksa izin sumber sebelum mencoba kembali.',
            'catalogue_worker_failed', 'archive_worker_interrupted' => 'Pekerja terhenti. Periksa pekerja antrean sebelum mencoba kembali.',
            'archive_cleanup_required' => 'Pembersihan penyimpanan tertunda. Periksa pekerja pemeliharaan dan akses penyimpanan.',
            'midtrans_amount_mismatch', 'midtrans_identity_mismatch', 'midtrans_verification_mismatch', 'midtrans_transaction_mismatch', 'midtrans_transaction_reused', 'midtrans_paid_time_unverified', 'midtrans_settlement_unverified', 'midtrans_status_unverified' => 'Verifikasi pembayaran tidak cocok. Periksa transaksi di sandbox penyedia lalu periksa ulang status.',
            'midtrans_refund', 'midtrans_review_required', 'midtrans_unknown_status', 'midtrans_reversal_review', 'midtrans_unknown_state', 'midtrans_terminal_contradiction' => 'Status pembayaran memerlukan peninjauan penyedia. Periksa ulang tanpa mengubah ketentuan pesanan.',
            'midtrans_retries_exhausted' => 'Batas percobaan pemeriksaan tercapai. Periksa transaksi di penyedia lalu antrikan pemeriksaan baru.',
            default => 'Operasi gagal atau memerlukan tinjauan. Periksa konfigurasi layanan dan pekerja, lalu gunakan pemeriksaan atau percobaan ulang yang tersedia.',
        };
    }

    public function overview(): array
    {
        $accounts = DB::table('users as u')->leftJoin('account_access as a', 'a.user_id', '=', 'u.id')->where('u.role', 'photographer')
            ->selectRaw("CASE WHEN u.suspended_at IS NOT NULL THEN 'suspended' WHEN u.email_verified_at IS NULL THEN 'unverified'
                WHEN a.access_ends_at > ? THEN CASE WHEN a.has_paid_access THEN 'pro' ELSE 'trial' END ELSE 'expired' END AS state, count(*) AS count", [now()])
            ->groupBy('state')->pluck('count', 'state');
        $pending = DB::table('orders')->whereNotIn('state', ['paid', 'failed']);

        return ['accounts' => $accounts, 'registered' => User::where('role', 'photographer')->count(),
            'projects' => DB::table('projects')->count(), 'activeProjects' => DB::table('projects')->whereNull('archived_at')->count(),
            'publishedModules' => DB::table('gallery_modules')->where('published', true)->count(),
            'choices' => DB::table('choices')->count(), 'confirmations' => DB::table('selection_confirmations')->count(),
            // Unique grant/order identity, independent of callback/event count. Sandbox gross receipts, not net profit.
            'paidIdr' => (int) DB::table('entitlement_grants as g')->join('orders as o', 'o.id', '=', 'g.order_id')->sum('o.payable_idr'),
            'paidOrders' => DB::table('entitlement_grants')->count(), 'pendingOrders' => (clone $pending)->count(),
            'pendingIdr' => (int) $pending->sum('payable_idr'), 'reviewOrders' => DB::table('orders')->whereNotNull('review_reason')->count(),
            'heldVouchers' => DB::table('voucher_reservations')->where('state', 'held')->count(),
            'redeemedVouchers' => DB::table('voucher_redemptions')->count(),
            'failedImports' => DB::table('catalogue_revisions')->where('state', 'failed')->count(),
            'failedArchives' => DB::table('archive_jobs')->where('state', 'failed')->count(),
            'cleanupPending' => DB::table('archive_attempts')->where('cleanup_pending', true)->count(),
            'oldestQueuedAt' => DB::table('jobs')->min('created_at'),
            'oldestPaymentCheck' => DB::table('orders')->whereNotIn('state', ['paid', 'failed'])->min('last_checked_at')];
    }

    public function account(User $user): array
    {
        return [...$user->only('id', 'name', 'email', 'created_at', 'suspension_version'),
            'state' => app(AccountPolicy::class)->state($user), 'accessEndsAt' => $user->accountAccess?->access_ends_at?->toIso8601String()];
    }

    public function orders(?int $user = null)
    {
        return DB::table('orders as o')->join('users as u', 'u.id', '=', 'o.user_id')
            ->when($user, fn ($q) => $q->where('o.user_id', $user))
            ->select(['o.id', 'o.user_id', 'u.email', 'o.state', 'o.base_idr', 'o.discount_idr', 'o.payable_idr', 'o.plan_version_id',
                'o.duration_days', 'o.voucher_code', 'o.voucher_version_id', 'o.paid_at', 'o.applied_at', 'o.created_at', 'o.last_checked_at', 'o.review_reason', 'o.failure']);
    }

    public function order(object $order): array
    {
        $row = (array) $order;
        $row['review'] = $order->review_reason !== null;
        $row['diagnostic'] = $this->diagnostic($order->review_reason ?? $order->failure);
        unset($row['review_reason'], $row['failure']);

        return $row;
    }

    public function audit(?string $type = null, ?string $id = null)
    {
        return DB::table('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->when($type, fn ($q) => $q->where('a.subject_type', $type)->where('a.subject_id', $id))
            ->select(['a.id', 'a.action', 'a.reason', 'a.subject_type', 'a.subject_id', 'a.before_reference', 'a.after_reference', 'a.created_at', 'u.email'])
            ->orderByDesc('a.id')->paginate(20, ['*'], 'audit_page')->withQueryString();
    }
}
