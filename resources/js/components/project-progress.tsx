import { Link } from '@inertiajs/react';
import { Status } from './ui/status';
import { Button } from './ui/button';
import { contextLabels, localTime, selectionLabels, type Overview, type Progress } from '../lib/progress';
import { useProgress } from '../hooks/use-progress';
import { FolderOpen, Refresh, Add } from 'iconsax-reactjs';
import { EmptyState } from './ui/empty-state';

export function ProgressStatus({ progress, compact = false }: { progress: Progress; compact?: boolean }) {
  return <div className="stack"><Status tone={progress.status === 'confirmed' ? 'success' : 'info'}>{selectionLabels[progress.status]} · {progress.count} / {progress.allowance} foto dipilih</Status>
    {compact && <div className="selection-meter" role="progressbar" aria-label="Foto dipilih" aria-valuenow={progress.count} aria-valuemin={0} aria-valuemax={progress.allowance}><span style={{ width: `${Math.min(100, progress.allowance > 0 ? progress.count / progress.allowance * 100 : 0)}%` }} /></div>}
    <div className="progress-modules"><span>Pemilihan: {progress.publication.selection ? 'diterbitkan' : 'belum dibuka'}</span><span>Hasil akhir: {progress.publication.delivery ? 'diterbitkan' : 'belum dibuka'}</span></div>
    {progress.context.map(context => <p className="muted" key={context}>{contextLabels[context]}</p>)}
    <p className={compact ? 'progress-meta' : undefined}>{progress.updatedAt ? `Pilihan terakhir disimpan: ${localTime(progress.updatedAt)}` : 'Klien belum menyimpan pilihan.'}</p>
    {!compact && progress.confirmedAt && <p>Konfirmasi saat ini: {localTime(progress.confirmedAt)}</p>}
    {!compact && <p>{progress.confirmationCount} konfirmasi tersimpan. Pilihan hanya siap diproses setelah klien mengonfirmasi.</p>}
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
    expired: 'Masa aktif akun berakhir. Proyek dan pilihan klien tetap tersimpan. Perpanjang Pro untuk mengubah proyek atau membuka pemilihan kembali.',
    suspended: 'Akun ditangguhkan. Galeri publik, perubahan proyek, dan pembayaran baru dinonaktifkan. Riwayat pembayaran tetap dapat dibaca. Hubungi pengelola SnapPik.',
  };
  return <section className="stack" aria-label="Progres proyek">
    {!denied && <>
      <Status tone={['expired', 'suspended'].includes(data.accountState) ? 'warning' : 'info'}>{messages[data.accountState] ?? 'Status akun tidak tersedia.'}</Status>
    </>}
    <div className="overview-toolbar"><div className="actions">
      {!denied && dashboard && data.accountState !== 'suspended' && <Link className="button button--primary" href="/projects">Kelola proyek</Link>}
      {!denied && !dashboard && ['trial', 'pro'].includes(data.accountState) && <Link className="button button--primary" href="/projects/create"><Add size={21} aria-hidden="true" />Buat proyek</Link>}
      <Button variant="secondary" pending={pending} onClick={() => void refresh()}><Refresh size={20} aria-hidden="true" />Periksa progres</Button></div>
      <p className="muted">Diperiksa: {localTime(data.checkedAt)} · diperbarui setiap 15 detik.</p></div>
    {error && <Status tone="warning">{error}</Status>}
    {!denied && data.projects && <>
      {data.projects.data.length === 0 && <EmptyState title="Mulai dengan proyek pertama Anda." description="Siapkan folder foto di Google Drive, lalu buat proyek dan tentukan jumlah foto yang boleh dipilih klien." scene="projects" />}
      <div className="project-grid">{data.projects.data.map(project => <article className="panel stack project-card" key={project.id}>
        <h2><FolderOpen size={26} aria-hidden="true" /><Link href={`/projects/${project.id}`}>{project.name}</Link></h2>
        <ProgressStatus progress={project} compact />
        <div className="actions project-card-actions"><Link className={`button button--${project.count > 0 ? 'primary' : 'secondary'}`} href={`/projects/${project.id}/results`}>Lihat hasil dan salin nama berkas</Link><Link href={`/projects/${project.id}`}>{project.publication.selection ? 'Kelola galeri' : 'Siapkan galeri'}</Link></div>
      </article>)}</div>
      <nav className="actions" aria-label="Halaman proyek">{data.projects.prev_page_url && <Link href={data.projects.prev_page_url}>Sebelumnya</Link>}{data.projects.next_page_url && <Link href={data.projects.next_page_url}>Berikutnya</Link>}</nav>
    </>}
  </section>;
}
