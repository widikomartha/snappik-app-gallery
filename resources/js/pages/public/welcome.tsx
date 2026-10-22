import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';

export default function Welcome() {
  return (
    <Shell surface="public">
      <Head title="Galeri foto" />
      <section className="hero">
        <p className="eyebrow">Untuk fotografer dan kliennya</p>
        <h1>Ruang untuk foto.<br />Kemudahan untuk klien.</h1>
        <p className="lede">Pilihan foto dan pengiriman hasil akhir, dalam satu pengalaman yang sederhana.</p>
        <p className="muted">SnapPik sedang disiapkan. Layanan galeri belum tersedia.</p>
        <Link href="/login" className="button button--primary">Akun SnapPik <span aria-hidden="true">↗</span></Link>
      </section>
      <section className="intro-grid" aria-label="Tentang SnapPik">
        <article><p className="eyebrow">01 / Pilihan</p><h2>Foto pilihan, tercatat.</h2><p className="muted">Ruang pilihan yang dirancang untuk proses editing Anda.</p></article>
        <article><p className="eyebrow">02 / Pengiriman</p><h2>Hasil akhir, tersampaikan.</h2><p className="muted">Pengalaman pengiriman foto yang dirancang untuk klien Anda.</p></article>
      </section>
    </Shell>
  );
}
