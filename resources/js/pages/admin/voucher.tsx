import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Shell } from '../../layouts/shell';
import { Field } from '../../components/ui/field';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';

export type Terms = { id: number; number: number; discount_type: 'percentage' | 'fixed'; discount_value: number; starts_at: string; ends_at: string; total_limit: number | null; per_user_limit: number | null; enabled: boolean; created_at: string };
export type Page<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; total: number };
type Voucher = { id: number; code: string; terms: Terms };
type Reservation = { id: number; order_id: string; email: string; user_id: number; number: number; state: string; created_at: string; resolved_at: string | null };
type Redemption = Omit<Reservation, 'state' | 'resolved_at'>;
type Audit = { id: number; action: string; reason: string; email: string | null; number: number; created_at: string };
type Props = { voucher: Voucher | null; usage?: { held: number; used: number; released: number }; versions?: Page<Terms>; reservations?: Page<Reservation>; redemptions?: Page<Redemption>; audit?: Page<Audit> };
const idr = (value: number) => `Rp${value.toLocaleString('id-ID')}`;
const instant = (value: string) => `${new Date(value).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA`;
const localTime = (value?: string) => value ? new Date(new Date(value).getTime() + 8 * 3600000).toISOString().slice(0, 19) : '';
const states: Record<string, string> = { held: 'Ditahan', released: 'Dilepas', consumed: 'Digunakan' };
const actions: Record<string, string> = { 'voucher.created': 'Dibuat', 'voucher.updated': 'Diubah', 'voucher.deactivated': 'Dinonaktifkan', 'voucher.reactivated': 'Diaktifkan kembali' };

export function Pagination({ page, label }: { page: Page<unknown>; label: string }) {
  return <nav className="actions" aria-label={label}>
    {page.prev_page_url && <Link href={page.prev_page_url} preserveState preserveScroll>Sebelumnya</Link>}
    <span>Halaman {page.current_page} / {page.last_page} · {page.total} catatan</span>
    {page.next_page_url && <Link href={page.next_page_url} preserveState preserveScroll>Berikutnya</Link>}
  </nav>;
}

export function VoucherTerms({ terms }: { terms: Terms }) {
  return <div className="stack">
    <p>Versi {terms.number} · {terms.enabled ? 'Diaktifkan' : 'Dinonaktifkan'} · Diskon {terms.discount_type === 'percentage' ? `${terms.discount_value}%` : idr(terms.discount_value)}</p>
    <p>Mulai {instant(terms.starts_at)} (termasuk) · Berakhir {instant(terms.ends_at)} (tidak termasuk)</p>
    <p>Batas total: {terms.total_limit ?? 'Tanpa batas'} · Per akun: {terms.per_user_limit ?? 'Tanpa batas'}</p>
  </div>;
}

function initial(voucher: Voucher | null) {
  return { code: voucher?.code ?? '', expected_version: voucher?.terms.number ?? 1,
    discount_type: voucher?.terms.discount_type ?? 'percentage', discount_value: String(voucher?.terms.discount_value ?? 10),
    starts_at: localTime(voucher?.terms.starts_at), ends_at: localTime(voucher?.terms.ends_at),
    total_limit: voucher?.terms.total_limit?.toString() ?? '', per_user_limit: voucher?.terms.per_user_limit?.toString() ?? '',
    enabled: voucher?.terms.enabled ?? true, reason: '' };
}

export default function VoucherPage({ voucher, usage, versions, reservations, redemptions, audit }: Props) {
  const form = useForm(initial(voucher));
  function save(event: FormEvent) {
    event.preventDefault();
    form.transform(data => ({ ...data, code: voucher ? undefined : data.code, expected_version: voucher ? data.expected_version : undefined,
      starts_at: data.starts_at ? `${data.starts_at.length === 16 ? `${data.starts_at}:00` : data.starts_at}+08:00` : '',
      ends_at: data.ends_at ? `${data.ends_at.length === 16 ? `${data.ends_at}:00` : data.ends_at}+08:00` : '',
    }));
    const options = { preserveScroll: true, onSuccess: (page: { props: Record<string, unknown> }) => {
      const saved = initial(page.props.voucher as Voucher);
      form.setData(saved); form.setDefaults(saved);
    } };
    if (voucher) form.put(`/admin/vouchers/${voucher.id}`, options); else form.post('/admin/vouchers', options);
  }
  return <Shell surface="admin"><Head title={voucher?.code ?? 'Buat voucher'} /><div className="stack">
    <Link href="/admin/vouchers">Semua voucher</Link><h1>{voucher?.code ?? 'Buat voucher'}</h1>
    {voucher && <section className="panel stack" aria-label="Ketentuan saat ini"><h2>Ketentuan saat ini</h2><VoucherTerms terms={voucher.terms} /></section>}
    <form className="panel stack" onSubmit={save} aria-label="Pengaturan voucher">
      <h2>{voucher ? 'Terbitkan versi baru' : 'Ketentuan voucher'}</h2>
      <p>Perubahan berlaku untuk pesanan baru. Pesanan tertunda mempertahankan ketentuan yang sudah disepakati.</p>
      {form.isDirty && <Status>Perubahan belum disimpan.</Status>}
      {Object.entries(form.errors).map(([key, message]) => <Status key={key} tone="error">{message}</Status>)}
      <fieldset className="stack" disabled={form.processing}>
        {voucher ? <p>Kode tetap: <strong>{voucher.code}</strong>. Kode tidak dapat dihapus atau digunakan ulang.</p> : <Field label="Kode voucher" required maxLength={64} value={form.data.code} onChange={e => form.setData('code', e.target.value)} description="Spasi di awal/akhir dihapus; huruf disimpan sebagai huruf besar." error={form.errors.code} />}
        <div className="field"><label htmlFor="discount-type">Jenis diskon</label><select id="discount-type" value={form.data.discount_type} onChange={e => form.setData('discount_type', e.target.value as Terms['discount_type'])}><option value="percentage">Persentase</option><option value="fixed">Nominal IDR</option></select></div>
        <Field label={form.data.discount_type === 'percentage' ? 'Persentase diskon' : 'Nominal diskon (IDR)'} type="number" min={1} step={1} max={form.data.discount_type === 'percentage' ? 99 : Number.MAX_SAFE_INTEGER} required value={form.data.discount_value} onChange={e => form.setData('discount_value', e.target.value)} error={form.errors.discount_value} description="Total akhir harus positif dan memenuhi minimum kanal pembayaran. Tidak ada penumpukan voucher." />
        <Field label="Mulai (WITA, UTC+08:00)" type="datetime-local" step={1} required value={form.data.starts_at} onChange={e => form.setData('starts_at', e.target.value)} error={form.errors.starts_at} />
        <Field label="Berakhir (WITA, UTC+08:00)" type="datetime-local" step={1} required value={form.data.ends_at} onChange={e => form.setData('ends_at', e.target.value)} error={form.errors.ends_at} />
        <Field label="Batas penggunaan total" type="number" min={1} step={1} max={Number.MAX_SAFE_INTEGER} value={form.data.total_limit} onChange={e => form.setData('total_limit', e.target.value)} error={form.errors.total_limit} description="Kosong berarti tanpa batas. Penggunaan berhasil dan reservasi ditahan dihitung di semua versi kode ini." />
        <Field label="Batas penggunaan per akun" type="number" min={1} step={1} max={Number.MAX_SAFE_INTEGER} value={form.data.per_user_limit} onChange={e => form.setData('per_user_limit', e.target.value)} error={form.errors.per_user_limit} description="Kosong berarti tanpa batas per akun." />
        <label className="choice"><input type="checkbox" checked={form.data.enabled} onChange={e => form.setData('enabled', e.target.checked)} />Aktifkan voucher untuk pesanan baru</label>
        <Field label="Alasan perubahan" required maxLength={500} value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} error={form.errors.reason} />
        <Button type="submit" pending={form.processing}>{voucher ? 'Simpan versi baru' : 'Buat voucher'}</Button>
      </fieldset>
      {voucher && <Button variant="secondary" disabled={form.processing} onClick={() => router.get(`/admin/vouchers/${voucher.id}`, {}, { preserveState: false, preserveScroll: true })}>Muat versi terbaru (buang perubahan)</Button>}
    </form>
    {voucher && usage && <section className="panel stack" aria-label="Penggunaan voucher"><h2>Penggunaan semua versi</h2>
      <p>{usage.held} reservasi ditahan · {usage.used} penggunaan berhasil · {usage.released} reservasi dilepas</p>
      <p>Menonaktifkan kode menghentikan pesanan baru. Reservasi pesanan tertunda tetap dihitung; pengembalian dana tidak memulihkan penggunaan secara otomatis.</p>
    </section>}
    {versions && <section className="panel stack" aria-label="Riwayat versi"><h2>Riwayat versi</h2>
      {versions.data.map(v => <article className="voucher-record stack" key={v.id}><h3>Versi {v.number} · ID {v.id}</h3><VoucherTerms terms={v} /><p>Dibuat {instant(v.created_at)}</p></article>)}
      <Pagination page={versions} label="Halaman versi" />
    </section>}
    {reservations && <section className="panel stack" aria-label="Reservasi voucher"><h2>Reservasi</h2>
      {reservations.data.length === 0 && <p>Belum ada reservasi.</p>}
      {reservations.data.map(r => <article className="voucher-record stack" key={r.id}><h3>{states[r.state]} · Versi {r.number}</h3><p>Akun {r.user_id} · {r.email}</p><p>Pesanan {r.order_id}</p><p>Ditahan {instant(r.created_at)}{r.resolved_at && ` · Diselesaikan ${instant(r.resolved_at)}`}</p></article>)}
      <Pagination page={reservations} label="Halaman reservasi" />
    </section>}
    {redemptions && <section className="panel stack" aria-label="Penukaran voucher"><h2>Penggunaan berhasil</h2>
      {redemptions.data.length === 0 && <p>Belum ada penggunaan berhasil.</p>}
      {redemptions.data.map(r => <article className="voucher-record stack" key={r.id}><h3>Versi {r.number} · {instant(r.created_at)}</h3><p>Akun {r.user_id} · {r.email}</p><p>Pesanan {r.order_id}</p></article>)}
      <Pagination page={redemptions} label="Halaman penukaran" />
    </section>}
    {audit && <section className="panel stack" aria-label="Audit voucher"><h2>Catatan perubahan</h2>
      {audit.data.map(a => <article className="voucher-record stack" key={a.id}><h3>{actions[a.action] ?? a.action} · Versi {a.number}</h3><p>{a.reason}</p><p>{a.email ?? 'Aktor sistem'} · {instant(a.created_at)}</p></article>)}
      <Pagination page={audit} label="Halaman audit" />
    </section>}
  </div></Shell>;
}
