import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { ProgressOverview } from '../../components/project-progress';
import type { Overview } from '../../lib/progress';

export default function Projects(props: Overview) {
  return <Shell surface="photographer"><Head title="Proyek" /><div className="stack">
    <h1>Proyek Anda</h1>
    <ProgressOverview initial={props} />
  </div></Shell>;
}
