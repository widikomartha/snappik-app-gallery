import { PageHeader } from '../../components/ui/page-header';
import { Head, Link } from '@inertiajs/react';
import { Operations, Audit, idr, instant, stateLabel, type Page, type AuditRow } from './operations';

type Overview = { accounts: Record<string, number>; registered: number; projects: number; activeProjects: number; publishedModules: number; choices: number; confirmations: number; paidIdr: number; paidOrders: number; pendingOrders: number; pendingIdr: number; reviewOrders: number; heldVouchers: number; redeemedVouchers: number; failedImports: number; failedArchives: number; cleanupPending: number; oldestQueuedAt: number | null; oldestPaymentCheck: string | null };
export default function Dashboard({ overview: o, audit }: { overview: Overview; audit: Page<AuditRow> }) {
  return <Operations><Head title="Administrasi" /><PageHeader title="Administrasi platform" scene="admin" description="Pantau aktivitas, pembayaran sandbox, dan pekerjaan yang perlu ditinjau." />
    <div className="admin-summary-grid"><section className="panel stack" aria-label="Ringkasan akun"><h2>Akun dan aktivitas</h2><p>{o.registered} fotografer terdaftar</p>
      <p>{['unverified', 'trial', 'pro', 'expired', 'suspended'].map(s => `${stateLabel(s)}: ${o.accounts[s] ?? 0}`).join(' · ')}</p>
      <p>{o.projects} proyek · {o.activeProjects} aktif · {o.publishedModules} modul berstatus terbit · {o.choices} pilihan tersimpan · {o.confirmations} konfirmasi historis</p>
    </section>
    <section className="panel stack" aria-label="Ringkasan pembayaran"><h2>Pembayaran sandbox</h2>
      <p>Penerimaan terverifikasi: {idr(o.paidIdr)} · {o.paidOrders} pesanan</p><p>Belum lunas: {idr(o.pendingIdr)} · {o.pendingOrders} pesanan</p>
      <p>Penerimaan bruto dihitung satu kali per pemberian akses terverifikasi. Refund atau sengketa tetap ditinjau; angka ini bukan pendapatan bersih produksi.</p>
      <Link href="/admin/orders?review=1">Perlu tinjauan: {o.reviewOrders} pesanan</Link>
      <p>{o.heldVouchers} reservasi voucher ditahan · {o.redeemedVouchers} penukaran berhasil</p>
      <p>Pemeriksaan tertua pesanan belum lunas: {instant(o.oldestPaymentCheck)}</p>
    </section>
    <section className="panel stack" aria-label="Ringkasan pekerjaan"><h2>Pekerjaan dan pemeliharaan</h2><p>{o.failedImports} impor gagal · {o.failedArchives} ZIP gagal · {o.cleanupPending} percobaan menunggu pembersihan</p>
      <p>Antrean tertua: {instant(o.oldestQueuedAt === null ? null : new Date(o.oldestQueuedAt * 1000).toISOString())}</p><Link href="/admin/jobs">Periksa pekerjaan</Link>
    </section></div><Audit audit={audit} />
  </Operations>;
}
