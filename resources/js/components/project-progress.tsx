import { Link } from '@inertiajs/react';
import { Status } from './ui/status';
import { Button } from './ui/button';
import { contextLabels, localTime, selectionLabels, type Overview, type Progress } from '../lib/progress';
import { useProgress } from '../hooks/use-progress';

export function ProgressStatus({ progress }: { progress: Progress }) {
  return <div className="stack"><Status tone={progress.status === 'confirmed' ? 'success' : 'info'}>{selectionLabels[progress.status]} · {progress.count} / {progress.allowance} foto dipilih</Status>
    <p>Pemilihan: {progress.publication.selection ? 'diterbitkan' : 'draf / ditutup'} · Hasil akhir: {progress.publication.delivery ? 'diterbitkan' : 'draf / ditutup'}</p>
    {progress.context.map(context => <p className="muted" key={context}>{contextLabels[context]}</p>)}
    <p>Pembaruan pilihan tercatat: {localTime(progress.updatedAt)}</p>
    {progress.confirmedAt && <p>Konfirmasi saat ini: {localTime(progress.confirmedAt)}</p>}
    <p>{progress.confirmationCount} konfirmasi tersimpan dalam riwayat.</p>
    {progress.catalogueStale && <Status tone="warning">Penyegaran katalog gagal. Katalog sebelumnya dipertahankan; ketersediaan sumber mungkin tidak mutakhir.</Status>}
    {['queued', 'importing'].includes(progress.catalogueState) && <Status>Impor katalog sedang berjalan. Hasil tersimpan tetap ditampilkan.</Status>}
  </div>;
}

export function ProgressOverview({ initial, dashboard = false }: { initial: Overview; dashboard?: boolean }) {
  const query = window.location.search;
  const { data, error, denied, pending, refresh } = useProgress(initial, `${dashboard ? '/dashboard' : '/projects'}/data${query}`);
  const messages: Record<string, string> = {
    trial: 'Uji coba aktif. Maksimal 3 proyek aktif, termasuk draf.',
    pro: 'Pro aktif. Tanpa kuota jumlah proyek, foto atau ukuran galeri.',
    expired: 'Akses berakhir. Metadata dan hasil tetap tersimpan dan dapat dibaca. Perubahan proyek dinonaktifkan. Buka langganan dan tagihan untuk memperpanjang.',
    suspended: 'Akun ditangguhkan. Galeri publik, perubahan proyek, dan pembayaran baru dinonaktifkan. Riwayat pembayaran tetap dapat dibaca. Hubungi pengelola SnapPik.',
  };
  return <section className="stack" aria-label="Progres proyek">
    {!denied && <>
      <Status tone={['expired', 'suspended'].includes(data.accountState) ? 'warning' : 'info'}>{messages[data.accountState] ?? 'Status akun tidak tersedia.'}</Status>
      {dashboard && data.accountState !== 'suspended' && <Link href="/projects">Kelola proyek</Link>}
      {!dashboard && ['trial', 'pro'].includes(data.accountState) && <Link className="button button--primary" href="/projects/create">Buat proyek</Link>}
    </>}
    <div className="actions"><Button variant="secondary" pending={pending} onClick={() => void refresh()}>Periksa progres</Button>
      <p className="muted">Diperiksa: {localTime(data.checkedAt)} · diperbarui setiap 15 detik.</p></div>
    {error && <Status tone="warning">{error}</Status>}
    {!denied && data.projects && <>
      {data.projects.data.length === 0 && <Status>Belum ada proyek. Buat proyek dengan folder Google Drive publik Anda.</Status>}
      {data.projects.data.map(project => <article className="panel stack" key={project.id}>
        <h2><Link href={`/projects/${project.id}`}>{project.name}</Link></h2>
        <ProgressStatus progress={project} />
        <div className="actions"><Link href={`/projects/${project.id}/results`}>Lihat hasil dan salin nama berkas</Link><Link href={`/projects/${project.id}`}>Pengaturan proyek</Link></div>
      </article>)}
      <nav className="actions" aria-label="Halaman proyek">{data.projects.prev_page_url && <Link href={data.projects.prev_page_url}>Sebelumnya</Link>}{data.projects.next_page_url && <Link href={data.projects.next_page_url}>Berikutnya</Link>}</nav>
    </>}
  </section>;
}
