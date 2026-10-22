import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';
import { VoucherTerms, type Terms, type Page, Pagination } from './voucher';

type Row = { id: number; code: string; terms: Terms; held: number; used: number };

export default function Vouchers({ vouchers }: { vouchers: Page<Row> }) {
  return <Shell surface="admin"><Head title="Voucher" /><div className="stack">
    <Link href="/admin">Administrasi platform</Link><h1>Voucher</h1>
    <Link className="button button--primary" href="/admin/vouchers/create">Buat voucher</Link>
    <p>Kode memiliki riwayat versi tetap. Batas penggunaan menghitung seluruh versi, termasuk reservasi yang masih ditahan.</p>
    {vouchers.data.length === 0 && <Status>Belum ada voucher.</Status>}
    {vouchers.data.map(v => <article key={v.id} className="panel stack" aria-label={v.code}>
      <h2><Link href={`/admin/vouchers/${v.id}`}>{v.code}</Link></h2>
      <VoucherTerms terms={v.terms} />
      <p>{v.held} reservasi ditahan · {v.used} penggunaan berhasil</p>
      <Link href={`/admin/vouchers/${v.id}`}>Kelola {v.code}</Link>
    </article>)}
    <Pagination page={vouchers} label="Halaman voucher" />
  </div></Shell>;
}
