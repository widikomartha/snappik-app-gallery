import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Reveal } from '../../components/ui/reveal';
import { idr } from '../../lib/billing';

export default function Welcome({ plan }: { plan: { price: number; days: number } | null }) {
  return (
    <Shell surface="public">
      <Head title="Galeri foto" />
      <Reveal className="hero">
        <p className="eyebrow">Untuk fotografer dan kliennya</p>
        <h1>Ruang untuk foto.<br />Kemudahan untuk klien.</h1>
        <p className="lede">Pilihan foto dan pengiriman hasil akhir, dalam satu pengalaman yang sederhana.</p>
        <p className="muted">Hubungkan folder Google Drive publik, bagikan galeri, lalu terima pilihan yang tersimpan. Klien cukup membuka tautan di browser.</p>
        <div className="actions"><Link href="/register" className="button button--primary">Mulai uji coba 7 hari</Link><a href="#harga">Lihat harga dan cara kerja</a></div>
        <p className="muted">Lingkungan pengujian · checkout QRIS menggunakan sandbox. Peluncuran publik masih dalam persiapan.</p>
      </Reveal>
      <section className="intro-grid" aria-label="Tentang SnapPik">
        <article><p className="eyebrow">01 / Pilihan</p><h2>Foto pilihan, tercatat.</h2><p className="muted">Atur batas pilihan, kata sandi dan tenggat. Klien memilih lintas folder, meninjau, lalu mengonfirmasi. Anda dapat menyalin nama berkas untuk proses editing.</p></article>
        <article><p className="eyebrow">02 / Pengiriman</p><h2>Hasil akhir, tersampaikan.</h2><p className="muted">Terbitkan folder hasil akhir yang terpisah. Klien mengunduh berkas asli atau satu ZIP lengkap melalui SnapPik, tanpa mengirim pesan WhatsApp.</p></article>
      </section>
      <Reveal className="intro-grid" id="harga" aria-label="Harga SnapPik">
        <article className="panel stack"><p className="eyebrow">Uji coba</p><h2>7 hari untuk mencoba.</h2><p>Dimulai setelah verifikasi email, satu kali per akun. Maksimal 3 proyek aktif, termasuk draf.</p><Link href="/register">Buat akun fotografer</Link></article>
        <article className="panel stack"><p className="eyebrow">Pro</p><h2>{plan ? `${idr(plan.price)} / ${plan.days} hari` : 'Harga belum tersedia'}</h2><p>Tanpa kuota jumlah proyek aktif, foto, ukuran foto, atau ukuran proyek. Tanpa debit otomatis. Satu voucher dapat digunakan untuk pembelian atau perpanjangan sesuai ketentuannya.</p><Link href="/billing">Lihat langganan dan tagihan</Link></article>
      </Reveal>
      <section className="panel stack" aria-label="Tentang sumber dan akses"><h2>Foto tetap di Drive Anda.</h2><p>SnapPik menggunakan folder Google Drive publik untuk JPEG/PNG. Kata sandi galeri SnapPik tidak menutup tautan sumber Drive. Unggah dan penyimpanan foto langsung belum tersedia.</p><p>Jika langganan berakhir, hasil pilihan tetap dapat dibaca dan disalin. Galeri hasil akhir yang masih terbuka tetap dapat diunduh sesuai kata sandi dan batas waktunya. ZIP tersedia selama 24 jam setelah siap dan dapat dibuat ulang.</p></section>
    </Shell>
  );
}
