import { Head, Link, useForm } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Field } from '../../components/ui/field';
import { Status } from '../../components/ui/status';

function Module({ module }: { module: { type: string; locked: boolean; photos: number | null } }) {
  const form = useForm({ gallery_password: '' });
  const title = module.type === 'selection' ? 'Pemilihan foto' : 'Hasil akhir';
  return <section className="panel stack"><h2>{title}</h2>{module.locked ? <form className="stack" onSubmit={e => { e.preventDefault(); form.post(`${window.location.pathname}/${module.type}/password`, { preserveScroll: true, onFinish: () => form.reset() }); }}>
    <Field label={`Kata sandi · ${title}`} type="password" autoComplete="current-password" required value={form.data.gallery_password} onChange={e => form.setData('gallery_password', e.target.value)} error={form.errors.gallery_password} /><Button type="submit" pending={form.processing}>Buka {title}</Button>
  </form> : <><Status>Galeri diterbitkan · {module.photos} foto.</Status>{module.type === 'selection' && <Link href={`${window.location.pathname}/selection`}>Buka pemilihan foto</Link>}{module.type === 'delivery' && <p>Tampilan hasil akhir akan tersedia pada tahap pengiriman.</p>}</>}</section>;
}
export default function ClientProject({ modules }: { modules: { type: string; locked: boolean; photos: number | null }[] }) {
  return <Shell surface="client"><Head title="Galeri klien"><meta name="robots" content="noindex,nofollow,noarchive" /></Head><div className="stack"><h1>Galeri klien</h1>{modules.map(module => <Module key={module.type} module={module} />)}</div></Shell>;
}
