import { Head, Link, useForm } from '@inertiajs/react';
import { Shell } from '../layouts/shell';
import { Field } from './ui/field';
import { Button } from './ui/button';
import { Status } from './ui/status';
import type { FormEvent } from 'react';

type Mode = 'login' | 'register' | 'forgot' | 'reset';
const content = {
  login: { title: 'Masuk ke SnapPik', action: 'Masuk', url: '/login' },
  register: { title: 'Buat akun fotografer', action: 'Daftar', url: '/register' },
  forgot: { title: 'Pulihkan kata sandi', action: 'Kirim tautan pemulihan', url: '/forgot-password' },
  reset: { title: 'Buat kata sandi baru', action: 'Simpan kata sandi', url: '/reset-password' },
};

export function AuthForm({ mode, status, token = '', email = '' }: { mode: Mode; status?: string; token?: string; email?: string }) {
  const form = useForm({ name: '', email, password: '', password_confirmation: '', token });
  const page = content[mode];
  const hasPassword = mode !== 'forgot';
  const newPassword = mode === 'register' || mode === 'reset';
  function submit(event: FormEvent) {
    event.preventDefault();
    form.post(page.url, { onFinish: () => form.reset('password', 'password_confirmation') });
  }
  return <Shell surface="auth">
    <Head title={page.title} />
    <div className="auth-content stack">
      <h1>{page.title}</h1>
      {status && <Status>{status}</Status>}
      {mode === 'register' && <p className="muted">Uji coba 7 hari dimulai setelah verifikasi email. Maksimal 3 proyek aktif selama uji coba.</p>}
      <form className="panel stack" onSubmit={submit}>
        {mode === 'register' && <Field label="Nama" autoComplete="name" name="name" required maxLength={255} value={form.data.name} onChange={e => form.setData('name', e.target.value)} error={form.errors.name} />}
        <Field label="Email" name="email" type="email" autoComplete="email" required maxLength={255} value={form.data.email} onChange={e => form.setData('email', e.target.value)} error={form.errors.email} />
        {hasPassword && <Field label="Kata sandi" name="password" type="password" autoComplete={newPassword ? 'new-password' : 'current-password'} required minLength={newPassword ? 12 : undefined} maxLength={72} description={newPassword ? 'Minimal 12 karakter, dengan huruf dan angka.' : undefined} value={form.data.password} onChange={e => form.setData('password', e.target.value)} error={form.errors.password} />}
        {newPassword && <Field label="Ulangi kata sandi" name="password_confirmation" type="password" autoComplete="new-password" required value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} error={form.errors.password_confirmation} />}
        {form.errors.token && <Status tone="error">{form.errors.token}</Status>}
        <Button type="submit" pending={form.processing}>{form.processing ? 'Memproses…' : page.action}</Button>
      </form>
      <nav className="actions" aria-label="Navigasi akun">
        {mode !== 'login' && <Link href="/login">Masuk</Link>}
        {mode === 'login' && <><Link href="/register">Buat akun</Link><Link href="/forgot-password">Lupa kata sandi?</Link></>}
      </nav>
    </div>
  </Shell>;
}
