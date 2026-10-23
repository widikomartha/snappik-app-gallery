import { Gallery, DocumentDownload, Setting2, Link21 } from 'iconsax-reactjs';
import { PageHeader } from '../../components/ui/page-header';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Shell } from '../../layouts/shell';
import { Field } from '../../components/ui/field';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';
import { useUnsavedChanges } from '../../hooks/use-unsaved-changes';

type Module = { type: 'selection' | 'delivery'; source: string; passwordRequired: boolean; deadline: string | null; published: boolean; state: string; failure: string | null; stale: boolean; photos: number; unsupported: number; unsupportedNames: string[]; missingChoices: number; missingNames: string[]; canPublish: boolean };
type Project = { id: string; name: string; contact: string | null; allowance: number; version: number; selection_epoch: number; selection_version: number; archived_at: string | null; selectionLocked: boolean; choiceCount: number; choicePreview: { file_id: string; name: string; path: string }[]; sharePath: string; modules: Module[] };
const titles = { selection: 'Pemilihan foto', delivery: 'Hasil akhir' };
const states: Record<string, string> = { empty: 'Belum ada sumber', queued: 'Menunggu impor', importing: 'Mengimpor', complete: 'Impor selesai', failed: 'Impor gagal', superseded: 'Digantikan impor baru' };
const remedies: Record<string, string> = {
  catalogue_sources_overlap: 'Sumber bertumpang tindih. Pilih folder pemilihan dan hasil akhir yang terpisah.',
  drive_no_supported_photos: 'Tidak ada foto JPEG/PNG. Periksa isi folder dan subfolder.',
  catalogue_access_closed: 'Akses pengelolaan ditutup. Periksa status akun dan arsip, lalu ulangi impor.',
};
function localDeadline(value?: string | null) {
  return value ? new Date(new Date(value).getTime() + 8 * 60 * 60 * 1000).toISOString().slice(0, 16) : '';
}

export default function ProjectPage({ project, canMutate }: { project: Project | null; canMutate: boolean }) {
  const selection = project?.modules.find(m => m.type === 'selection');
  const delivery = project?.modules.find(m => m.type === 'delivery');
  const form = useForm({ name: project?.name ?? '', contact: project?.contact ?? '', allowance: project?.allowance ?? 1,
    version: project?.version ?? 1, selection_source: selection?.source ?? '', delivery_source: delivery?.source ?? '',
    selection_password: '', delivery_password: '', selection_remove_password: false, delivery_remove_password: false,
    selection_deadline: localDeadline(selection?.deadline), delivery_deadline: localDeadline(delivery?.deadline) });
  const command = useForm<{ version: number; command: string; module: string | null }>({ version: project?.version ?? 1, command: '', module: null });
  const [commandError, setCommandError] = useState('');
  const [rotatePending, setRotatePending] = useState(false);
  const [reopenPending, setReopenPending] = useState(false);
  const [reopening, setReopening] = useState(false);
  const [copyMessage, setCopyMessage] = useState('');
  const shareInput = useRef<HTMLInputElement>(null);
  useEffect(() => setCopyMessage(''), [project?.sharePath]);
  const busy = project?.modules.some(m => ['queued', 'importing'].includes(m.state));
  const { start, stop } = usePoll(3000, { only: ['project', 'canMutate'], onSuccess: () => { /* Canonical job state only; form values stay mounted. */ } }, { autoStart: false });
  useEffect(() => { if (busy) start(); else stop(); }, [busy, start, stop]);
  const editable = canMutate && !project?.archived_at;
  useUnsavedChanges(form.isDirty && !form.processing);
  function save(e: FormEvent) {
    e.preventDefault();
    form.transform(data => ({ ...data,
      selection_deadline: data.selection_deadline ? `${data.selection_deadline}:00+08:00` : '',
      delivery_deadline: data.delivery_deadline ? `${data.delivery_deadline}:00+08:00` : '' }));
    const options = { preserveScroll: true, onSuccess: (page: { props: Record<string, unknown> }) => {
      const saved = page.props.project as Project;
      form.setData(data => ({ ...data, version: saved.version, selection_password: '', delivery_password: '', selection_remove_password: false, delivery_remove_password: false }));
      form.setDefaults({ ...form.data, version: saved.version, selection_password: '', delivery_password: '', selection_remove_password: false, delivery_remove_password: false });
    } };
    if (project) form.put(`/projects/${project.id}`, options); else form.post('/projects', options);
  }
  function act(action: string, module: string | null = null) {
    if (!project) return;
    setCommandError('');
    command.transform(() => ({ version: project.version, command: action, module }));
    command.post(`/projects/${project.id}/commands`, { preserveScroll: true, onError: errors => setCommandError(Object.values(errors).join(' ')), onSuccess: () => setRotatePending(false) });
  }
  async function reopen() {
    if (!project) return;
    setReopening(true); setCommandError('');
    try {
      const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
      const response = await fetch(`/projects/${project.id}/selection/reopen`, { method: 'POST', headers: {
        Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
      }, body: JSON.stringify({ epoch: project.selection_epoch, version: project.selection_version }) });
      if (!response.ok) throw new Error();
      setReopenPending(false);
      router.reload({ only: ['project', 'canMutate'] });
    } catch { setCommandError('Pemilihan belum dapat dibuka kembali. Periksa status terbaru, akses akun dan batas waktu sebelum mencoba lagi.'); }
    finally { setReopening(false); }
  }
  async function copyLink() {
    if (!project) return;
    try {
      await navigator.clipboard.writeText(new URL(project.sharePath, window.location.origin).href);
      setCopyMessage('Tautan disalin. Tempel dan kirim kepada klien Anda.');
    } catch {
      shareInput.current?.focus(); shareInput.current?.select();
      setCopyMessage('Tautan sudah dipilih. Gunakan perintah Salin pada perangkat Anda untuk menyalinnya secara manual.');
    }
  }
  const shareable = !!project && !project.archived_at && project.modules.some(module => module.published);
  return <Shell surface="photographer"><Head title={project?.name ?? 'Buat proyek'} /><div className="stack project-workspace">
    <div className="project-intro stack"><Link href="/projects">Semua proyek</Link><PageHeader title={project?.name ?? 'Buat proyek'} scene="projects" description={project ? 'Kelola foto yang dilihat klien, buka galeri, dan bagikan tautannya.' : 'Hubungkan folder Google Drive dan tentukan jumlah foto yang boleh dipilih. Galeri baru dibuka setelah Anda menerbitkannya.'} actions={project && <Link className="button button--secondary" href={`/projects/${project.id}/results`}>Lihat hasil pemilihan</Link>} />
    {(!project || !selection?.published) && <ol className="workflow-steps" aria-label="Langkah menyiapkan galeri"><li><span>1</span><div><strong>Hubungkan folder</strong><p>SnapPik membaca daftar foto Anda.</p></div></li><li><span>2</span><div><strong>Terbitkan galeri</strong><p>Periksa hasil impor, lalu buka akses.</p></div></li><li><span>3</span><div><strong>Bagikan tautan</strong><p>Klien memilih melalui browser.</p></div></li></ol>}</div>
    <div className="project-notices stack">
    {!canMutate && <Status tone="warning">Akses berakhir. Pengaturan hanya dapat dibaca; data tetap tersimpan.</Status>}
    {project?.archived_at && <Status tone="warning">Proyek diarsipkan. Semua tautan klien ditutup.</Status>}
    {project?.selectionLocked && <Status>Klien sudah mengonfirmasi pilihannya. Buka kembali pemilihan jika klien perlu mengganti foto atau Anda perlu mengganti folder sumber.</Status>}
    {commandError && <Status tone="error">{commandError}</Status>}
    </div>
    <aside className="project-sidebar stack" aria-label="Penerbitan dan berbagi">
    {project ? <section className="panel stack share-panel"><span className="eyebrow">Bagikan kepada klien</span><h2>Tautan galeri</h2><p>{shareable ? 'Satu tautan untuk galeri pemilihan dan hasil akhir yang sudah Anda terbitkan.' : 'Terbitkan setidaknya satu galeri di bawah sebelum membagikan tautan ini. Proyek yang diarsipkan perlu dipulihkan terlebih dahulu.'}</p>
      <div className="field"><label htmlFor="client-share-link">Tautan klien</label><input id="client-share-link" ref={shareInput} readOnly value={new URL(project.sharePath, window.location.origin).href} onFocus={e => e.target.select()} /></div>
      <div className="actions"><Button disabled={!shareable} onClick={() => void copyLink()}>Salin tautan klien</Button>{shareable && <a href={project.sharePath} target="_blank" rel="noopener noreferrer">Lihat galeri klien</a>}</div>
      {copyMessage && <Status>{copyMessage}</Status>}
    </section> : <details className="panel disclosure setup-help"><summary>Cara menyiapkan folder Google Drive</summary><div className="stack"><ol><li>Simpan foto JPEG/PNG di Google Drive.</li><li>Ubah akses folder menjadi “Siapa saja yang memiliki link”.</li><li>Salin tautan folder, lalu tempel di bagian Pemilihan foto.</li></ol><p className="muted">Belum punya hasil edit? Kosongkan folder Hasil akhir untuk sekarang.</p></div></details>}
    <div className="module-status-grid">{project?.modules.map(module => <section className="panel stack" key={module.type} aria-label={`Status ${titles[module.type]}`}>
      <h2 className="section-title">{module.type === 'selection' ? <Gallery size={25} aria-hidden="true" /> : <DocumentDownload size={25} aria-hidden="true" />}{titles[module.type]}</h2><Status tone={module.state === 'failed' ? 'error' : 'info'}>{states[module.state]} · {module.published ? 'Diterbitkan' : 'Draf'} · {module.photos} foto</Status>
      <p>{module.passwordRequired ? 'Dilindungi kata sandi' : 'Tanpa kata sandi'} · {module.deadline ? `Ditutup ${new Date(module.deadline).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA` : 'Tanpa batas waktu'}</p>
      <p className="muted">{module.published ? 'Galeri sudah diterbitkan. Akses klien mengikuti kata sandi dan batas waktu di atas.' : module.canPublish ? 'Foto sudah siap. Terbitkan galeri untuk membuka akses klien.' : module.state === 'failed' ? 'Daftar foto belum berhasil dibaca. Periksa folder, lalu tekan Segarkan untuk mencoba lagi.' : module.source ? 'Tunggu pembacaan foto selesai sebelum menerbitkan galeri.' : 'Tambahkan tautan folder dan simpan pengaturan untuk menyiapkan galeri ini.'}</p>
      {module.failure && <Status tone="error">{remedies[module.failure] ?? 'Periksa izin berbagi publik, akses subfolder dan layanan Drive, lalu ulangi impor.'}</Status>}
      {module.stale && <Status tone="warning">Pembaruan foto gagal. Klien masih melihat daftar foto terakhir yang berhasil dibaca. Periksa folder, lalu coba segarkan lagi.</Status>}
      {module.unsupported > 0 && <Status tone="warning">{module.unsupported} berkas tidak didukung atau pintasan dilaporkan, tidak dimasukkan sebagai foto.<ul>{module.unsupportedNames.map((name, i) => <li key={i}>{name}</li>)}</ul><p>Menampilkan maksimal 20 nama. <Link href={`/projects/${project.id}/diagnostics?module=${module.type}&kind=unsupported`}>Lihat semua berkas yang dikecualikan</Link></p></Status>}
      {module.missingChoices > 0 && <Status tone="warning">{module.missingChoices} pilihan tersimpan tidak tersedia dalam katalog terbaru. Hasil tetap disimpan.<ul>{module.missingNames.map((name, i) => <li key={i}>{name}</li>)}</ul><Link href={`/projects/${project.id}/diagnostics?module=selection&kind=missing`}>Lihat semua pilihan tidak tersedia</Link></Status>}
      <div className="actions"><Button variant="secondary" disabled={!editable || busy || command.processing || form.isDirty || !module.source} onClick={() => act('refresh', module.type)}>Segarkan {titles[module.type]}</Button>
        <Button disabled={!editable || command.processing || form.isDirty || (!module.published && !module.canPublish)} onClick={() => act(module.published ? 'unpublish' : 'publish', module.type)}>{module.published ? 'Tutup' : 'Terbitkan'} {titles[module.type]}</Button></div>
    </section>)}</div>
    {project && <details className="panel disclosure project-management"><summary><Link21 size={21} aria-hidden="true" />Arsip & penggantian tautan</summary><div className="stack">
      <p>Arsip menutup kedua galeri dan menyimpan pilihan klien. Mengganti tautan membuat tautan lama tidak berlaku.</p>
      <div className="actions"><Button variant="secondary" disabled={!canMutate || command.processing || form.isDirty} onClick={() => act(project.archived_at ? 'restore' : 'archive')}>{project.archived_at ? 'Pulihkan proyek' : 'Arsipkan proyek'}</Button>
        <Button variant="secondary" disabled={!canMutate || command.processing} onClick={() => setRotatePending(true)}>Ganti tautan klien</Button>
        </div>
      {rotatePending && <div className="stack"><Status tone="warning">Tautan lama akan langsung ditutup. Bagikan kembali tautan baru kepada klien.</Status><div className="actions"><Button disabled={command.processing} onClick={() => act('rotate')}>Konfirmasi ganti tautan</Button><Button variant="ghost" onClick={() => setRotatePending(false)}>Batal</Button></div></div>}
    </div></details>}
    {project && <Button variant="ghost" onClick={() => router.reload({ only: ['project', 'canMutate'] })}>Periksa status impor</Button>}
    </aside>
    <div className="project-main stack">
    {project && <section className="panel stack" aria-label="Pilihan tersimpan"><div className="section-heading"><div><span className="eyebrow">Pilihan klien</span><h2>{project.choiceCount} dari {project.allowance} foto</h2></div><Link href={`/projects/${project.id}/results`}>Lihat semua pilihan</Link></div><p>{project.selectionLocked ? 'Sudah dikonfirmasi. Daftar foto siap digunakan untuk proses edit.' : project.choiceCount ? 'Pilihan sudah tersimpan, tetapi klien belum mengonfirmasi. Daftar masih dapat berubah.' : selection?.published ? 'Klien belum menyimpan pilihan. Pastikan Anda sudah membagikan tautan galeri.' : 'Belum ada foto dipilih. Terbitkan galeri pemilihan dan bagikan tautan untuk memulai.'}</p></section>}
    {project?.selectionLocked && editable && <div className="panel stack"><Button variant="secondary" disabled={command.processing || form.isDirty || reopening} onClick={() => setReopenPending(true)}>Buka kembali pemilihan</Button>{reopenPending && <><p>Pilihan saat ini dan riwayat konfirmasi tetap disimpan. Klien dapat mengubah pilihan lagi jika batas waktu belum berakhir.</p><div className="actions"><Button pending={reopening} onClick={() => void reopen()}>Konfirmasi buka kembali</Button><Button variant="ghost" disabled={reopening} onClick={() => setReopenPending(false)}>Batal</Button></div></>}</div>}
    <form className="panel stack project-form" onSubmit={save}>
      <div><span className="eyebrow">Identitas & sumber foto</span><h2 className="section-title"><Setting2 size={25} aria-hidden="true" />Pengaturan proyek</h2></div>
      {form.isDirty && <Status>Perubahan belum disimpan.</Status>}
      {Object.keys(form.errors).length > 0 && <Status tone="error">Pengaturan belum disimpan. Periksa kolom yang ditandai di bawah.{Object.entries(form.errors).filter(([key]) => !['name', 'contact', 'allowance', 'selection_source', 'delivery_source', 'selection_password', 'delivery_password', 'selection_deadline', 'delivery_deadline'].includes(key)).map(([key, message]) => <p key={key}>{message}</p>)}</Status>}
      <fieldset className="stack" disabled={!editable || form.processing}>
        <div className="project-basics"><Field label="Nama proyek / klien" placeholder="Contoh: Wisuda Nadia" description="Gunakan nama yang mudah Anda kenali di daftar proyek." required value={form.data.name} onChange={e => form.setData('name', e.target.value)} error={form.errors.name} />
        <Field label="Batas pilihan klien" type="number" min={1} required description="Contoh: isi 20 agar klien boleh memilih 1–20 foto untuk diedit. Ini bukan batas jumlah foto dalam galeri." value={form.data.allowance} onChange={e => form.setData('allowance', Number(e.target.value))} error={form.errors.allowance} /></div>
        <div className="module-settings-grid">{(['selection', 'delivery'] as const).map(type => <details className="module-source disclosure" key={type} open={type === 'selection' || !!delivery?.source || Object.keys(form.errors).some(key => key.startsWith('delivery_')) ? true : undefined}><summary>{type === 'selection' ? 'Folder untuk pemilihan foto' : 'Tambahkan galeri hasil akhir (opsional)'}</summary><section className="stack module-settings" aria-labelledby={`${type}-title`}>
          <h3 className="section-title" id={`${type}-title`}>{type === 'selection' ? <Gallery size={24} aria-hidden="true" /> : <DocumentDownload size={24} aria-hidden="true" />}{titles[type]}</h3>
          <p>{type === 'selection' ? 'Foto yang akan dipilih klien untuk diedit.' : 'Foto yang sudah selesai diedit dan siap diunduh. Anda boleh menambahkannya nanti.'}</p>
          <Field label={`Folder Google Drive · ${titles[type]}`} type="url" required={type === 'selection'} readOnly={type === 'selection' && project?.selectionLocked} value={form.data[`${type}_source`]} onChange={e => form.setData(`${type}_source`, e.target.value)} error={form.errors[`${type}_source`]} description={type === 'delivery' ? 'Opsional. Pilih folder terpisah dari galeri pemilihan, tanpa foto atau subfolder yang sama.' : 'Tempel tautan folder publik. SnapPik membaca foto JPEG/PNG, termasuk yang ada di subfolder.'} />
          <details className="disclosure" open={form.errors[`${type}_password`] || form.errors[`${type}_deadline`] ? true : undefined}><summary>Kata sandi & batas waktu · {titles[type]}</summary><div className="stack">
          <p className="muted">{(type === 'selection' ? selection : delivery)?.passwordRequired ? 'Galeri ini sudah dilindungi kata sandi.' : 'Galeri ini belum memakai kata sandi.'} Kata sandi dan batas waktu hanya berlaku untuk galeri ini.</p>
          <Field label={`Kata sandi baru · ${titles[type]}`} type="password" autoComplete="new-password" value={form.data[`${type}_password`]} onChange={e => form.setData(`${type}_password`, e.target.value)} description={project ? 'Kosongkan jika tidak ingin mengubah kata sandi.' : 'Opsional. Jika diisi, bagikan kata sandi ini kepada klien.'} error={form.errors[`${type}_password`]} />
          {project && <label className="choice"><input type="checkbox" checked={form.data[`${type}_remove_password`]} onChange={e => form.setData(`${type}_remove_password`, e.target.checked)} />Hapus kata sandi · {titles[type]}</label>}
          <Field label={`Batas waktu · ${titles[type]} (WITA, UTC+08:00)`} type="datetime-local" value={form.data[`${type}_deadline`]} onChange={e => form.setData(`${type}_deadline`, e.target.value)} error={form.errors[`${type}_deadline`]} description="Opsional. Akses galeri ditutup pada waktu ini. Kosongkan agar galeri tidak memiliki batas waktu." />
          </div></details>
        </section></details>)}</div>
        <details className="disclosure" open={form.errors.contact ? true : undefined}><summary>Kontak klien (opsional)</summary><div className="stack"><Field label="Kontak WhatsApp (opsional)" description="Disimpan sebagai informasi kontak. SnapPik tidak mengirim pesan otomatis." value={form.data.contact} onChange={e => form.setData('contact', e.target.value)} error={form.errors.contact} /></div></details>
        <div className="form-footer stack"><Button type="submit" pending={form.processing}>{project ? 'Simpan pengaturan' : 'Buat proyek dan impor'}</Button><p className="muted">{project ? 'Perubahan folder memulai pembacaan ulang foto. Periksa status galeri setelah menyimpan.' : 'Proyek disimpan sebagai draf. Setelah daftar foto selesai dibaca, terbitkan galeri agar klien dapat membukanya.'}</p></div>
      </fieldset>
    </form>
    <details className="disclosure source-disclosure"><summary>Tentang akses foto di Google Drive</summary><p>Foto asli tetap di Drive Anda. Bagikan folder sebagai “Siapa saja yang memiliki link”. Kata sandi SnapPik melindungi halaman galeri; orang yang memiliki tautan folder Drive tetap dapat membuka sumbernya langsung.</p></details>
    </div>

  </div></Shell>;
}
