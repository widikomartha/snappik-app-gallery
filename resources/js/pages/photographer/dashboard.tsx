import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function Dashboard({ accountState }: { accountState: string }) {
  const message: Record<string, string> = {
    trial: 'Uji coba aktif. Pengelolaan proyek sedang disiapkan.',
    pro: 'Pro aktif. Pengelolaan proyek sedang disiapkan.',
    expired: 'Akses berakhir. Metadata dan hasil tetap tersimpan dan dapat dibaca. Perubahan proyek dinonaktifkan. Perpanjangan sedang disiapkan.',
    suspended: 'Akun ditangguhkan. Galeri publik, perubahan proyek, dan pembayaran baru dinonaktifkan. Riwayat pembayaran tetap dapat dibaca saat fitur tersedia. Hubungi pengelola SnapPik.',
  };
  return <Shell surface="photographer"><Head title="Ruang fotografer" /><h1>Ruang fotografer</h1><Status tone={['expired', 'suspended'].includes(accountState) ? 'warning' : 'info'}>{message[accountState] ?? 'Status akun tidak tersedia.'}</Status></Shell>;
}
