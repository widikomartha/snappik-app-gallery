import type { ReactNode } from 'react';
import { Illustration, type IllustrationScene } from './illustration';

export function EmptyState({ title, description, scene = 'projects', children }: { title: string; description?: string; scene?: IllustrationScene; children?: ReactNode }) {
  return <div className="empty-state"><Illustration scene={scene} /><div><h3>{title}</h3>{description && <p className="muted">{description}</p>}{children}</div></div>;
}
