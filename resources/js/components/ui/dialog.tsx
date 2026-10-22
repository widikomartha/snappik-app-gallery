import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from './button';

export function Dialog({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  useEffect(() => {
    const dialog = ref.current;
    if (!open || !dialog) return;
    const invoker = document.activeElement;
    dialog.showModal();
    return () => {
      dialog.close();
      if (invoker instanceof HTMLElement && invoker.isConnected) invoker.focus();
    };
  }, [open]);
  return (
    <dialog ref={ref} tabIndex={-1} aria-labelledby={titleId}
      onCancel={(event) => { event.preventDefault(); onClose(); }}
      onKeyDown={(event) => {
        if (event.key !== 'Tab') return;
        const controls = [...event.currentTarget.querySelectorAll<HTMLElement>(
          'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
        )].filter((control) => control.getClientRects().length > 0 && control.tabIndex >= 0);
        const first = controls[0];
        const last = controls.at(-1);
        if (!first) {
          event.preventDefault();
          event.currentTarget.focus();
        } else if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }}>
      <div className="dialog-header">
        <h2 id={titleId}>{title}</h2>
        <Button variant="ghost" onClick={onClose} autoFocus>Tutup</Button>
      </div>
      {children}
    </dialog>
  );
}
