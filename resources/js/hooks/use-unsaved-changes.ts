import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useUnsavedChanges(dirty: boolean) {
  useEffect(() => {
    if (!dirty) return;
    const unload = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = ''; };
    const unsubscribe = router.on('before', event => {
      const visit = event.detail.visit;
      // Same-page refreshes retain drafts; submissions must be allowed to save them.
      if (visit.method === 'get' && visit.url.href !== window.location.href
        && !window.confirm('Perubahan belum disimpan. Tinggalkan halaman dan buang perubahan?')) event.preventDefault();
    });
    window.addEventListener('beforeunload', unload);
    return () => { unsubscribe(); window.removeEventListener('beforeunload', unload); };
  }, [dirty]);
}
