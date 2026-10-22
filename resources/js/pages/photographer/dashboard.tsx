import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { ProgressOverview } from '../../components/project-progress';
import type { Overview } from '../../lib/progress';

export default function Dashboard(props: Overview) {
  return <Shell surface="photographer"><Head title="Ruang fotografer" /><div className="stack"><h1>Ruang fotografer</h1><Link href="/billing">Langganan dan tagihan</Link><ProgressOverview initial={props} dashboard /></div></Shell>;
}
