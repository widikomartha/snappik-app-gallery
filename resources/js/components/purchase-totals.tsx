import { idr, type Quote } from '../lib/billing';

export function PurchaseTotals({ quote }: { quote: Omit<Quote, 'quote_token'> }) {
  return <div className="stack" aria-label="Rincian harga">
    <p>Pro · {quote.duration_days} hari · tanpa perpanjangan otomatis</p>
    <dl className="billing-totals"><div><dt>Harga dasar</dt><dd>{idr(quote.base_idr)}</dd></div>
      <div><dt>Diskon{quote.voucher_code && ` (${quote.voucher_code})`}</dt><dd>{idr(quote.discount_idr)}</dd></div>
      <div><dt>Total bayar (IDR)</dt><dd><strong>{idr(quote.payable_idr)}</strong></dd></div></dl>
    <p className="muted">Tanpa biaya tambahan pelanggan. Akses Pro aktif setelah pembayaran terverifikasi.</p>
  </div>;
}
