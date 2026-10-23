import { Head, Link } from '@inertiajs/react';
import { Operations, ReasonAction, Audit, Pagination, instant, stateLabel, type Page, type AuditRow } from './operations';
type Job = { id: string; state: string; operations_version: number; name: string; user_id: number; type: string; diagnostic: string; updated_at: string };
type Cleanup = { id: string; archive_job_id: string; diagnostic: string; created_at: string };
function Jobs({ rows, type }: { rows: Page<Job>; type: 'import' | 'archive' }) {
  const label = type === 'import' ? 'Impor katalog' : 'ZIP';
  return <section className="stack" aria-label={label}><h2>{label}</h2>{!rows.data.length && <p>Tidak ada pekerjaan aktif atau gagal.</p>}{rows.data.map(j => <article key={j.id} className="panel stack" aria-label={`${label} ${j.name}`}><h3>{j.name} · {j.type}</h3><p>{j.id} · {stateLabel(j.state)} · {instant(j.updated_at)}</p><p>{j.diagnostic}</p><Link href={`/admin/accounts/${j.user_id}`}>Periksa akun pemilik</Link>{j.state === 'failed' && <ReasonAction url={`/admin/jobs/${type}/${j.id}/retry`} version={j.operations_version} label={`Coba ulang ${label}`} />}</article>)}<Pagination page={rows} label={`Halaman ${label}`} /></section>;
}
export default function JobsPage({ imports, archives, cleanup, audit }: { imports: Page<Job>; archives: Page<Job>; cleanup: Page<Cleanup>; audit: Page<AuditRow> }) {
  return <Operations><Head title="Pekerjaan" /><h1>Pekerjaan dan kegagalan</h1><p>Perbaiki izin atau konfigurasi sumber melalui fotografer sebelum mencoba kembali. Percobaan ulang memeriksa status akun, sumber dan gerbang galeri saat ini.</p><Jobs rows={imports} type="import" /><Jobs rows={archives} type="archive" />
    <section className="panel stack" aria-label="Pembersihan ZIP"><h2>Pembersihan ZIP</h2><p>Pekerja pemeliharaan memeriksa dan mencoba pembersihan lagi setiap menit. Percobaan aktif atau ZIP siap yang masih berlaku tetap dilindungi.</p>{!cleanup.data.length && <p>Tidak ada pembersihan tertunda.</p>}{cleanup.data.map(c => <article key={c.id}><p>Percobaan {c.id} · ZIP {c.archive_job_id} · {instant(c.created_at)}</p><p>{c.diagnostic}</p></article>)}<Pagination page={cleanup} label="Halaman pembersihan" /></section><Audit audit={audit} />
  </Operations>;
}
