import { useAppearance } from '../../lib/appearance';
import { Button } from './button';

export function AppearanceControl() {
  const { appearance, setAppearance } = useAppearance();
  const label = appearance === 'light' ? 'Aktifkan tema gelap' : 'Aktifkan tema terang';
  return <Button variant="ghost" onClick={() => setAppearance(appearance === 'light' ? 'dark' : 'light')}>{label}</Button>;
}
