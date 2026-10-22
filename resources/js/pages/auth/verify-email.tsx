import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';
import { Button } from '../../components/ui/button';
import { useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
  const form = useForm({});
  return <Shell surface="auth"><Head title="Verifikasi email" /><div className="auth-content stack"><h1>Verifikasi email Anda</h1><p>Buka tautan di email untuk mengaktifkan akun. Uji coba dimulai satu kali setelah verifikasi berhasil.</p>{status && <Status>{status}</Status>}<form onSubmit={e => { e.preventDefault(); form.post('/email/verification-notification'); }}><Button type="submit" pending={form.processing}>Kirim ulang email verifikasi</Button></form></div></Shell>;
}
