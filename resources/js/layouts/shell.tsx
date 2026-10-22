import { Link, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppearanceControl } from '../components/ui/appearance-control';
import { Button } from '../components/ui/button';

export type Surface = 'public' | 'auth' | 'photographer' | 'admin' | 'client';
const labels: Record<Surface, string> = {
  public: 'Galeri foto, lebih sederhana.',
  auth: 'Akun SnapPik',
  photographer: 'Ruang fotografer',
  admin: 'Administrasi platform',
  client: 'Galeri klien',
};

export function Shell({ surface, children }: { surface: Surface; children: ReactNode }) {
  const { auth } = usePage<{ auth?: { user: { name: string } | null } }>().props;
  const logout = useForm({});
  return (
    <div className={`shell shell--${surface}`} data-surface={surface}>
      <a className="skip-link" href="#main">Lewati ke konten</a>
      <header className="site-header">
        <Link className="brand" href="/" aria-label="SnapPik beranda">SnapPik<span aria-hidden="true">.</span></Link>
        <AppearanceControl />
        {auth?.user && <form onSubmit={e => { e.preventDefault(); logout.post('/logout'); }}><Button type="submit" variant="ghost" pending={logout.processing}>Keluar</Button></form>}
      </header>
      <main id="main" tabIndex={-1}>
        <p className="eyebrow">{labels[surface]}</p>
        {children}
      </main>
      <footer className="site-footer">SnapPik <span aria-hidden="true">/</span> Pilih. Sampaikan. Selesai.</footer>
    </div>
  );
}
