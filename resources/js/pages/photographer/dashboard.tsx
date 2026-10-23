import { PageHeader } from '../../components/ui/page-header';
import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { ProgressOverview } from '../../components/project-progress';
import type { Overview } from '../../lib/progress';

export default function Dashboard(props: Overview) {
  return <Shell surface="photographer"><Head title="Ruang fotografer" /><div className="stack"><PageHeader title="Ruang fotografer" scene="projects" description="Lihat siapa yang sedang memilih dan siapa yang sudah mengonfirmasi. Buka hasil pilihan untuk mulai mengedit." /><ProgressOverview initial={props} dashboard /></div></Shell>;
}
