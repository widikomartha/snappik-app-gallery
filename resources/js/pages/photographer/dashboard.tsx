import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

export default function Dashboard() {
  return <Shell surface="photographer"><Head title="Ruang fotografer" /><h1>Ruang fotografer</h1><Status>Pengelolaan proyek sedang disiapkan.</Status></Shell>;
}
