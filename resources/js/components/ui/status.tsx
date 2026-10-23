import type { ReactNode } from 'react';
import { CloseCircle, Danger, InfoCircle, TickCircle } from 'iconsax-reactjs';

export function Status({ tone = 'info', children }: { tone?: 'info' | 'success' | 'warning' | 'error'; children: ReactNode }) {
  const icons = { info: InfoCircle, success: TickCircle, warning: Danger, error: CloseCircle };
  const Icon = icons[tone];
  return <div className={`status status--${tone}`} role={tone === 'error' ? 'alert' : 'status'}><Icon className="status-icon" size={21} aria-hidden="true" focusable="false" /><div className="status-content">{children}</div></div>;
}
