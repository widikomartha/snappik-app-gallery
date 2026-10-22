import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from './ui/button';
import { Status } from './ui/status';

type Archive = { id: string; state: 'queued' | 'processing' | 'ready' | 'failed' | 'expired'; files: number; completedFiles: number; expiresAt: string | null; failure: string | null };

export function DeliveryArchive({ base }: { base: string }) {
  const [archive, setArchive] = useState<Archive | null>(null);
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(true);
  const [denied, setDenied] = useState(false);
  const serial = useRef(0);
  const sending = useRef(false);
  const load = useCallback(async (create = false) => {
    if (sending.current) return;
    if (create) sending.current = true;
    const sequence = ++serial.current;
    setBusy(true);
    try {
      const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
      const response = await fetch(`${base}/archive`, { method: create ? 'POST' : 'GET', cache: 'no-store',
        headers: { Accept: 'application/json', ...(create ? { 'X-CSRF-TOKEN': token } : {}) } });
      if (!response.ok) {
        if (sequence === serial.current && [401, 403, 404, 419].includes(response.status)) { setDenied(true); setArchive(null); }
        throw new Error();
      }
      const result = await response.json() as { archive: Archive | null };
      if (sequence === serial.current) { setArchive(result.archive); setStale(false); setDenied(false); }
    } catch { if (sequence === serial.current) setStale(true); }
    finally { if (create) sending.current = false; if (sequence === serial.current) setBusy(false); }
  }, [base]);
  useEffect(() => {
    void load();
    const timer = window.setInterval(() => { if (document.visibilityState === 'visible') void load(); }, 5000);
    const visible = () => { if (document.visibilityState === 'visible') void load(); };
    document.addEventListener('visibilitychange', visible);
    return () => { serial.current++; clearInterval(timer); document.removeEventListener('visibilitychange', visible); };
  }, [load]);
  const active = archive?.state === 'queued' || archive?.state === 'processing';
  return <section className="panel stack" aria-label="Unduh semua foto">
    <h2>Unduh semua foto</h2><p>Satu ZIP berisi seluruh hasil akhir, termasuk semua subfolder dan halaman. Proses tetap berjalan saat browser ditutup.</p>
    {stale && <Status tone="warning">Status ZIP belum diperbarui. Periksa koneksi atau kembali ke galeri untuk memeriksa akses. Permintaan yang belum mendapat balasan dapat diperiksa atau dicoba lagi dengan aman.</Status>}
    {archive?.state === 'queued' && <Status>ZIP menunggu antrean.</Status>}
    {archive?.state === 'processing' && <Status>Menyiapkan ZIP · {archive.completedFiles} / {archive.files} foto selesai.</Status>}
    {archive?.state === 'ready' && <Status tone="success">ZIP lengkap siap · {archive.files} foto. Berlaku sampai {archive.expiresAt && new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Makassar' }).format(new Date(archive.expiresAt))} WITA (24 jam setelah siap).</Status>}
    {archive?.state === 'failed' && <Status tone="error">ZIP gagal. Tidak ada arsip sebagian yang dapat diunduh. {archive.failure === 'archive_worker_interrupted' ? 'Pekerja terhenti; coba lagi.' : archive.failure?.includes('capacity') ? 'Batas teknis penyedia tercapai. Hubungi fotografer.' : 'Sumber atau izin mungkin berubah. Coba lagi setelah sumber tersedia atau hubungi fotografer.'}</Status>}
    {archive?.state === 'expired' && <Status tone="warning">ZIP kedaluwarsa. Buat ulang untuk mengunduh seluruh foto.</Status>}
    <div className="actions"><Button variant="secondary" disabled={busy} onClick={() => void load()}>Periksa status ZIP</Button>
      {!denied && !active && archive?.state !== 'ready' && <Button disabled={busy} onClick={() => void load(true)}>{archive ? 'Buat ulang ZIP' : 'Siapkan ZIP semua foto'}</Button>}
      {!stale && archive?.state === 'ready' && <a className="button button--primary" href={`${base}/archive/${archive.id}`} target="_blank" rel="noopener noreferrer">Unduh ZIP lengkap</a>}
    </div>
  </section>;
}
