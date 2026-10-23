import { Head, Link } from '@inertiajs/react';
import { Operations, Pagination, instant, stateLabel, type Page } from './operations';
import { Status } from '../../components/ui/status';
export type Account = { id: number; name: string; email: string; state: string; suspension_version: number; accessEndsAt: string | null; created_at: string };
export default function Accounts({ accounts }: { accounts: Page<Account> }) {
  return <Operations><Head title="Akun fotografer" /><h1>Akun fotografer</h1>
    {!accounts.data.length && <Status>Belum ada akun fotografer.</Status>}
    {accounts.data.map(a => <article key={a.id} className="panel stack"><h2><Link href={`/admin/accounts/${a.id}`}>{a.name}</Link></h2><p>{a.email} · {stateLabel(a.state)}</p><p>Akses sampai {instant(a.accessEndsAt)}</p><Link href={`/admin/accounts/${a.id}`}>Periksa akun {a.email}</Link></article>)}
    <Pagination page={accounts} label="Halaman akun" />
  </Operations>;
}
