import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Button } from './button';
import { Field } from './field';

describe('accessible controls', () => {
  it('associates the label, description, and validation with the input', () => {
    render(<Field label="Nama" description="Deskripsi" error="Wajib diisi" />);
    const input = screen.getByRole('textbox', { name: 'Nama' });
    expect(input).toHaveAccessibleDescription('Deskripsi Wajib diisi');
    expect(input).toHaveAttribute('aria-invalid', 'true');
  });

  it('blocks repeated actions while pending and never submits by default', () => {
    const action = vi.fn();
    render(<Button pending onClick={action}>Menunggu</Button>);
    const button = screen.getByRole('button', { name: 'Menunggu' });
    expect(button).toHaveAttribute('type', 'button');
    expect(button).toHaveAttribute('aria-busy', 'true');
    fireEvent.click(button);
    expect(action).not.toHaveBeenCalled();
  });
});
