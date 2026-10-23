import { PageHeader } from '../../components/ui/page-header';
import { Head, router, useForm } from '@inertiajs/react';
import { Operations, Audit, Pagination, idr, instant, type Page, type AuditRow } from './operations';
import { Field } from '../../components/ui/field';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';
import { useUnsavedChanges } from '../../hooks/use-unsaved-changes';
type Plan = { id: number; price_idr: number; duration_days: number; created_at: string };
export default function PlanPage({ plan, versions, audit }: { plan: Plan; versions: Page<Plan>; audit: Page<AuditRow> }) {
  const form = useForm<{ price_idr: string; expected_version: number; reason: string; operation?: string }>({ price_idr: String(plan.price_idr), expected_version: plan.id, reason: '' });
  useUnsavedChanges(form.isDirty && !form.processing);
  return <Operations><Head title="Harga Pro" /><PageHeader title="Harga Pro" scene="billing" /><p>Harga saat ini {idr(plan.price_idr)} / 30 hari · Versi {plan.id}. Harga baru hanya berlaku untuk pesanan baru. Pesanan yang sudah dibuat mempertahankan harga, durasi dan voucher asal.</p>
    <form className="panel stack" aria-label="Versi harga Pro" onSubmit={e => { e.preventDefault(); form.put('/admin/plan', { onSuccess: () => router.get(window.location.href, {}, { preserveState: false, preserveScroll: true }) }); }}>
      {form.errors.operation && <Status tone="error">{form.errors.operation}</Status>}
      <Field label="Harga baru (IDR)" type="number" min={1} max={9007199254740991} step={1} required value={form.data.price_idr} onChange={e => form.setData('price_idr', e.target.value)} error={form.errors.price_idr} />
      <Field label="Alasan perubahan" required maxLength={500} value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} error={form.errors.reason} />
      <Button type="submit" pending={form.processing}>Simpan versi harga</Button><Button variant="ghost" disabled={form.processing} onClick={() => router.get(window.location.href, {}, { preserveState: false, preserveScroll: true })}>Muat ulang (buang perubahan)</Button>
    </form><section className="panel stack" aria-label="Riwayat harga"><h2>Riwayat harga</h2>{versions.data.map(v => <p key={v.id}>Versi {v.id} · {idr(v.price_idr)} / {v.duration_days} hari · {instant(v.created_at)}</p>)}<Pagination page={versions} label="Halaman harga" /></section><Audit audit={audit} />
  </Operations>;
}
