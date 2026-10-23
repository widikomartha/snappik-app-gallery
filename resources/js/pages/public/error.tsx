import { Head, Link } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';
import { Button } from '../../components/ui/button';

const messages: Record<number, { title: string; detail: string }> = {
  403: { title: 'Akses tidak diizinkan', detail: 'Akun ini tidak memiliki izin untuk halaman tersebut. Periksa akun yang digunakan atau hubungi pengelola.' },
  404: { title: 'Halaman tidak tersedia', detail: 'Tautan mungkin sudah berubah atau akses telah ditutup. Periksa tautan terbaru. Untuk galeri, hubungi fotografer agar memeriksa penerbitan dan batas waktu.' },
  419: { title: 'Sesi perlu diperbarui', detail: 'Sesi halaman telah berakhir. Muat ulang sebelum mencoba kembali. Perubahan yang belum disimpan belum dapat dipastikan.' },
  429: { title: 'Terlalu banyak percobaan', detail: 'Tunggu sebentar sebelum mencoba kembali. Periksa kata sandi atau koneksi Anda.' },
  500: { title: 'Layanan mengalami gangguan', detail: 'Permintaan belum dapat diselesaikan. Jika Anda baru menyimpan atau membayar, periksa hasil atau pesanan sebelum mencoba kembali.' },
  503: { title: 'Layanan sementara tidak tersedia', detail: 'Coba kembali beberapa saat lagi. Pilihan dan pesanan yang sudah tersimpan tetap tercatat.' },
};

export default function ErrorPage({ statusCode, clientGallery }: { statusCode: number; clientGallery: boolean }) {
  const message = messages[statusCode] ?? messages[500];
  return <Shell surface={clientGallery ? 'client' : 'public'}><Head title={message.title}><meta name="robots" content="noindex,nofollow,noarchive" /></Head>
    <section className="panel stack"><p className="eyebrow">{statusCode}</p><h1>{message.title}</h1><Status tone="error">{message.detail}</Status>
      <div className="actions"><Button variant="secondary" onClick={() => window.location.reload()}>Muat ulang halaman</Button><Link href="/">Kembali ke beranda</Link></div>
    </section></Shell>;
}
