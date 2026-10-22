import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Shell, type Surface } from '../../layouts/shell';
import { Button } from '../../components/ui/button';
import { Field } from '../../components/ui/field';
import { Dialog } from '../../components/ui/dialog';
import { Status } from '../../components/ui/status';

export default function Foundation({ surface }: { surface: Surface }) {
  const [draft, setDraft] = useState('Contoh proyek');
  const [open, setOpen] = useState(false);
  return (
    <Shell surface={surface}>
      <Head title="Pratinjau komponen lokal" />
      <h1>Fondasi antarmuka</h1>
      <p className="lede">Pratinjau lokal dengan data contoh. Tidak terhubung ke layanan galeri.</p>
      <nav className="actions" aria-label="Pratinjau tata letak">
        {(['public', 'auth', 'photographer', 'admin', 'client'] as const).map((name) => <Link key={name} href={`/_foundation/${name}`} aria-current={name === surface ? 'page' : undefined}>{name}</Link>)}
      </nav>
      <section className="panel stack" aria-label="Contoh komponen">
        <Field label="Nama proyek contoh" description="Perubahan ini hanya ada di halaman ini." value={draft} onChange={(event) => setDraft(event.target.value)} />
        <Field label="Contoh validasi" error="Nama proyek wajib diisi." defaultValue="" />
        <div className="actions">
          <Button onClick={() => setOpen(true)}>Buka dialog contoh</Button>
          <Button variant="secondary" pending>Menunggu respons…</Button>
          <Button variant="destructive" disabled>Hapus tidak tersedia</Button>
        </div>
        <Status>Contoh kosong: belum ada foto.</Status>
        <Status tone="warning">Contoh terkunci: pilihan tidak dapat diubah.</Status>
        <Status tone="warning">Contoh dibekukan: akses pilihan hanya baca.</Status>
        <Status tone="error">Contoh gagal: perubahan belum tersimpan. Coba kembali.</Status>
        <Status tone="success">Contoh berhasil: respons server telah diterima.</Status>
        <label className="choice"><input type="checkbox" defaultChecked /> Contoh foto terpilih</label>
      </section>
      <Dialog open={open} onClose={() => setOpen(false)} title="Dialog contoh"><p>Gunakan Tab untuk berpindah dan Escape untuk menutup.</p></Dialog>
    </Shell>
  );
}
