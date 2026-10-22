import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { Shell } from '../../layouts/shell';
import { Field } from '../../components/ui/field';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';

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
  const busy = project?.modules.some(m => ['queued', 'importing'].includes(m.state));
  const { start, stop } = usePoll(3000, { only: ['project', 'canMutate'], onSuccess: () => { /* Canonical job state only; form values stay mounted. */ } }, { autoStart: false });
  useEffect(() => { if (busy) start(); else stop(); }, [busy, start, stop]);
  const editable = canMutate && !project?.archived_at;
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
  return <Shell surface="photographer"><Head title={project?.name ?? 'Buat proyek'} /><div className="stack">
    <Link href="/projects">Semua proyek</Link><h1>{project?.name ?? 'Buat proyek'}</h1>
    {project && <Link href={`/projects/${project.id}/results`}>Lihat progres, riwayat dan salin nama berkas</Link>}
    {!canMutate && <Status tone="warning">Akses berakhir. Pengaturan hanya dapat dibaca; data tetap tersimpan.</Status>}
    {project?.archived_at && <Status tone="warning">Proyek diarsipkan. Semua tautan klien ditutup.</Status>}
    {project?.selectionLocked && <Status>Pemilihan telah dikonfirmasi. Sumber pemilihan terkunci hingga konfirmasi dibuka kembali.</Status>}
    {project && <section className="panel stack" aria-label="Pilihan tersimpan"><h2>Pilihan tersimpan · {project.choiceCount} / {project.allowance}</h2><p>{project.selectionLocked ? 'Konfirmasi saat ini terkunci.' : 'Pilihan bersama masih berupa draf.'} Menampilkan hingga 20 pilihan pertama dalam urutan tersimpan.</p><ul>{project.choicePreview.map(choice => <li key={choice.file_id}>{choice.path} · {choice.file_id}</li>)}</ul></section>}
    {project?.selectionLocked && editable && <div className="panel stack"><Button variant="secondary" disabled={command.processing || form.isDirty || reopening} onClick={() => setReopenPending(true)}>Buka kembali pemilihan</Button>{reopenPending && <><p>Pilihan saat ini dan riwayat konfirmasi tetap disimpan. Klien dapat mengubah pilihan lagi jika batas waktu belum berakhir.</p><div className="actions"><Button pending={reopening} onClick={() => void reopen()}>Konfirmasi buka kembali</Button><Button variant="ghost" disabled={reopening} onClick={() => setReopenPending(false)}>Batal</Button></div></>}</div>}
    <form className="panel stack" onSubmit={save}>
      <h2>Pengaturan proyek</h2>
      {form.isDirty && <Status>Perubahan belum disimpan.</Status>}
      {Object.entries(form.errors).map(([key, message]) => <Status key={key} tone="error">{message}</Status>)}
      <fieldset className="stack" disabled={!editable || form.processing}>
        <Field label="Nama proyek / klien" required value={form.data.name} onChange={e => form.setData('name', e.target.value)} error={form.errors.name} />
        <Field label="Kontak WhatsApp (opsional)" value={form.data.contact} onChange={e => form.setData('contact', e.target.value)} error={form.errors.contact} />
        <Field label="Batas pilihan klien" type="number" min={1} required description="Jumlah maksimal pilihan klien. Jumlah foto di galeri tidak dibatasi paket Pro." value={form.data.allowance} onChange={e => form.setData('allowance', Number(e.target.value))} error={form.errors.allowance} />
        <p className="muted">Foto tetap berada di Google Drive Anda. Folder harus dibagikan publik. Siapa pun yang memiliki tautan sumber Drive masih dapat mengaksesnya, terlepas dari kata sandi SnapPik.</p>
        {(['selection', 'delivery'] as const).map(type => <section className="stack" key={type} aria-labelledby={`${type}-title`}>
          <h3 id={`${type}-title`}>{titles[type]}</h3>
          <Field label={`Folder Google Drive · ${titles[type]}`} type="url" required={type === 'selection'} readOnly={type === 'selection' && project?.selectionLocked} value={form.data[`${type}_source`]} onChange={e => form.setData(`${type}_source`, e.target.value)} error={form.errors[`${type}_source`]} description={type === 'delivery' ? 'Opsional. Gunakan folder terpisah tanpa foto atau subfolder yang sama.' : 'JPEG/PNG dalam folder dan seluruh subfolder akan diimpor.'} />
          <Field label={`Kata sandi baru · ${titles[type]}`} type="password" autoComplete="new-password" value={form.data[`${type}_password`]} onChange={e => form.setData(`${type}_password`, e.target.value)} description="Kosongkan untuk mempertahankan kata sandi saat ini." error={form.errors[`${type}_password`]} />
          <label className="choice"><input type="checkbox" checked={form.data[`${type}_remove_password`]} onChange={e => form.setData(`${type}_remove_password`, e.target.checked)} />Hapus kata sandi · {titles[type]}</label>
          <Field label={`Batas waktu · ${titles[type]} (WITA, UTC+08:00)`} type="datetime-local" value={form.data[`${type}_deadline`]} onChange={e => form.setData(`${type}_deadline`, e.target.value)} error={form.errors[`${type}_deadline`]} description="Opsional. Kosong berarti tanpa batas waktu modul." />
        </section>)}
        <Button type="submit" pending={form.processing}>{project ? 'Simpan pengaturan' : 'Buat proyek dan impor'}</Button>
      </fieldset>
    </form>
    {project?.modules.map(module => <section className="panel stack" key={module.type} aria-label={`Status ${titles[module.type]}`}>
      <h2>{titles[module.type]}</h2><Status tone={module.state === 'failed' ? 'error' : 'info'}>{states[module.state]} · {module.published ? 'Diterbitkan' : 'Draf'} · {module.photos} foto</Status>
      <p>{module.passwordRequired ? 'Dilindungi kata sandi' : 'Tanpa kata sandi'} · {module.deadline ? `Ditutup ${new Date(module.deadline).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA` : 'Tanpa batas waktu'}</p>
      {module.failure && <Status tone="error">{remedies[module.failure] ?? 'Periksa izin berbagi publik, akses subfolder dan layanan Drive, lalu ulangi impor.'}</Status>}
      {module.stale && <Status tone="warning">Katalog sebelumnya tetap tersedia. Penyegaran gagal; metadata mungkin tidak mutakhir.</Status>}
      {module.unsupported > 0 && <Status tone="warning">{module.unsupported} berkas tidak didukung atau pintasan dilaporkan, tidak dimasukkan sebagai foto.<ul>{module.unsupportedNames.map((name, i) => <li key={i}>{name}</li>)}</ul><p>Menampilkan maksimal 20 nama. <Link href={`/projects/${project.id}/diagnostics?module=${module.type}&kind=unsupported`}>Lihat semua berkas yang dikecualikan</Link></p></Status>}
      {module.missingChoices > 0 && <Status tone="warning">{module.missingChoices} pilihan tersimpan tidak tersedia dalam katalog terbaru. Hasil tetap disimpan.<ul>{module.missingNames.map((name, i) => <li key={i}>{name}</li>)}</ul><Link href={`/projects/${project.id}/diagnostics?module=selection&kind=missing`}>Lihat semua pilihan tidak tersedia</Link></Status>}
      <div className="actions"><Button variant="secondary" disabled={!editable || busy || command.processing || form.isDirty || !module.source} onClick={() => act('refresh', module.type)}>Segarkan {titles[module.type]}</Button>
        <Button disabled={!editable || command.processing || form.isDirty || (!module.published && !module.canPublish)} onClick={() => act(module.published ? 'unpublish' : 'publish', module.type)}>{module.published ? 'Tutup' : 'Terbitkan'} {titles[module.type]}</Button></div>
    </section>)}
    {commandError && <Status tone="error">{commandError}</Status>}
    {project && <section className="panel stack"><h2>Tautan dan arsip</h2>
      <p className="muted">Tautan tersedia setelah modul diterbitkan. Mengganti tautan mencabut akses melalui tautan lama.</p>
      <Field label="Tautan klien" readOnly value={new URL(project.sharePath, window.location.origin).href} onFocus={e => e.target.select()} />
      <div className="actions"><Button variant="secondary" disabled={!canMutate || command.processing || form.isDirty} onClick={() => act(project.archived_at ? 'restore' : 'archive')}>{project.archived_at ? 'Pulihkan proyek' : 'Arsipkan proyek'}</Button>
        <Button variant="secondary" disabled={!canMutate || command.processing} onClick={() => setRotatePending(true)}>Ganti tautan klien</Button>
        <Button variant="ghost" onClick={() => router.reload({ only: ['project', 'canMutate'] })}>Periksa status impor</Button></div>
      {rotatePending && <div className="stack"><Status tone="warning">Tautan lama akan langsung ditutup. Bagikan kembali tautan baru kepada klien.</Status><div className="actions"><Button disabled={command.processing} onClick={() => act('rotate')}>Konfirmasi ganti tautan</Button><Button variant="ghost" onClick={() => setRotatePending(false)}>Batal</Button></div></div>}
    </section>}
  </div></Shell>;
}
