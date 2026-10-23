import { Link, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppearanceControl } from '../components/ui/appearance-control';
import { Button } from '../components/ui/button';
import { motion, useReducedMotion } from 'motion/react';
import { timing } from '../lib/motion';

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
  const reduced = useReducedMotion();
  return (
    <div className={`shell shell--${surface}`} data-surface={surface}>
      <a className="skip-link" href="#main">Lewati ke konten</a>
      <header className="site-header">
        <Link className="brand" href="/" aria-label="SnapPik beranda">SnapPik<span aria-hidden="true">.</span></Link>
        <AppearanceControl />
        {auth?.user && <form onSubmit={e => { e.preventDefault(); logout.post('/logout'); }}><Button type="submit" variant="ghost" pending={logout.processing}>Keluar</Button></form>}
        {surface === 'public' && <nav className="actions shell-navigation" aria-label="Navigasi utama"><Link href="/login">Masuk akun</Link><Link href="/register">Daftar fotografer</Link></nav>}
        {surface === 'photographer' && <nav className="actions shell-navigation" aria-label="Navigasi fotografer"><Link href="/dashboard">Beranda fotografer</Link><Link href="/projects">Daftar proyek</Link><Link href="/billing">Tagihan</Link></nav>}
        {surface === 'admin' && <nav className="actions shell-navigation" aria-label="Navigasi pengelola"><Link href="/admin">Beranda admin</Link><Link href="/admin/vouchers">Voucher</Link></nav>}
      </header>
      <motion.main id="main" tabIndex={-1} initial={reduced ? false : { opacity: 0.96 }} animate={{ opacity: 1 }} transition={{ duration: reduced ? 0 : timing.state }}>
        <p className="eyebrow">{labels[surface]}</p>
        {children}
      </motion.main>
      <footer className="site-footer">SnapPik <span aria-hidden="true">/</span> Pilih. Sampaikan. Selesai.</footer>
    </div>
  );
}
