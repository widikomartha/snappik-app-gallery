import '@fontsource/dm-sans/latin-400.css';
import '@fontsource/dm-sans/latin-500.css';
import '@fontsource/raleway/latin-300.css';
import '@fontsource/raleway/latin-500.css';
import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { MotionConfig } from 'motion/react';
import { easeOut, timing } from './lib/motion';
import type { ComponentType } from 'react';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
  title: (title) => `${title} — SnapPik`,
  resolve: async (name) => {
    const page = pages[`./pages/${name}.tsx`];
    if (!page) throw new Error(`Unknown application page: ${name}`);
    return (await page()).default;
  },
  setup({ el, App, props }) {
    let path = window.location.pathname;
    router.on('navigate', () => {
      if (path === window.location.pathname) return;
      path = window.location.pathname;
      requestAnimationFrame(() => document.getElementById('main')?.focus({ preventScroll: true }));
    });
    createRoot(el).render(
      <MotionConfig reducedMotion="user" transition={{ duration: timing.state, ease: easeOut }}>
        <App {...props} />
      </MotionConfig>,
    );
  },
});
