import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { ArrowDown2, ArrowRight, Chart2, DocumentCopy, FolderOpen, Gallery, MonitorMobbile, Pause, Play, SecuritySafe, TickCircle, TickSquare } from 'iconsax-reactjs';
import { Shell } from '../../layouts/shell';
import { Reveal } from '../../components/ui/reveal';
import { SelectionDemo } from '../../components/landing/selection-demo';
import { idr } from '../../lib/billing';
import '../../../css/landing.css';

const reducedMotionQuery = '(prefers-reduced-motion: reduce)';
function subscribeMotionPreference(listener: () => void) {
  const preference = window.matchMedia(reducedMotionQuery);
  preference.addEventListener('change', listener);
  return () => preference.removeEventListener('change', listener);
}

export default function Welcome({ plan }: { plan: { price: number; days: number } | null }) {
  const [paused, setPaused] = useState(false);
  const reduced = useSyncExternalStore(subscribeMotionPreference, () => window.matchMedia(reducedMotionQuery).matches, () => false);
  const root = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const element = root.current;
    if (!element || typeof IntersectionObserver === 'undefined') return;
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) entry.target.classList.toggle('landing-offscreen', !entry.isIntersecting);
    }, { rootMargin: '80px' });
    element.querySelectorAll('.landing-canvas,.landing-step,.landing-delivery-art,.landing-drive-art,.landing-final-art').forEach(scene => observer.observe(scene));
    return () => observer.disconnect();
  }, []);

  return <Shell surface="public" presentation="landing">
    <Head title="Galeri pilihan dan pengiriman foto">
      <meta name="description" content="Bagikan galeri dari Google Drive, terima pilihan foto klien, lalu sampaikan hasil edit melalui SnapPik. Coba alurnya selama 7 hari." />
    </Head>
    <div className="landing-page" ref={root} data-motion-paused={paused} data-motion-reduced={reduced}>

      <div className="landing-hero">
        <span className="landing-kicker">Dari pilihan ke hasil akhir</span>
        <h1>Foto Anda.<br />Pilihan mereka.<br />Semua tertata.</h1>
        <p className="landing-description">Bagikan galeri, terima pilihan foto untuk diedit, lalu sampaikan hasil akhirnya melalui SnapPik.</p>
        <div className="landing-cta-row"><Link href="/register" className="landing-cta">Mulai uji coba 7 hari<ArrowRight size={18} aria-hidden="true" focusable="false" /></Link><a className="landing-secondary" href="#landing-cara">Lihat cara kerja</a></div>
        <p className="landing-hero-note">Untuk fotografer dan kliennya. Klien cukup membuka tautan, tanpa membuat akun.</p>
        <div className="landing-canvas">
          <svg className="landing-art" viewBox="0 0 700 300" aria-hidden="true">
            <ellipse cx="353" cy="269" rx="262" ry="17" className="soft"/>
            <path d="M125 146Q211 26 350 96T591 139" fill="none" className="line landing-draw" opacity=".45"/>
            <g className="landing-float"><path d="M68 144Q68 126 89 126H110L123 109H166L180 126H203Q224 126 224 147V221Q224 242 202 242H89Q68 242 68 221Z" className="paper line"/><rect x="80" y="144" width="132" height="76" rx="21" className="soft"/><circle cx="146" cy="181" r="44" className="ink"/><circle cx="146" cy="181" r="30" className="peach"/><circle cx="146" cy="181" r="20" className="mid"/><ellipse cx="138" cy="172" rx="6" ry="8" className="paper"/><rect x="183" y="132" width="20" height="7" rx="3.5" className="ink"/><path d="M55 110v-13m-6 6h12" fill="none" className="line"/></g>
            <g className="landing-drift"><rect x="250" y="54" width="171" height="201" rx="28" className="soft line" transform="rotate(-10 335 150)"/></g>
            <g className="landing-float" data-landing-delay="1"><rect x="280" y="39" width="171" height="211" rx="28" className="paper line"/><rect x="294" y="53" width="143" height="152" rx="20" className="lavender"/><circle cx="332" cy="91" r="19" className="mid"/><path d="M295 171Q318 121 343 140T379 136Q411 100 436 148V184Q436 205 415 205H315Q294 205 294 184Z" className="mid"/><path d="M346 224h42" className="line" fill="none"/><path d="m373 105 7 11m25-13-7 11" className="line" fill="none"/><path d="M377 128q11 11 21 0" className="line" fill="none"/></g>
            <g className="landing-check"><circle cx="440" cy="60" r="34" className="ink"/><path d="m426 60 10 10 20-23" fill="none" stroke="var(--landing-bg)" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round"/></g>
            <g className="landing-drift" data-landing-delay="1"><path d="M489 163Q489 147 507 147H539L552 161H612Q633 161 633 182V233Q633 254 612 254H510Q489 254 489 233Z" className="mid line"/><rect x="512" y="131" width="87" height="93" rx="17" className="sage line" transform="rotate(8 555 177)"/><path d="M484 195Q481 177 501 177H626Q646 177 642 196L636 237Q634 254 615 254H510Q491 254 489 237Z" className="soft line"/><path d="M545 216h39" className="line" fill="none"/></g>
            <g className="landing-flight"><path d="m525 75 69-22-25 63-14-26Z" className="paper line"/><path d="m555 90 39-37" className="line" fill="none"/></g>
            <path d="M235 29v15m-7-8h14m268 88v12m-6-6h12m-57 125v14m-7-7h14" className="line" fill="none"/>
            <circle cx="611" cy="117" r="7" className="soft"/><circle cx="198" cy="70" r="6" className="mid"/>
          </svg>
          {!reduced && <button type="button" className="landing-pause" aria-label={paused ? 'Putar animasi' : 'Jeda animasi'} title={paused ? 'Putar animasi' : 'Jeda animasi'} aria-pressed={paused} onClick={() => setPaused(value => !value)}>
            {paused ? <Play size={20} aria-hidden="true" focusable="false" /> : <Pause size={20} aria-hidden="true" focusable="false" />}
          </button>}
        </div>
      </div>
      <div className="landing-value-strip"><span><Gallery size={20} aria-hidden="true" focusable="false" />Satu proyek, dua galeri</span><span><FolderOpen size={20} aria-hidden="true" focusable="false" />Foto tetap di Drive Anda</span><span><MonitorMobbile size={20} aria-hidden="true" focusable="false" />Bisa dibuka lewat browser</span></div>
      <Reveal className="landing-section landing-intro" aria-labelledby="landing-intro-title">
        <div><span className="landing-kicker">Setelah sesi foto</span><h2 id="landing-intro-title">Pilihan foto jelas.<br />Langkah berikutnya juga.</h2></div>
        <div><p className="landing-copy">Saat pilihan foto tersebar di pesan, nama berkas mudah tertukar dan proses editing sulit dilanjutkan. SnapPik memberi klien ruang untuk memilih, lalu mengirimkan konfirmasi yang bisa Anda tindak lanjuti.</p><p className="landing-copy">Setelah editing selesai, terbitkan galeri hasil akhir dari folder terpisah. Pemilihan dan pengiriman tetap berada dalam proyek yang sama.</p></div>
      </Reveal>
      <Reveal className="landing-process" id="landing-cara" aria-labelledby="landing-cara-title">
        <span className="landing-kicker landing-center">Cara kerja</span>
        <h2 id="landing-cara-title">Tiga langkah. Semua tertata.</h2>
        <div className="landing-trio">
          <div className="landing-step">
            <svg className="landing-art landing-mini" viewBox="0 0 210 140" aria-hidden="true"><g className="landing-float"><path d="M30 55q0-13 15-13h35l12 14h66q16 0 16 16v42q0 15-16 15H46q-16 0-16-15Z" className="soft line"/><rect x="66" y="20" width="79" height="79" rx="18" className="sage line" transform="rotate(8 105 59)"/><circle cx="89" cy="43" r="9" className="mid"/><path d="m74 77 17-20 17 13 16-20 13 30" className="line" fill="none"/><path d="M29 82q0-13 14-13h126q15 0 12 15l-5 32q-2 13-16 13H45q-14 0-15-14Z" className="mid line"/></g></svg>
            <div><h3>Hubungkan folder.</h3><p>Buat proyek dan hubungkan folder Google Drive publik berisi foto JPEG/PNG. Atur batas pilihan, lalu terbitkan galeri.</p></div>
          </div>
          <div className="landing-step">
            <svg className="landing-art landing-mini" viewBox="0 0 210 140" aria-hidden="true"><g className="landing-drift"><rect x="41" y="12" width="126" height="117" rx="25" className="paper line"/><rect x="55" y="27" width="43" height="37" rx="12" className="lavender"/><rect x="110" y="27" width="43" height="37" rx="12" className="mid"/><rect x="55" y="76" width="43" height="37" rx="12" className="mid"/><rect x="110" y="76" width="43" height="37" rx="12" className="soft"/></g><g className="landing-check" data-landing-delay="1"><circle cx="165" cy="110" r="23" className="ink"/><path d="m155 110 7 7 13-16" fill="none" stroke="var(--landing-bg)" strokeWidth="4" strokeLinecap="round"/></g></svg>
            <div><h3>Klien memilih.</h3><p>Bagikan tautan. Klien memilih foto, meninjau pilihannya, lalu mengonfirmasi. Anda mendapatkan daftar nama berkas untuk editing.</p></div>
          </div>
          <div className="landing-step">
            <svg className="landing-art landing-mini" viewBox="0 0 210 140" aria-hidden="true"><g className="landing-float" data-landing-delay="2"><rect x="47" y="17" width="115" height="111" rx="28" className="soft line"/><path d="M91 18v73q0 10 11 10h8q11 0 11-10V18" className="peach line"/><path d="M101 22h10m-10 13h10m-10 13h10m-10 13h10" className="line" fill="none"/><rect x="95" y="72" width="23" height="30" rx="9" className="ink"/><path d="M106 81v9" fill="none" stroke="var(--landing-bg)" strokeWidth="3" strokeLinecap="round"/></g><g className="landing-pulse"><circle cx="163" cy="107" r="24" className="paper line"/><path d="M163 94v23m-8-8 8 8 8-8" className="line" fill="none"/></g></svg>
            <div><h3>Sampaikan hasil.</h3><p>Hubungkan folder hasil edit yang terpisah dan terbitkan galeri pengiriman. Klien mengunduh foto asli satu per satu atau dalam satu ZIP lengkap.</p></div>
          </div>
        </div>
      </Reveal>
      <Reveal className="landing-section landing-feature" id="landing-fitur" aria-labelledby="landing-selection-title">
        <div className="landing-feature-copy"><span className="landing-kicker">01 / Galeri pilihan</span><h2 id="landing-selection-title">Klien memilih.<br />Anda lanjut berkarya.</h2><p className="landing-copy">Berikan galeri yang mudah ditelusuri, dengan folder, nama berkas, dan preview foto. Pilihan tersimpan selama klien memilih dan dikunci setelah konfirmasi.</p><ul className="landing-list"><li><TickCircle size={18} aria-hidden="true" focusable="false" />Atur jumlah maksimal pilihan sesuai kesepakatan proyek.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Klien bisa memilih lintas subfolder dan meninjau sebelum konfirmasi.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Pantau progres serta salin nama berkas pilihan untuk editing.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Buka kembali pilihan yang sudah dikonfirmasi saat akses akun dan galeri masih mengizinkan.</li></ul></div>
        <SelectionDemo />
      </Reveal>
      <Reveal className="landing-section landing-feature landing-delivery" aria-labelledby="landing-delivery-title">
        <div className="landing-delivery-art" aria-hidden="true"><div className="landing-photo-bundle landing-float"><div className="landing-bundle-back"></div><div className="landing-bundle-front"><span className="landing-synthetic landing-scene-c"><span></span></span></div></div><div className="landing-zip landing-drift"><span className="landing-zip-teeth"></span><strong>ZIP</strong></div><div className="landing-delivery-caption">Foto asli · satu ZIP lengkap</div></div>
        <div className="landing-feature-copy"><span className="landing-kicker">02 / Galeri hasil akhir</span><h2 id="landing-delivery-title">Hasil edit selesai.<br />Siap diterima klien.</h2><p className="landing-copy">Galeri hasil akhir memiliki folder sumber tersendiri. Klien melihat foto yang sudah selesai dan mengunduhnya langsung melalui SnapPik.</p><ul className="landing-list"><li><TickCircle size={18} aria-hidden="true" focusable="false" />Unduhan individual mempertahankan berkas asli.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Unduh semua menghasilkan satu ZIP lengkap, termasuk foto dalam subfolder.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Status pembuatan ZIP bisa diperiksa kembali, meski browser sempat ditutup.</li></ul><p className="landing-small">ZIP tersedia selama 24 jam setelah siap dan dapat dibuat ulang selama akses galeri serta sumber foto masih tersedia.</p></div>
      </Reveal>
      <Reveal className="landing-section landing-tools" aria-labelledby="landing-tools-title"><span className="landing-kicker landing-center">Sesuai kebutuhan proyek</span><h2 id="landing-tools-title">Anda yang mengatur alurnya.</h2><div className="landing-tools-grid"><article><TickSquare className="landing-feature-icon" size={26} aria-hidden="true" focusable="false" /><h3>Batas pilihan fleksibel</h3><p>Atur jumlah foto yang boleh dipilih klien. Batas pilihan ini terpisah dari jumlah foto yang bisa tampil di galeri.</p></article><article><SecuritySafe className="landing-feature-icon" size={26} aria-hidden="true" focusable="false" /><h3>Kata sandi &amp; tenggat</h3><p>Atur kata sandi dan batas waktu secara terpisah untuk galeri pilihan serta galeri hasil akhir.</p></article><article><Chart2 className="landing-feature-icon" size={26} aria-hidden="true" focusable="false" /><h3>Progres yang terbaca</h3><p>Lihat jumlah pilihan dan status konfirmasi. Bedakan pilihan yang masih berlangsung dengan hasil yang sudah dikonfirmasi.</p></article><article><DocumentCopy className="landing-feature-icon" size={26} aria-hidden="true" focusable="false" /><h3>Nama berkas siap dipakai</h3><p>Salin nama berkas lengkap, dengan jalur folder bila diperlukan untuk membedakan nama yang sama.</p></article></div></Reveal>
      <Reveal className="landing-section landing-drive" aria-labelledby="landing-drive-title"><div className="landing-drive-art" aria-hidden="true"><div className="landing-drive-folder landing-float"><span></span><span></span><span></span></div><div className="landing-drive-dot"></div></div><div><span className="landing-kicker">Tetap memakai Drive Anda</span><h2 id="landing-drive-title">Sumber foto Anda.<br />Pengalaman galeri SnapPik.</h2><p className="landing-copy">Foto JPEG/PNG tetap berada di folder Google Drive publik milik Anda. SnapPik menampilkan galeri, mencatat pilihan, dan menyiapkan unduhan hasil akhir.</p><p className="landing-source-note">Folder sumber harus dibagikan secara publik. Kata sandi galeri SnapPik tidak membuat tautan sumber Drive menjadi privat. Unggah dan penyimpanan foto langsung di SnapPik belum tersedia.</p></div></Reveal>
      <Reveal className="landing-section landing-pricing" id="landing-harga" aria-labelledby="landing-price-title"><span className="landing-kicker landing-center">Harga yang sederhana</span><h2 id="landing-price-title">Coba alurnya.<br />Lanjut saat sudah cocok.</h2><p className="landing-section-lede">Mulai dengan trial, lalu aktifkan Pro untuk melanjutkan proyek Anda.</p><div className="landing-price-grid"><article className="landing-price-card"><span className="landing-kicker">Uji coba</span><h3>7 hari untuk mencoba.</h3><p className="landing-price">Gratis<span>selama 7 hari</span></p><p className="landing-copy">Untuk mencoba alur pemilihan hingga pengiriman hasil akhir.</p><ul className="landing-list"><li><TickCircle size={18} aria-hidden="true" focusable="false" />Dimulai setelah verifikasi email.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Maksimal 3 proyek aktif, termasuk draf.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Dua galeri dalam satu proyek.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Satu kali trial per akun.</li></ul><Link href="/register" className="landing-cta landing-price-action">Mulai uji coba 7 hari<ArrowRight size={18} aria-hidden="true" focusable="false" /></Link></article><article className="landing-price-card landing-pro"><span className="landing-kicker">Pro</span><h3>Ruang untuk proyek Anda.</h3><p className={`landing-price${plan ? "" : " landing-price-unavailable"}`}>{plan ? idr(plan.price) : "Harga belum tersedia"}{plan && <span>untuk {plan.days} hari</span>}</p><p className="landing-copy">Tanpa kuota konten dari paket SnapPik.</p><ul className="landing-list"><li><TickCircle size={18} aria-hidden="true" focusable="false" />Tanpa kuota jumlah proyek aktif.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Tanpa kuota jumlah foto.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Tanpa kuota ukuran foto atau proyek.</li><li><TickCircle size={18} aria-hidden="true" focusable="false" />Tanpa debit otomatis.</li></ul><Link href="/register" className="landing-cta landing-price-action">Buat akun fotografer<ArrowRight size={18} aria-hidden="true" focusable="false" /></Link></article></div><p className="landing-price-note">Ketersediaan sumber dan batas teknis Google Drive tetap berlaku.</p></Reveal>
      <Reveal className="landing-section landing-faq" id="landing-faq" aria-labelledby="landing-faq-title"><span className="landing-kicker landing-center">Sebelum mulai</span><h2 id="landing-faq-title">Pertanyaan yang mungkin Anda punya.</h2><div className="landing-faq-list">
        <details open><summary>Apakah klien perlu membuat akun?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Tidak. Klien membuka tautan proyek di browser. Jika Anda mengatur kata sandi, klien perlu memasukkannya sebelum membuka galeri terkait.</p></details>
        <details><summary>Apakah foto perlu diunggah lagi ke SnapPik?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Tidak. SnapPik menggunakan folder Google Drive publik sebagai sumber foto JPEG/PNG, termasuk foto di subfolder. Unggah dan penyimpanan foto langsung di SnapPik belum tersedia.</p></details>
        <details><summary>Apa bedanya galeri pilihan dan galeri hasil akhir?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Galeri pilihan membantu klien memilih foto yang akan diedit. Galeri hasil akhir berisi foto selesai untuk diunduh. Keduanya berada dalam satu proyek, tetapi memakai folder sumber yang terpisah dan tidak tumpang tindih.</p></details>
        <details><summary>Apakah Pro membatasi foto atau proyek?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Paket Pro tidak menerapkan kuota jumlah proyek aktif, jumlah foto, ukuran foto, atau ukuran proyek. Batas teknis dan ketersediaan Google Drive tetap berlaku. Batas pilihan yang Anda atur hanya menentukan berapa foto yang boleh dipilih klien.</p></details>
        <details><summary>Bisakah klien mengubah pilihan setelah konfirmasi?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Setelah konfirmasi, pilihan terkunci. Anda dapat membukanya kembali selama akses akun aktif dan aturan galeri masih mengizinkan. Pilihan sebelumnya tetap dipertahankan.</p></details>
        <details><summary>Bagaimana jika langganan berakhir?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Anda tetap dapat masuk, membaca hasil pilihan, menyalin nama berkas, dan memperpanjang langganan. Pembuatan atau perubahan proyek serta perubahan pilihan klien dihentikan sementara. Galeri yang masih terbuka tetap bisa dilihat dan hasil akhir tetap bisa diunduh sesuai aturan aksesnya.</p></details>
        <details><summary>Apakah kata sandi SnapPik melindungi tautan Drive?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Kata sandi mengatur akses ke galeri SnapPik. Kata sandi tersebut tidak mengubah izin folder Google Drive publik. Siapa pun yang memiliki tautan sumber publik masih dapat mengakses sumber sesuai pengaturan Drive.</p></details>
        <details><summary>Apakah unduh semua berarti satu ZIP?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Ya. SnapPik menyiapkan satu ZIP berisi seluruh foto yang didukung dalam galeri hasil akhir yang diterbitkan. ZIP tersedia selama 24 jam setelah siap. Jika ada sumber yang gagal diambil, pembuatan ZIP dinyatakan gagal agar hasil tidak terpotong diam-diam.</p></details>
        <details><summary>Apakah langganan diperpanjang otomatis?<ArrowDown2 size={20} aria-hidden="true" focusable="false" /></summary><p>Tidak ada debit otomatis. Anda melakukan pembelian atau perpanjangan saat diperlukan. Satu voucher dapat digunakan per pembelian atau perpanjangan sesuai ketentuannya.</p></details>
      </div></Reveal>
      <Reveal className="landing-section landing-final" aria-labelledby="landing-final-title"><div className="landing-final-art" aria-hidden="true"><div className="landing-final-photo landing-float"><span className="landing-synthetic landing-scene-b"><span></span></span></div><span className="landing-final-spark landing-pulse">✦</span></div><span className="landing-kicker">Proyek berikutnya, lebih tertata</span><h2 id="landing-final-title">Dari foto pilihan<br />sampai hasil yang diterima.</h2><p className="landing-section-lede">Beri klien ruang untuk memilih. Beri diri Anda alur yang jelas untuk melanjutkan.</p><div className="landing-cta-row"><Link href="/register" className="landing-cta">Mulai uji coba 7 hari<ArrowRight size={18} aria-hidden="true" focusable="false" /></Link></div><p className="landing-small">Trial dimulai setelah verifikasi email. Hingga 3 proyek aktif, termasuk draf.</p></Reveal>

    </div>
  </Shell>;
}
