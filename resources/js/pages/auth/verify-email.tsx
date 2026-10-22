import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function VerifyEmail() {
  return <Shell surface="auth"><Head title="Verifikasi email" /><h1>Verifikasi email Anda</h1><Status>Verifikasi email belum tersedia. Layanan sedang disiapkan.</Status></Shell>;
}
