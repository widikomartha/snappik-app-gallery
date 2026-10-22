import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';

export default function Diagnostics({ projectId, kind, records }: { projectId: string; kind: string; records: { data: { id: number; name: string; path: string }[]; total: number; prev_page_url: string | null; next_page_url: string | null } }) {
  const title = kind === 'missing' ? 'Pilihan tidak tersedia' : 'Berkas tidak didukung dan pintasan';
  return <Shell surface="photographer"><Head title={title} /><div className="stack"><Link href={`/projects/${projectId}`}>Kembali ke proyek</Link><h1>{title}</h1><p>{records.total} catatan</p>
    <ul className="stack">{records.data.map(record => <li key={record.id}>{record.path}</li>)}</ul>
    <nav className="actions" aria-label="Halaman diagnostik">{records.prev_page_url && <Link href={records.prev_page_url}>Sebelumnya</Link>}{records.next_page_url && <Link href={records.next_page_url}>Berikutnya</Link>}</nav>
  </div></Shell>;
}
