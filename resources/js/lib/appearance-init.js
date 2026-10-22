// Executed before CSS at first paint; the React controller uses this same instance.
(() => {
  const key = 'snappik.appearance';
  const system = window.matchMedia('(prefers-color-scheme: dark)');
  const listeners = new Set();
  let explicit = null;
  try {
    const stored = window.localStorage.getItem(key);
    if (stored === 'light' || stored === 'dark') explicit = stored;
  } catch { /* Storage is optional. */ }
  let current = explicit ?? (system.matches ? 'dark' : 'light');
  const apply = () => {
    document.documentElement.dataset.theme = current;
    document.documentElement.style.colorScheme = current;
    listeners.forEach((listener) => listener());
  };
  apply();
  system.addEventListener('change', () => {
    if (explicit === null) {
      current = system.matches ? 'dark' : 'light';
      apply();
    }
  });
  window.__snappikAppearance = {
    getSnapshot: () => current,
    subscribe: (listener) => {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    setTheme: (theme) => {
      if (theme !== 'light' && theme !== 'dark') return;
      explicit = current = theme;
      try { window.localStorage.setItem(key, theme); } catch { /* Keep in memory. */ }
      apply();
    },
  };
})();
