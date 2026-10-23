import { PageHeader } from '../../components/ui/page-header';
import { Head, Link } from '@inertiajs/react';
import { Operations, OrderRecords, type Page, type OrderRow } from './operations';
export default function Orders({ orders }: { orders: Page<OrderRow> }) {
  return <Operations><Head title="Transaksi" /><PageHeader title="Transaksi sandbox" scene="billing" description="Tinjau pesanan, pemeriksaan pembayaran, dan pemberian akses." /><div className="actions"><Link href="/admin/orders">Semua transaksi</Link><Link href="/admin/orders?review=1">Perlu tinjauan</Link></div><OrderRecords orders={orders} /></Operations>;
}
