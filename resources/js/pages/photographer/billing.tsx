import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Field } from '../../components/ui/field';
import { Status } from '../../components/ui/status';
import { PurchaseTotals } from '../../components/purchase-totals';
import { billingRequest, idr, type Purchase, type Quote } from '../../lib/billing';

type Intent = { quote: Quote; key: string };
function readIntent(storageKey: string): Intent | null {
  try { const value = JSON.parse(sessionStorage.getItem(storageKey) ?? 'null'); return value?.quote?.quote_token && value?.key ? value : null; } catch { return null; }
}

export default function Billing({ ownerId, canCheckout, configured, base, orders }: { ownerId: number; canCheckout: boolean; configured: boolean; base: number; orders: { data: Purchase[]; prev_page_url: string | null; next_page_url: string | null } }) {
  const storageKey = `snappik-checkout-${ownerId}`;
  const [intent, setIntent] = useState<Intent | null>(() => readIntent(storageKey));
  const [voucher, setVoucher] = useState(intent?.quote.voucher_code ?? '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const sending = useRef(false);
  const saveIntent = (value: Intent | null) => {
    setIntent(value);
    try { if (value) sessionStorage.setItem(storageKey, JSON.stringify(value)); else sessionStorage.removeItem(storageKey); } catch { /* Current mounted intent remains usable when storage is blocked. */ }
  };
  async function submit(create: boolean) {
    if (sending.current) return;
    sending.current = true; setBusy(true); setError('');
    try {
      if (create && intent) {
        const result = await billingRequest('/billing/orders', { quote_token: intent.quote.quote_token, idempotency_key: intent.key });
        saveIntent(null);
        router.visit(`/billing/orders/${result.id}`);
      } else {
        const result = await billingRequest('/billing/quote', { voucher });
        saveIntent({ quote: result.quote, key: crypto.randomUUID() });
      }
    } catch (e) { setError(e instanceof Error ? e.message : 'Permintaan belum dapat dipastikan.'); }
    finally { sending.current = false; setBusy(false); }
  }
  return <Shell surface="photographer"><Head title="Langganan dan tagihan" /><div className="stack">
    <h1>Langganan dan tagihan</h1><Link href="/dashboard">Kembali ke ruang fotografer</Link>
    <section className="panel stack" aria-label="Checkout Pro"><h2>Pro, ruang untuk semua karya.</h2>
      <p>{idr(base)} / 30 hari. Tanpa kuota jumlah proyek aktif, foto, ukuran foto, atau ukuran proyek.</p>
      <Status>Lingkungan sandbox · pembayaran uji QRIS.</Status>
      {!canCheckout && <Status tone="warning">{configured ? 'Akun ditangguhkan. Riwayat tagihan tetap dapat dibaca; checkout tidak tersedia.' : 'Checkout sandbox belum dikonfigurasi oleh pengelola.'}</Status>}
      <form className="stack" aria-label="Periksa harga Pro" onSubmit={e => { e.preventDefault(); void submit(false); }}>
        <Field label="Kode voucher (opsional)" value={voucher} disabled={!canCheckout || busy} maxLength={64} onChange={e => { setVoucher(e.target.value); saveIntent(null); }} description="Satu voucher untuk pembelian awal maupun perpanjangan. Reservasi dibuat saat melanjutkan checkout." />
        <Button type="submit" variant="secondary" disabled={!canCheckout || busy}>Periksa harga</Button>
      </form>
      {error && <Status tone="error">{error} Jika sudah mengirim checkout, coba kembali dengan harga yang sama atau buka pesanan di riwayat.</Status>}
      {intent && <section className="stack" aria-label="Harga terkonfirmasi"><PurchaseTotals quote={intent.quote} />
        <Button pending={busy} disabled={!canCheckout} onClick={() => void submit(true)}>Buat atau lanjutkan checkout QRIS</Button>
        <p className="muted">Jika koneksi terputus, tombol ini menggunakan identitas checkout yang sama. Menutup halaman tidak membatalkan pesanan.</p>
      </section>}
    </section>
    <section className="panel stack" aria-label="Riwayat pesanan"><h2>Riwayat pesanan</h2>
      {orders.data.length === 0 && <p>Belum ada pesanan.</p>}
      {orders.data.map(order => <article key={order.id} className="voucher-record"><p>Pro · {idr(order.payable_idr)} · {order.state === 'review' ? 'Menunggu pemeriksaan' : 'Belum terverifikasi'}</p><Link href={`/billing/orders/${order.id}`}>Buka pesanan {order.id}</Link></article>)}
      <div className="actions">{orders.prev_page_url && <Link href={orders.prev_page_url}>Sebelumnya</Link>}{orders.next_page_url && <Link href={orders.next_page_url}>Berikutnya</Link>}</div>
    </section>
  </div></Shell>;
}
