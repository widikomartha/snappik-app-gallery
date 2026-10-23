import { Head } from '@inertiajs/react';
import { Operations, ReasonAction, Audit, Pagination, OrderRecords, instant, stateLabel, type Page, type AuditRow, type OrderRow } from './operations';
import type { Account } from './accounts';
type Project = { id: string; name: string; archived_at: string | null; selection_locked_at: string | null; created_at: string };
type Grant = { order_id: string; paid_at: string; starts_at: string; ends_at: string; duration_days: number };
export default function AccountDetail({ account: a, projects, orders, grants, audit }: { account: Account; projects: Page<Project>; orders: Page<OrderRow>; grants: Page<Grant>; audit: Page<AuditRow> }) {
  return <Operations><Head title={a.name} /><h1>{a.name}</h1><p>{a.email} · {stateLabel(a.state)} · Akses sampai {instant(a.accessEndsAt)}</p>
    <section className="panel stack" aria-label="Status akun"><h2>Suspensi akun</h2><p>Penangguhan menutup permintaan galeri baru dan perubahan akun. Tagihan tetap dapat dibaca. Reaktivasi mengikuti masa akses dan pengaturan galeri yang tersimpan.</p>
      <ReasonAction key={a.id} url={`/admin/accounts/${a.id}/suspension`} label={a.state === 'suspended' ? 'Aktifkan kembali akun' : 'Tangguhkan akun'} suspended={a.state !== 'suspended'} version={a.suspension_version} method="put" />
    </section>
    <section className="panel stack" aria-label="Metadata proyek"><h2>Metadata proyek</h2>{!projects.data.length && <p>Belum ada proyek.</p>}
      {projects.data.map(p => <article key={p.id}><h3>{p.name}</h3><p>{p.id} · {p.archived_at ? 'Diarsipkan' : 'Aktif'} · {p.selection_locked_at ? 'Pilihan dikonfirmasi' : 'Belum dikonfirmasi'}</p></article>)}<Pagination page={projects} label="Halaman metadata proyek" />
    </section>
    <section className="panel stack" aria-label="Riwayat akses"><h2>Pemberian akses terverifikasi</h2>{!grants.data.length && <p>Belum ada pemberian akses berbayar.</p>}{grants.data.map(g => <article key={g.order_id}><p>{g.order_id} · {g.duration_days} hari · Dibayar {instant(g.paid_at)}</p><p>{instant(g.starts_at)} → {instant(g.ends_at)}</p></article>)}<Pagination page={grants} label="Halaman akses" /></section>
    <OrderRecords orders={orders} /><Audit audit={audit} />
  </Operations>;
}
