import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function Dashboard() {
  return <Shell surface="admin"><Head title="Administrasi" /><h1>Administrasi platform</h1><Status>Operasional platform sedang disiapkan.</Status></Shell>;
}
