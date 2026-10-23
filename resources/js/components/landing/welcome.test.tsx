import type { HTMLAttributes, ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import Welcome from '../../pages/public/welcome';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ href, children, ...props }: HTMLAttributes<HTMLAnchorElement> & { href: string }) => <a href={href} {...props}>{children}</a>,
}));
vi.mock('../../layouts/shell', () => ({ Shell: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('../../components/ui/reveal', () => ({ Reveal: (props: HTMLAttributes<HTMLElement>) => <section {...props} /> }));

it('renders the server offer and removes it when pricing becomes unavailable', () => {
  const { rerender } = render(<Welcome plan={{ price: 49000, days: 60 }} />);
  expect(screen.getByText(/Rp\s*49\.000/)).toBeVisible();
  expect(screen.getByText('untuk 60 hari')).toBeVisible();
  expect(screen.queryByText(/29\.000/)).not.toBeInTheDocument();

  rerender(<Welcome plan={null} />);
  expect(screen.getByText('Harga belum tersedia')).toBeVisible();
  expect(screen.queryByText(/49\.000/)).not.toBeInTheDocument();
  expect(screen.queryByText('untuk 60 hari')).not.toBeInTheDocument();
});
