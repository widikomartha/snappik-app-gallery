import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Shell } from '../../layouts/shell';
import { Field } from '../../components/ui/field';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';
import { Pagination, type Page } from './voucher';

export { Pagination, type Page };
export const idr = (n: number) => `Rp${Number(n).toLocaleString('id-ID')}`;
export const instant = (s: string | null) => s ? `${new Date(s).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA` : 'Belum tercatat';
const labels: Record<string, string> = { trial: 'Uji coba', pro: 'Pro', expired: 'Kedaluwarsa', suspended: 'Ditangguhkan', unverified: 'Belum terverifikasi', paid: 'Lunas terverifikasi', pending: 'Menunggu pembayaran', failed: 'Gagal', review: 'Perlu tinjauan', queued: 'Dalam antrean', processing: 'Diproses', importing: 'Mengimpor', created: 'Dibuat', creating: 'Membuka checkout', unknown: 'Status belum pasti', processed: 'Selesai', retry: 'Menunggu pemeriksaan ulang' };
export const stateLabel = (s: string) => labels[s] ?? s;

export function Operations({ children }: { children: ReactNode }) {
  const { status } = usePage<{ status?: string }>().props;
  return <Shell surface="admin"><div className="stack">
    <nav className="actions" aria-label="Administrasi"><Link href="/admin">Ringkasan</Link><Link href="/admin/accounts">Akun fotografer</Link><Link href="/admin/plan">Harga Pro</Link><Link href="/admin/orders">Transaksi</Link><Link href="/admin/vouchers">Kelola voucher</Link><Link href="/admin/jobs">Pekerjaan</Link></nav>
    {status && <Status tone="success">{status}</Status>}{children}
  </div></Shell>;
}

export function ReasonAction({ url, label, version, suspended, method = 'post' }: { url: string; label: string; version?: number; suspended?: boolean; method?: 'post' | 'put' }) {
  const form = useForm<{ reason: string; expected_version?: number; suspended?: boolean; operation?: string }>({ reason: '', ...(version === undefined ? {} : { expected_version: version }), ...(suspended === undefined ? {} : { suspended }) });
  const actionLabel = form.data.suspended === undefined ? label : form.data.suspended ? 'Tangguhkan akun' : 'Aktifkan kembali akun';
  return <form className="stack" aria-label={actionLabel} onSubmit={e => { e.preventDefault(); form.submit(method, url, { preserveScroll: true, onSuccess: () => {
    if (suspended !== undefined) router.get(window.location.href, {}, { preserveState: false, preserveScroll: true });
    else form.reset('reason');
  } }); }}>
    {form.errors.operation && <Status tone="error">{form.errors.operation}</Status>}
    <Field label={`Alasan: ${actionLabel}`} required maxLength={500} value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} error={form.errors.reason} />
    <div className="actions"><Button type="submit" pending={form.processing} variant={form.data.suspended ? 'destructive' : 'secondary'}>{actionLabel}</Button>
      <Button variant="ghost" disabled={form.processing} onClick={() => router.get(window.location.href, {}, { preserveState: false, preserveScroll: true })}>Muat ulang (buang perubahan)</Button></div>
  </form>;
}

export type AuditRow = { id: number; email: string | null; action: string; reason: string; subject_type: string | null; subject_id: string | null; before_reference: string | null; after_reference: string | null; created_at: string };
export function Audit({ audit }: { audit: Page<AuditRow> }) {
  return <section className="panel stack" aria-label="Audit administrasi"><h2>Audit administrasi</h2>
    {audit.data.length === 0 && <p>Belum ada perubahan administratif.</p>}
    {audit.data.map(a => <article key={a.id} className="stack"><p>{a.action} · {instant(a.created_at)} · {a.email ?? 'Sistem'}</p><p>{a.reason}</p><p>Referensi {a.subject_type} {a.subject_id} · {a.before_reference ?? '—'} → {a.after_reference ?? '—'}</p></article>)}
    <Pagination page={audit} label="Halaman audit" />
  </section>;
}

export type OrderRow = { id: string; user_id: number; email: string; state: string; base_idr: number; discount_idr: number; payable_idr: number; plan_version_id: number; duration_days: number; voucher_code: string | null; voucher_version_id: number | null; paid_at: string | null; applied_at: string | null; created_at: string; last_checked_at: string | null; review: boolean; diagnostic: string };
export function OrderRecords({ orders }: { orders: Page<OrderRow> }) {
  return <section className="stack" aria-label="Riwayat transaksi">
    {orders.data.length === 0 && <Status>Belum ada transaksi pada daftar ini.</Status>}
    {orders.data.map(o => <article key={o.id} className="panel stack"><h2><Link href={`/admin/orders/${o.id}`}>Pesanan {o.id}</Link></h2>
      <p><Link href={`/admin/accounts/${o.user_id}`}>{o.email}</Link> · {instant(o.created_at)}</p>
      <p>{stateLabel(o.state)}{o.review ? ' · Perlu tinjauan penyedia' : ''} · Total {idr(o.payable_idr)}</p>
      <p>Harga {idr(o.base_idr)} · Diskon {idr(o.discount_idr)} · Versi harga {o.plan_version_id} · {o.duration_days} hari</p>
      <p>Voucher {o.voucher_code ?? 'Tanpa voucher'} · Versi voucher {o.voucher_version_id ?? '—'}</p>
    </article>)}<Pagination page={orders} label="Halaman transaksi" />
  </section>;
}
