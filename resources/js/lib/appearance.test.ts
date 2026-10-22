import { beforeEach, describe, expect, it, vi } from 'vitest';
import initializer from './appearance-init.js?raw';

describe('before-paint appearance controller', () => {
  let systemChange: () => void;
  let system: { matches: boolean; addEventListener: ReturnType<typeof vi.fn> };
  beforeEach(() => {
    localStorage.clear();
    system = { matches: true, addEventListener: vi.fn((_event, callback) => { systemChange = callback; }) };
    vi.spyOn(window, 'matchMedia').mockReturnValue(system as unknown as MediaQueryList);
  });
  const init = () => { new Function(initializer)(); return window.__snappikAppearance; };

  it('follows system changes until an explicit choice, then persists that choice', () => {
    const controller = init();
    expect(document.documentElement.dataset.theme).toBe('dark');
    system.matches = false;
    systemChange();
    expect(controller.getSnapshot()).toBe('light');
    controller.setTheme('dark');
    systemChange();
    expect(controller.getSnapshot()).toBe('dark');
    expect(localStorage.getItem('snappik.appearance')).toBe('dark');
  });

  it('restores only valid stored preferences', () => {
    localStorage.setItem('snappik.appearance', 'light');
    expect(init().getSnapshot()).toBe('light');
    localStorage.setItem('snappik.appearance', 'invalid');
    expect(init().getSnapshot()).toBe('dark');
  });

  it('keeps switching and notifying when storage is blocked', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('blocked'); });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('blocked'); });
    const controller = init();
    const listener = vi.fn();
    const unsubscribe = controller.subscribe(listener);
    controller.setTheme('light');
    expect(document.documentElement.style.colorScheme).toBe('light');
    expect(listener).toHaveBeenCalledOnce();
    unsubscribe();
    controller.setTheme('dark');
    expect(listener).toHaveBeenCalledOnce();
  });
});
