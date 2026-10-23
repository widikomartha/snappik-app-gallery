import type { ReactNode } from 'react';

export function Status({ tone = 'info', children }: { tone?: 'info' | 'success' | 'warning' | 'error'; children: ReactNode }) {
  const symbols = { info: 'ⓘ', success: '✓', warning: '⚠', error: '!' };
  return <div className={`status status--${tone}`} role={tone === 'error' ? 'alert' : 'status'}><span className="status-icon" aria-hidden="true">{symbols[tone]}</span><div className="status-content">{children}</div></div>;
}
