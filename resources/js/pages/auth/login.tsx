import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function Login() {
  return <Shell surface="auth"><Head title="Akun" /><h1>Akun SnapPik</h1><Status>Masuk dan pendaftaran belum tersedia. Layanan sedang disiapkan.</Status></Shell>;
}
