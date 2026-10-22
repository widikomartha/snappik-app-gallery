import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';
import { Button } from '../../components/ui/button';
import { ProgressStatus } from '../../components/project-progress';
import { useProgress } from '../../hooks/use-progress';
import { localTime, type Page, type Progress } from '../../lib/progress';

type Results = {
  progress: Progress; checkedAt: string;
  records: Page<{ id: number; file_id: string; name: string; path: string; available: number }>;
  history: Page<{ id: string; epoch: number; confirmed_at: string; count: number }>;
  snapshot: { id: string; epoch: number; confirmedAt: string } | null;
};

function FilenameExport({ url, historical }: { url: string; historical: boolean }) {
  const [output, setOutput] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [pending, setPending] = useState(false);
  const text = useRef<HTMLTextAreaElement>(null);
  const active = useRef<AbortController | null>(null);
  useEffect(() => () => { active.current?.abort(); active.current = null; }, []);
  async function prepare() {
    const controller = new AbortController(); active.current = controller;
    setPending(true); setMessage(''); setOutput(null);
    const timeout = window.setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(url, { headers: { Accept: 'text/plain' }, cache: 'no-store', signal: controller.signal });
      if (!response.ok || !response.headers.get('content-type')?.startsWith('text/plain')) throw new Error();
      const value = await response.text();
      if (active.current === controller) { setOutput(value); setMessage(value ? 'Daftar lengkap siap disalin.' : 'Belum ada nama berkas untuk disalin.'); }
    } catch { if (active.current === controller) setMessage('Daftar belum dapat dimuat. Periksa hasil terbaru, lalu coba lagi.'); }
    finally { window.clearTimeout(timeout); if (active.current === controller) { active.current = null; setPending(false); } }
  }
  async function copy() {
    if (!output) return;
    try { await navigator.clipboard.writeText(output); setMessage('Nama berkas disalin.'); }
    catch { setMessage('Papan klip ditolak. Pilih dan salin teks di bawah secara manual.'); text.current?.focus(); text.current?.select(); }
  }
  return <section className="panel stack" aria-label="Ekspor nama berkas">
    <h2>Salin nama berkas</h2>
    <p>Daftar lengkap {historical ? 'konfirmasi yang dipilih' : 'pilihan saat ini'}, satu berkas per baris. Nama sama memakai jalur folder; jalur sama disertai ID berkas. Karakter baris baru dan garis miring terbalik ditulis sebagai {'\\n, \\r dan \\\\'}.</p>
    <div className="actions"><Button pending={pending} onClick={() => void prepare()}>Siapkan daftar nama berkas</Button>
      {output && <Button variant="secondary" onClick={() => void copy()}>Salin nama berkas</Button>}</div>
    {message && <Status>{message}</Status>}
    {output !== null && <div className="field"><label htmlFor="filename-output">Daftar nama berkas lengkap</label>
      <textarea id="filename-output" ref={text} className="filename-output" readOnly rows={8} value={output} onFocus={event => event.target.select()} /></div>}
  </section>;
}

export default function ResultsPage(initial: Results) {
  const base = `/projects/${initial.progress.id}`;
  const { data, error, denied, pending, refresh } = useProgress(initial, `${base}/results/data${window.location.search}`);
  const { progress, records, history, snapshot } = data;
  const confirmation = snapshot ? `?confirmation=${snapshot.id}` : `?version=${progress.version}&revision=${progress.revision ?? ''}`;
  return <Shell surface="photographer"><Head title={`Hasil · ${progress.name}`} /><div className="stack">
    <nav className="actions"><Link href="/dashboard">Ruang fotografer</Link><Link href={`${base}`}>Pengaturan proyek</Link></nav>
    <h1>Hasil pemilihan · {progress.name}</h1>
    <div className="actions"><Button variant="secondary" pending={pending} onClick={() => void refresh()}>Periksa hasil terbaru</Button><p className="muted">Diperiksa: {localTime(data.checkedAt)} · diperbarui setiap 15 detik.</p></div>
    {error && <Status tone="warning">{error}</Status>}
    {!denied && <>
      <section className="panel stack" aria-label="Progres saat ini"><h2>Progres saat ini</h2><ProgressStatus progress={progress} /></section>
      <section className="panel stack" aria-label="Hasil tersimpan">
        <h2>{snapshot ? `Riwayat konfirmasi · putaran ${snapshot.epoch}` : 'Pilihan saat ini'}</h2>
        <p>{snapshot ? `Snapshot tetap dari ${localTime(snapshot.confirmedAt)}. ${progress.status === 'confirmed' && snapshot.epoch === progress.epoch ? 'Snapshot konfirmasi saat ini.' : 'Riwayat, bukan konfirmasi saat ini.'}` : progress.status === 'confirmed' ? 'Pilihan saat ini dikunci oleh konfirmasi.' : 'Pilihan draf yang sudah tersimpan, belum dikonfirmasi.'}</p>
        {snapshot && <Link href={`${base}/results`}>Kembali ke pilihan saat ini</Link>}
        <p>{records.total} berkas tersimpan · menampilkan hingga 20 per halaman.</p>
        {records.data.length === 0 && <Status>Belum ada pilihan di halaman ini.</Status>}
        <ul className="result-records">{records.data.map(record => <li key={record.file_id}><strong>{record.name}</strong><p className="photo-path">{record.path} · ID: {record.file_id}</p>{Number(record.available) === 0 && <p className="muted">Tidak tersedia dalam katalog terbit saat ini. Catatan hasil tetap disimpan.</p>}</li>)}</ul>
        <nav className="actions" aria-label="Halaman hasil">{records.prev_page_url && <Link href={records.prev_page_url}>Hasil sebelumnya</Link>}{records.next_page_url && <Link href={records.next_page_url}>Hasil berikutnya</Link>}</nav>
      </section>
      <FilenameExport key={`${snapshot?.id ?? 'current'}-${progress.version}-${progress.revision}`} url={`${base}/results/export${confirmation}`} historical={!!snapshot} />
      <section className="panel stack" aria-label="Riwayat konfirmasi"><h2>Riwayat konfirmasi</h2>
        {history.data.length === 0 && <Status>Belum ada konfirmasi. Pilihan draf tetap terlihat di atas.</Status>}
        {history.data.map(item => <article key={item.id}><h3><Link href={`${base}/results?confirmation=${item.id}`}>Konfirmasi putaran {item.epoch}</Link></h3>
          <p>{localTime(item.confirmed_at)} · {item.count} foto · {progress.status === 'confirmed' && item.epoch === progress.epoch ? 'Konfirmasi saat ini' : 'Riwayat, bukan konfirmasi saat ini'}</p></article>)}
        <nav className="actions" aria-label="Halaman riwayat">{history.prev_page_url && <Link href={history.prev_page_url}>Riwayat sebelumnya</Link>}{history.next_page_url && <Link href={history.next_page_url}>Riwayat berikutnya</Link>}</nav>
      </section>
    </>}
  </div></Shell>;
}
