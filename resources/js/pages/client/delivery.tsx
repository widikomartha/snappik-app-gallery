import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Dialog } from '../../components/ui/dialog';
import { Status } from '../../components/ui/status';
import { GalleryTile } from '../../components/ui/gallery-tile';
import { DeliveryArchive } from '../../components/delivery-archive';

type Photo = { id: number; name: string; path: string };
type Page<T> = { data: T[]; current_page: number; last_page: number; total: number };
type Gallery = { revision: string; photos: Page<Photo>; folders: Page<{ id: number; path: string }>; folder: string | null; legacyFolders: boolean };

function Preview({ photo, base, original = false }: { photo: Photo; base: string; original?: boolean }) {
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  return <div className="photo-preview">{failed ? <div className="stack"><p>Pratinjau tidak tersedia. Sumber atau izin mungkin berubah.</p><Button variant="secondary" onClick={() => { setFailed(false); setAttempt(n => n + 1); }}>Coba pratinjau lagi</Button></div> : <img src={`${base}/media/${photo.id}?thumbnail=${original ? '0' : '1'}&attempt=${attempt}`} alt={photo.name} loading="lazy" onError={() => setFailed(true)} />}</div>;
}

function Download({ photo, base }: { photo: Photo; base: string }) {
  // Native download streaming keeps large originals out of JavaScript memory.
  // On failure the new tab displays the server's text error instead of saving HTML as a photo.
  return <a className="button button--secondary" href={`${base}/media/${photo.id}?download=1`} target="_blank" rel="noopener noreferrer" aria-label={`Unduh asli ${photo.path}`}>Unduh berkas asli</a>;
}

export default function Delivery() {
  const base = window.location.pathname;
  const [gallery, setGallery] = useState<Gallery | null>(null);
  const [query, setQuery] = useState({ page: 1, folder_page: 1, folder: '' });
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const [zoom, setZoom] = useState<number | null>(null);
  const sequence = useRef(0);
  const load = useCallback(async () => {
    const serial = ++sequence.current;
    setBusy(true);
    try {
      const params = new URLSearchParams({ page: String(query.page), folder_page: String(query.folder_page), ...(query.folder ? { folder: query.folder } : {}) });
      const response = await fetch(`${base}/data?${params}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) throw new Error();
      const data = await response.json() as Gallery;
      if (serial !== sequence.current) return;
      setGallery(data); setFailed(false); setZoom(null);
    } catch {
      if (serial === sequence.current) { setFailed(true); setZoom(null); }
    } finally { if (serial === sequence.current) setBusy(false); }
  }, [base, query]);
  useEffect(() => { void load(); return () => { sequence.current++; }; }, [load]);
  const photo = zoom === null ? null : gallery?.photos.data[zoom];
  const disabled = busy || failed;
  return <Shell surface="client"><Head title="Hasil akhir"><meta name="robots" content="noindex,nofollow,noarchive" /></Head><div className="stack">
    <Link href={base.replace(/\/delivery$/, '')}>Kembali ke galeri</Link><h1>Hasil akhir</h1>
    <p>Unduh berkas asli melalui SnapPik. Jika unduhan gagal, pesan kesalahan tampil di tab baru. Coba lagi atau hubungi fotografer; periksa status selesai di unduhan browser.</p>
    {busy && <Status>Memuat hasil akhir…</Status>}
    {failed && <Status tone="error">Galeri gagal dimuat. Periksa koneksi atau kembali ke galeri untuk memeriksa kata sandi dan batas waktu. Tampilan sebelumnya belum diperbarui.</Status>}
    <Button variant="secondary" disabled={busy} onClick={() => void load()}>Periksa hasil akhir terbaru</Button>
    <DeliveryArchive base={base} />
    {gallery && <><nav className="panel stack" aria-label="Folder hasil akhir"><h2>Folder</h2>{gallery.legacyFolders && <Status tone="warning">Katalog lama: semua foto tersedia. Minta fotografer menyegarkan katalog untuk navigasi folder.</Status>}<p>{gallery.folder ? `${gallery.folder} · termasuk subfolder` : 'Semua folder'}</p><div className="actions"><Button variant="ghost" disabled={disabled} onClick={() => setQuery(q => ({ ...q, folder: '', page: 1 }))}>Semua folder</Button>{gallery.folders.data.map(folder => <Button key={folder.id} variant="secondary" disabled={disabled} onClick={() => setQuery(q => ({ ...q, folder: String(folder.id), page: 1 }))}>{folder.path}</Button>)}</div>
      {gallery.folders.last_page > 1 && <div className="actions"><Button disabled={disabled || query.folder_page <= 1} onClick={() => setQuery(q => ({ ...q, folder_page: q.folder_page - 1 }))}>Folder sebelumnya</Button><span>Halaman folder {gallery.folders.current_page} / {gallery.folders.last_page}</span><Button disabled={disabled || query.folder_page >= gallery.folders.last_page} onClick={() => setQuery(q => ({ ...q, folder_page: q.folder_page + 1 }))}>Folder berikutnya</Button></div>}
    </nav>
      {gallery.photos.data.length === 0 && <Status>Tidak ada foto di folder atau halaman ini.</Status>}
      <div className="client-photo-grid" aria-label="Foto hasil akhir" aria-busy={busy}>{gallery.photos.data.map((item, index) => <GalleryTile className="panel photo-tile stack" index={index} key={item.id}><Preview photo={item} base={base} /><strong>{item.name}</strong><span className="muted photo-path">{item.path}</span><Button variant="secondary" disabled={disabled} onClick={() => setZoom(index)} aria-label={`Perbesar ${item.path}`}>Perbesar</Button>{!disabled && <Download photo={item} base={base} />}</GalleryTile>)}</div>
      <nav className="actions" aria-label="Halaman foto"><Button disabled={disabled || query.page <= 1} onClick={() => setQuery(q => ({ ...q, page: q.page - 1 }))}>Foto sebelumnya</Button><span>Halaman {gallery.photos.current_page} / {gallery.photos.last_page} · {gallery.photos.total} foto</span><Button disabled={disabled || query.page >= gallery.photos.last_page} onClick={() => setQuery(q => ({ ...q, page: q.page + 1 }))}>Foto berikutnya</Button></nav>
    </>}
    <Dialog open={!!photo} onClose={() => setZoom(null)} title={photo?.name ?? 'Pratinjau hasil akhir'}>{photo && <div className="stack"><Preview key={photo.id} photo={photo} base={base} original /><p>{photo.path} · Foto {zoom! + 1} pada halaman ini</p><Download photo={photo} base={base} /><div className="actions"><Button variant="secondary" disabled={zoom === 0} onClick={() => setZoom(n => n! - 1)}>Sebelumnya</Button><Button variant="secondary" disabled={zoom === gallery!.photos.data.length - 1} onClick={() => setZoom(n => n! + 1)}>Berikutnya</Button></div></div>}</Dialog>
  </div></Shell>;
}
