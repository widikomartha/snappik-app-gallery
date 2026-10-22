import type { ReactNode } from 'react';

export function Status({ tone = 'info', children }: { tone?: 'info' | 'success' | 'warning' | 'error'; children: ReactNode }) {
  return <div className={`status status--${tone}`} role={tone === 'error' ? 'alert' : 'status'}>{children}</div>;
}
