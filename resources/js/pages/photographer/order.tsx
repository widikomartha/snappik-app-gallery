import { Head, Link, useForm } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Status } from '../../components/ui/status';
import { PurchaseTotals } from '../../components/purchase-totals';
import type { Purchase } from '../../lib/billing';

export default function Order({ order, canCheckout }: { order: Purchase; canCheckout: boolean }) {
  const form = useForm({});
  return <Shell surface="photographer"><Head title="Pesanan Pro" /><div className="stack"><h1>Pesanan Pro</h1>
    <Link href="/billing">Semua tagihan</Link><section className="panel stack" aria-label="Pesanan Pro">
      <p className="photo-path">Pesanan {order.id}</p><Status>Sandbox QRIS · pembayaran uji.</Status>
      <PurchaseTotals quote={order} />
      {order.state === 'paid' && <Status tone="success">Pembayaran telah diverifikasi. Akses Pro telah ditambahkan {order.duration_days} hari sesuai ketentuan pesanan.</Status>}
      {order.state === 'failed' && <Status tone="warning">Penyedia mengonfirmasi pembayaran tidak berhasil. Reservasi voucher telah dilepas. Pesanan ini tidak memberikan akses Pro.</Status>}
      {(order.state === 'unknown' || order.state === 'creating' || order.state === 'created') && <Status tone="warning">Pembuatan checkout belum dapat dipastikan. Reservasi voucher tetap ditahan. Periksa pesanan yang sama sebelum mencoba lagi.</Status>}
      {order.state === 'pending' && <Status>Menunggu pembayaran terverifikasi. Menutup checkout tidak membatalkan pesanan atau melepas reservasi voucher.</Status>}
      {(order.state === 'review' || order.needsReview) && <Status tone="warning">Status penyedia memerlukan pemeriksaan pengelola. {order.applied_at ? 'Catatan akses yang telah diberikan tetap tersimpan.' : 'Pesanan belum memberikan akses.'} Hubungi pengelola jika diperlukan.</Status>}
      {order.windowElapsed && !['paid', 'failed'].includes(order.state) && <Status tone="warning">Jendela checkout telah berakhir. Ini belum membuktikan pembayaran gagal; reservasi menunggu pemeriksaan penyedia.</Status>}
      {!canCheckout && <Status tone="warning">Akun ditangguhkan. Checkout tidak dapat dilanjutkan.</Status>}
      <div className="actions">{canCheckout && !['paid', 'failed'].includes(order.state) && <form onSubmit={e => { e.preventDefault(); form.post(`/billing/orders/${order.id}/resume`, { preserveScroll: true }); }}><Button type="submit" pending={form.processing} variant="secondary">Periksa atau pulihkan pesanan</Button></form>}
        {canCheckout && order.canOpen && <a className="button button--primary" href={`/billing/orders/${order.id}/open`} target="_blank" rel="noopener noreferrer">Buka checkout QRIS sandbox</a>}
      </div>
      <p className="muted">Kembali dari Midtrans bukan bukti pembayaran. Ketentuan pesanan ini tetap berlaku walaupun harga atau voucher berubah.</p>
    </section></div></Shell>;
}
