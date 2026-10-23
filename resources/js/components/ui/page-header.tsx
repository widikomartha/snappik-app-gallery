import type { ReactNode } from 'react';
import { Illustration, type IllustrationScene } from './illustration';

export function PageHeader({ title, description, scene = 'projects', actions }: { title: ReactNode; description?: string; scene?: IllustrationScene; actions?: ReactNode }) {
  return <header className="page-heading">
    <div className="page-heading-copy"><h1>{title}</h1>{description && <p>{description}</p>}{actions && <div className="actions">{actions}</div>}</div>
    <div className="page-heading-art"><Illustration scene={scene} /></div>
  </header>;
}
