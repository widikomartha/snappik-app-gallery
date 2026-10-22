import { useCallback, useEffect, useRef, useState } from 'react';

// Keep the last acknowledged response on transport failure. Never replace it with
// a client estimate; abort old page requests before they can overwrite a new page.
export function useProgress<T>(initial: T, url: string) {
  const [data, setData] = useState(initial);
  const [error, setError] = useState('');
  const [denied, setDenied] = useState(false);
  const [pending, setPending] = useState(false);
  const active = useRef<AbortController | null>(null);
  const refresh = useCallback(async () => {
    if (active.current) return;
    const controller = new AbortController();
    active.current = controller;
    setPending(true);
    const timeout = window.setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
      if (active.current !== controller) return;
      if ([401, 403, 404].includes(response.status)) {
        setDenied(true);
        throw new Error('Akses hasil tidak tersedia. Masuk kembali atau periksa status akun.');
      }
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
      const next = await response.json() as T;
      if (active.current !== controller) return;
      setData(next); setError(''); setDenied(false);
    } catch (error) {
      if (active.current === controller) setError(error instanceof Error && error.message.startsWith('Akses') ? error.message : 'Penyegaran gagal. Data terakhir mungkin tidak mutakhir. Coba periksa lagi.');
    } finally {
      window.clearTimeout(timeout);
      if (active.current === controller) { active.current = null; setPending(false); }
    }
  }, [url]);
  useEffect(() => {
    setData(initial); setError(''); setDenied(false); setPending(false);
    const interval = window.setInterval(() => { if (!document.hidden) void refresh(); }, 15000);
    return () => { window.clearInterval(interval); active.current?.abort(); active.current = null; };
  }, [initial, refresh]);
  return { data, error, denied, pending, refresh };
}
