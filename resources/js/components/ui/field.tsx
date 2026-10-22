import { useId, type InputHTMLAttributes } from 'react';

type Props = InputHTMLAttributes<HTMLInputElement> & { label: string; description?: string; error?: string };

export function Field({ label, description, error, id, ...props }: Props) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const describedBy = [props['aria-describedby'], description && `${inputId}-description`, error && `${inputId}-error`].filter(Boolean).join(' ') || undefined;
  return (
    <div className="field">
      <label htmlFor={inputId}>{label}</label>
      {description && <p id={`${inputId}-description`} className="muted">{description}</p>}
      <input {...props} id={inputId} aria-describedby={describedBy} aria-invalid={error ? true : props['aria-invalid']} />
      {error && <p id={`${inputId}-error`} className="field-error">{error}</p>}
    </div>
  );
}
