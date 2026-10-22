import { useSyncExternalStore } from 'react';

export type Appearance = 'light' | 'dark';

declare global {
  interface Window {
    __snappikAppearance: {
      getSnapshot: () => Appearance;
      subscribe: (listener: () => void) => () => void;
      setTheme: (theme: Appearance) => void;
    };
  }
}

export function useAppearance() {
  const controller = window.__snappikAppearance;
  const appearance = useSyncExternalStore(controller.subscribe, controller.getSnapshot);
  return { appearance, setAppearance: controller.setTheme };
}
