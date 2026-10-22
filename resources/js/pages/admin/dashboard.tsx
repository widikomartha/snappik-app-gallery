import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function Dashboard() {
  return <Shell surface="admin"><Head title="Administrasi" /><div className="stack"><h1>Administrasi platform</h1><Link className="button button--primary" href="/admin/vouchers">Kelola voucher</Link><Status>Layar operasional lainnya sedang disiapkan.</Status></div></Shell>;
}
