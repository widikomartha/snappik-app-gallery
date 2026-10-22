import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Dialog } from '../../components/ui/dialog';
import { Status } from '../../components/ui/status';

type State = { epoch: number; version: number; revision: string; allowance: number; count: number; locked: boolean; confirmedAt: string | null; accountState: string; canWrite: boolean };
type Photo = { file_id: string; name: string; path: string; entry_id: number | null; selected: boolean };
type Page<T> = { data: T[]; current_page: number; last_page: number; total: number };
type Gallery = { state: State; photos: Page<Photo>; folders: Page<{ id: number; path: string }>; folder: string | null; legacyFolders: boolean };
type Operation = { endpoint: 'choices' | 'confirm'; payload: { operation_id: string; epoch: number; version: number; revision: string; file_id?: string; selected?: boolean } };

function Preview({ photo, base, original = false }: { photo: Photo; base: string; original?: boolean }) {
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  return <div className="photo-preview">{photo.entry_id && !failed ? <img src={`${base}/preview/${photo.entry_id}?attempt=${attempt}&original=${original ? '1' : '0'}`} alt={photo.name} loading="lazy" onError={() => setFailed(true)} /> : <div className="stack"><p>Pratinjau tidak tersedia.</p>{photo.entry_id && <Button variant="secondary" onClick={() => { setAttempt(n => n + 1); setFailed(false); }}>Coba pratinjau lagi</Button>}</div>}</div>;
}

export default function Selection() {
  const base = window.location.pathname;
  const [gallery, setGallery] = useState<Gallery | null>(null);
  const [query, setQuery] = useState({ page: 1, folder_page: 1, folder: '', review: false });
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(false);
  const [message, setMessage] = useState('Memuat galeri…');
  const [tone, setTone] = useState<'info' | 'error' | 'warning' | 'success'>('info');
  const [uncertain, setUncertain] = useState<Operation | null>(null);
  const [zoom, setZoom] = useState<number | null>(null);
  const [confirmation, setConfirmation] = useState<Operation | null>(null);
  const serial = useRef(0);
  const load = useCallback(async () => {
    const sequence = ++serial.current;
    setBusy(true);
    try {
      const params = new URLSearchParams({ page: String(query.page), folder_page: String(query.folder_page), review: query.review ? '1' : '0', ...(query.folder ? { folder: query.folder } : {}) });
      const response = await fetch(`${base}/data?${params}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) throw new Error();
      const data = await response.json() as Gallery;
      if (sequence !== serial.current) return;
      setGallery(data); setStale(false);
      setMessage('Pilihan tersimpan dimuat dari server.'); setTone('info');
      return true;
    } catch {
      if (sequence !== serial.current) return;
      setStale(true); setTone('error');
      setMessage('Galeri gagal dimuat. Periksa koneksi dan muat ulang. Jika akses ditutup, kembali ke halaman galeri untuk memeriksa kata sandi atau batas waktu.');
    } finally { if (sequence === serial.current) setBusy(false); }
  }, [base, query]);
  useEffect(() => { void load(); return () => { serial.current++; }; }, [load]);

  function operation(endpoint: Operation['endpoint'], photo?: Photo): Operation {
    const state = gallery!.state;
    return { endpoint, payload: { operation_id: crypto.randomUUID(), epoch: state.epoch, version: state.version, revision: state.revision,
      ...(photo ? { file_id: photo.file_id, selected: !photo.selected } : {}) } };
  }
  async function send(op: Operation) {
    setBusy(true); setTone('info'); setMessage('Menyimpan…'); setConfirmation(null);
    try {
      const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
      const response = await fetch(`${base}/${op.endpoint}`, { method: 'POST', credentials: 'same-origin', headers: {
        Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
      }, body: JSON.stringify(op.payload) });
      // A non-JSON/server error may leave commit status uncertain. Retain this operation ID.
      const result = await response.json() as { state?: State; message?: string };
      if (response.status >= 500) throw new Error();
      setUncertain(null);
      if (result.state) setGallery(current => current ? { ...current, state: result.state! } : current);
      if (!response.ok) {
        setStale(true); setTone(response.status === 409 ? 'warning' : 'error');
        setMessage(result.message ?? 'Perubahan ditolak. Muat ulang galeri untuk memeriksa akses dan pilihan.');
      } else {
        if (await load()) {
          setTone('success'); setMessage(op.endpoint === 'confirm' ? 'Konfirmasi tersimpan. Pilihan dikunci.' : 'Tersimpan di server.');
        }
      }
    } catch {
      setUncertain(op); setStale(true); setTone('error');
      setMessage('Penyimpanan belum dapat dipastikan. Periksa koneksi, lalu periksa server atau coba operasi yang sama lagi. Tidak ada perubahan yang dikirim otomatis.');
    } finally { setBusy(false); }
  }
  const state = gallery?.state;
  const disabled = busy || stale || !!uncertain;
  const writable = !!state?.canWrite && !disabled;
  const photo = zoom === null ? null : gallery?.photos.data[zoom];
  return <Shell surface="client"><Head title="Pemilihan foto"><meta name="robots" content="noindex,nofollow,noarchive" /></Head><div className="stack">
    <Link href={base.replace(/\/selection$/, '')}>Kembali ke galeri</Link><h1>Pemilihan foto</h1>
    <Status tone={tone}>{message}</Status>
    <div className="actions"><Button variant="secondary" disabled={busy} onClick={() => { setZoom(null); void load(); }}>Periksa pilihan di server</Button>{uncertain && <><Button disabled={busy} onClick={() => void send(uncertain)}>Coba simpan operasi yang sama</Button><Button variant="ghost" disabled={busy} onClick={() => { setUncertain(null); void load(); }}>Lepaskan operasi dan muat ulang</Button></>}</div>
    {state && <><section className="panel stack" aria-label="Status pilihan"><h2>{state.count} / {state.allowance} foto dipilih</h2>
      {state.locked ? <Status tone="success">Pilihan dikonfirmasi dan dikunci · {new Date(state.confirmedAt!).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA</Status> : state.accountState === 'expired' ? <Status tone="warning">Masa akses fotografer berakhir. Pilihan tersimpan hanya dapat dibaca.</Status> : <p>Pilih hingga {state.allowance} foto. Tidak perlu memenuhi seluruh batas untuk konfirmasi.</p>}
      <div className="actions"><Button variant={!query.review ? 'primary' : 'secondary'} disabled={disabled} onClick={() => { setZoom(null); setQuery(q => ({ ...q, page: 1, review: false })); }}>Semua foto</Button><Button variant={query.review ? 'primary' : 'secondary'} disabled={disabled} onClick={() => { setZoom(null); setQuery(q => ({ ...q, page: 1, review: true, folder: '' })); }}>Tinjau pilihan</Button></div>
    </section>
    {!query.review && <nav className="panel stack" aria-label="Folder foto"><h2>Folder</h2>{gallery!.legacyFolders && <Status tone="warning">Katalog lama: semua foto tersedia. Minta fotografer menyegarkan katalog untuk navigasi folder.</Status>}<p>{gallery!.folder ? `${gallery!.folder} · termasuk subfolder` : 'Semua folder'}</p><div className="actions"><Button variant="ghost" disabled={disabled} onClick={() => { setZoom(null); setQuery(q => ({ ...q, folder: '', page: 1 })); }}>Semua folder</Button>{gallery!.folders.data.map(folder => <Button key={folder.id} variant="secondary" disabled={disabled} onClick={() => { setZoom(null); setQuery(q => ({ ...q, folder: String(folder.id), page: 1 })); }}>{folder.path}</Button>)}</div>
      {gallery!.folders.last_page > 1 && <div className="actions"><Button disabled={disabled || query.folder_page <= 1} onClick={() => setQuery(q => ({ ...q, folder_page: q.folder_page - 1 }))}>Folder sebelumnya</Button><span>Halaman folder {gallery!.folders.current_page} / {gallery!.folders.last_page}</span><Button disabled={disabled || query.folder_page >= gallery!.folders.last_page} onClick={() => setQuery(q => ({ ...q, folder_page: q.folder_page + 1 }))}>Folder berikutnya</Button></div>}
    </nav>}
    {query.review && <section className="panel stack"><h2>Tinjau sebelum konfirmasi</h2><p>Periksa pilihan tersimpan di semua halaman. Konfirmasi mengunci satu set bersama untuk proyek ini.</p><Button disabled={!writable || state.count < 1} onClick={() => setConfirmation(operation('confirm'))}>Konfirmasi pilihan</Button></section>}
    {gallery!.photos.data.length === 0 && <Status>{query.review ? 'Belum ada foto dipilih.' : 'Tidak ada foto di folder atau halaman ini.'}</Status>}
    <div className="client-photo-grid" aria-label={query.review ? 'Foto pilihan' : 'Foto galeri'} aria-busy={busy}>{gallery!.photos.data.map((item, index) => <article className="panel photo-tile stack" key={item.file_id}>
      <Preview key={`${state.revision}-${item.file_id}`} photo={item} base={base} />
      <div className="stack"><strong>{item.name}</strong><span className="muted photo-path">{item.path}</span>{!item.entry_id && <Status tone="warning">Foto tidak tersedia di katalog terbaru. Pilihan tetap tersimpan; hapus sebelum konfirmasi.</Status>}
        <Button variant="secondary" disabled={!item.entry_id || disabled} onClick={() => setZoom(index)} aria-label={`Perbesar ${item.path}`}>Perbesar</Button>
        <Button disabled={!writable} variant={item.selected ? 'primary' : 'secondary'} aria-pressed={item.selected} aria-label={`${item.selected ? 'Hapus pilihan' : 'Pilih'} ${item.path}`} onClick={() => void send(operation('choices', item))}>{item.selected ? '✓ Dipilih · hapus pilihan' : 'Pilih foto'}</Button>
      </div></article>)}</div>
    <nav className="actions" aria-label="Halaman foto"><Button disabled={disabled || query.page <= 1} onClick={() => { setZoom(null); setQuery(q => ({ ...q, page: q.page - 1 })); }}>Foto sebelumnya</Button><span>Halaman {gallery!.photos.current_page} / {gallery!.photos.last_page} · {gallery!.photos.total} foto</span><Button disabled={disabled || query.page >= gallery!.photos.last_page} onClick={() => { setZoom(null); setQuery(q => ({ ...q, page: q.page + 1 })); }}>Foto berikutnya</Button></nav>
    </>}
    <Dialog open={photo !== null && photo !== undefined} onClose={() => setZoom(null)} title={photo?.name ?? 'Pratinjau foto'}>{photo && <div className="stack"><Preview key={photo.file_id} photo={photo} base={base} original /><p>{photo.path} · Foto {zoom! + 1} pada halaman ini</p><div className="actions"><Button variant="secondary" disabled={zoom === 0} onClick={() => setZoom(n => n! - 1)}>Sebelumnya</Button><Button variant="secondary" disabled={zoom === gallery!.photos.data.length - 1} onClick={() => setZoom(n => n! + 1)}>Berikutnya</Button></div></div>}</Dialog>
    <Dialog open={!!confirmation} onClose={() => setConfirmation(null)} title="Kunci pilihan bersama?"><p>{state?.count} foto akan dikonfirmasi. Hanya fotografer yang dapat membuka kembali pemilihan.</p><div className="actions"><Button disabled={busy} onClick={() => confirmation && void send(confirmation)}>Ya, konfirmasi dan kunci</Button><Button variant="ghost" onClick={() => setConfirmation(null)}>Batal</Button></div></Dialog>
  </div></Shell>;
}
