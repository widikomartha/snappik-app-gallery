import { Gallery, DocumentDownload } from 'iconsax-reactjs';
import { PageHeader } from '../../components/ui/page-header';
import { Head, Link, useForm } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Field } from '../../components/ui/field';

function Module({ module }: { module: { type: string; locked: boolean; photos: number | null } }) {
  const form = useForm({ gallery_password: '' });
  const title = module.type === 'selection' ? 'Pemilihan foto' : 'Hasil akhir';
  return <section className="panel stack client-module"><div className="module-symbol" aria-hidden="true">{module.type === 'selection' ? <Gallery size={30} /> : <DocumentDownload size={30} />}</div><div className="stack"><h2>{title}</h2><p className="muted">{module.type === 'selection' ? 'Pilih foto yang ingin Anda minta untuk diedit. Tinjau pilihan Anda sebelum mengirim konfirmasi kepada fotografer.' : 'Lihat foto yang sudah selesai diedit. Unduh satu per satu atau semua foto dalam satu file ZIP.'}</p></div>{module.locked ? <form className="stack" onSubmit={e => { e.preventDefault(); form.post(`${window.location.pathname}/${module.type}/password`, { preserveScroll: true, onFinish: () => form.reset() }); }}>
    <p>Galeri ini dilindungi kata sandi. Masukkan kata sandi dari fotografer untuk membukanya.</p>
    <Field label={`Kata sandi · ${title}`} type="password" autoComplete="current-password" required value={form.data.gallery_password} onChange={e => form.setData('gallery_password', e.target.value)} error={form.errors.gallery_password} /><Button type="submit" pending={form.processing}>Buka {title}</Button>
  </form> : <><p className="module-availability">{module.photos} foto tersedia</p>{module.type === 'selection' && <Link className="button button--primary" href={`${window.location.pathname}/selection`}>Buka pemilihan foto</Link>}{module.type === 'delivery' && <Link className="button button--primary" href={`${window.location.pathname}/delivery`}>Buka hasil akhir</Link>}</>}</section>;
}
export default function ClientProject({ modules }: { modules: { type: string; locked: boolean; photos: number | null }[] }) {
  return <Shell surface="client"><Head title="Galeri klien"><meta name="robots" content="noindex,nofollow,noarchive" /></Head><div className="stack"><PageHeader title="Foto Anda, dari pilihan hingga hasil akhir." scene="projects" description="Buka galeri pemilihan untuk memilih foto. Jika fotografer sudah membagikan hasil edit, Anda dapat mengunduhnya dari galeri hasil akhir." /><div className="client-module-grid">{modules.map(module => <Module key={module.type} module={module} />)}</div></div></Shell>;
}
