import { Link, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppearanceControl } from '../components/ui/appearance-control';
import { Button } from '../components/ui/button';
import { motion, useReducedMotion } from 'motion/react';
import { timing } from '../lib/motion';
import { Card, FolderOpen, Home2, LogoutCurve, Profile2User, Receipt1, Setting2, Tag } from 'iconsax-reactjs';
import { Illustration } from '../components/ui/illustration';

export type Surface = 'public' | 'auth' | 'photographer' | 'admin' | 'client';
const labels: Record<Surface, string> = {
  public: 'Galeri foto, lebih sederhana.',
  auth: 'Akun SnapPik',
  photographer: 'Ruang fotografer',
  admin: 'Administrasi platform',
  client: 'Galeri klien',
};

export function Shell({ surface, children, presentation }: { surface: Surface; children: ReactNode; presentation?: 'landing' }) {
  const page = usePage<{ auth?: { user: { name: string } | null }; publicDocuments?: Partial<Record<'terms' | 'privacy' | 'support', string>> }>();
  const { auth, publicDocuments } = page.props;
  const logout = useForm({});
  const reduced = useReducedMotion();
  const landing = surface === 'public' && presentation === 'landing';
  const workspace = surface === 'photographer' || surface === 'admin';
  const pathname = page.url.split('?')[0];
  const photographerNavigation = [
    { href: '/dashboard', label: 'Beranda fotografer', text: 'Beranda', icon: Home2 },
    { href: '/projects', label: 'Daftar proyek', text: 'Proyek', icon: FolderOpen },
    { href: '/billing', label: 'Tagihan', text: 'Tagihan', icon: Card },
  ];
  const adminNavigation = [
    { href: '/admin', label: 'Ringkasan', text: 'Ringkasan', icon: Home2 },
    { href: '/admin/accounts', label: 'Akun fotografer', text: 'Akun fotografer', icon: Profile2User },
    { href: '/admin/plan', label: 'Harga Pro', text: 'Harga Pro', icon: Tag },
    { href: '/admin/orders', label: 'Transaksi', text: 'Transaksi', icon: Receipt1 },
    { href: '/admin/vouchers', label: 'Kelola voucher', text: 'Voucher', icon: Card },
    { href: '/admin/jobs', label: 'Pekerjaan', text: 'Pekerjaan', icon: Setting2 },
  ];
  return (
    <div className={`shell shell--${surface}${landing ? ' shell--landing' : ''}`} data-surface={surface}>
      <a className="skip-link" href="#main">Lewati ke konten</a>
      <header className="site-header">
        <Link className="brand" href="/" aria-label="SnapPik beranda">SnapPik<span aria-hidden="true">.</span></Link>
        {landing && <nav className="landing-navigation" aria-label="Bagian landing"><a href="#landing-cara">Cara kerja</a><a href="#landing-fitur">Fitur</a><a href="#landing-harga">Harga</a><a href="#landing-faq">FAQ</a></nav>}
        {workspace && <span className="workspace-label">{labels[surface]}</span>}
        {!landing && !workspace && surface !== 'public' && <span className="workspace-label">{labels[surface]}</span>}
        <div className="header-controls"><AppearanceControl compact />
        {auth?.user && <><span className="account-name">{auth.user.name}</span><form onSubmit={e => { e.preventDefault(); logout.post('/logout'); }}><Button type="submit" variant="ghost" className="button--icon" title="Keluar" aria-label="Keluar" pending={logout.processing}><LogoutCurve size={21} aria-hidden="true" focusable="false" /></Button></form></>}
        </div>
        {surface === 'public' && <nav className="actions shell-navigation" aria-label="Navigasi utama">{landing ? auth?.user ? <Link href="/dashboard">Ruang fotografer</Link> : <Link href="/login" aria-label="Masuk akun">Masuk</Link> : <><Link href="/login">Masuk akun</Link><Link href="/register">Daftar fotografer</Link></>}</nav>}
      </header>
      <div className={workspace ? 'workspace-layout' : undefined}>
      {workspace && <aside className="workspace-sidebar"><p className="nav-caption">{surface === 'admin' ? 'Pengelolaan' : 'Ruang kerja'}</p><nav aria-label={surface === 'admin' ? 'Administrasi' : 'Navigasi fotografer'}>
        {(surface === 'admin' ? adminNavigation : photographerNavigation).map(({ href, label, text, icon: Icon }) => {
          const current = href === '/admin' ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
          return <Link href={href} key={href} aria-label={label} aria-current={current ? 'page' : undefined}><Icon size={22} aria-hidden="true" focusable="false" /><span>{text}</span></Link>;
        })}
      </nav></aside>}
      <motion.main id="main" tabIndex={-1} initial={reduced ? false : { opacity: 0.96 }} animate={{ opacity: 1 }} transition={{ duration: reduced ? 0 : timing.state }}>
        {surface === 'auth' ? <div className="auth-layout"><aside className="auth-story"><span className="eyebrow">Dari pilihan ke hasil akhir</span><Illustration scene="account" /><h2>Pilihan rapi.<br />Hasil tersampaikan.</h2><p>Ruang untuk foto Anda.<br />Kemudahan untuk klien Anda.</p></aside><div className="auth-form-area">{children}</div></div> : children}
      </motion.main>
      </div>
      <footer className="site-footer">SnapPik <span aria-hidden="true">/</span> Pilih. Sampaikan. Selesai.
        {publicDocuments && Object.keys(publicDocuments).length > 0 && <nav className="actions" aria-label="Informasi dan bantuan">
          {publicDocuments.terms && <a href={publicDocuments.terms}>Ketentuan layanan</a>}
          {publicDocuments.privacy && <a href={publicDocuments.privacy}>Privasi</a>}
          {publicDocuments.support && <a href={publicDocuments.support}>Bantuan</a>}
        </nav>}
      </footer>
    </div>
  );
}
