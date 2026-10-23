import { useAppearance } from '../../lib/appearance';
import { Button } from './button';
import { Moon, Sun1 } from 'iconsax-reactjs';

export function AppearanceControl({ compact = false }: { compact?: boolean }) {
  const { appearance, setAppearance } = useAppearance();
  const label = appearance === 'light' ? 'Aktifkan tema gelap' : 'Aktifkan tema terang';
  return <Button variant="ghost" aria-label={label} title={compact ? label : undefined} className={compact ? 'button--icon' : undefined} onClick={() => setAppearance(appearance === 'light' ? 'dark' : 'light')}>
    {compact ? appearance === 'light' ? <Moon size={22} aria-hidden="true" focusable="false" /> : <Sun1 size={22} aria-hidden="true" focusable="false" /> : label}
  </Button>;
}
