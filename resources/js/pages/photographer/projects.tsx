import { PageHeader } from '../../components/ui/page-header';
import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { ProgressOverview } from '../../components/project-progress';
import type { Overview } from '../../lib/progress';

export default function Projects(props: Overview) {
  return <Shell surface="photographer"><Head title="Proyek" /><div className="stack">
    <PageHeader title="Proyek Anda" scene="projects" description="Setiap proyek berisi galeri pemilihan dan galeri hasil akhir untuk satu klien. Mulai dari folder foto di Google Drive Anda." />
    <ProgressOverview initial={props} />
  </div></Shell>;
}
