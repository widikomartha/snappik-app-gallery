import { motion, useReducedMotion } from 'motion/react';

export type IllustrationScene = 'account' | 'projects' | 'selection' | 'delivery' | 'billing' | 'admin';

/** Original decorative artwork. It never depicts a canonical save/payment/job state. */
export function Illustration({ scene = 'projects', className = '' }: { scene?: IllustrationScene; className?: string }) {
  const reduced = useReducedMotion();
  return <motion.svg className={`product-illustration ${className}`} viewBox="0 0 360 240" aria-hidden="true" focusable="false"
    initial={false} whileInView={reduced ? undefined : { y: [4, -3, 0], opacity: [.92, 1] }} viewport={{ once: true, amount: .15 }} transition={{ duration: .8 }}>
    <ellipse cx="180" cy="213" rx="133" ry="12" className="art-neutral" />
    <circle cx="181" cy="113" r="89" className="art-halo" />
    {(scene === 'projects' || scene === 'selection') && <>
      <rect x="81" y="45" width="130" height="159" rx="25" className="art-sage art-stroke" transform="rotate(-10 146 125)" />
      <rect x="133" y="30" width="137" height="175" rx="26" className="art-paper art-stroke" transform="rotate(5 201 117)" />
      <rect x="147" y="45" width="109" height="116" rx="17" className="art-lavender" />
      <circle cx="177" cy="75" r="15" className="art-peach" />
      <path d="M147 130q27-50 51-22t58-14v47q0 20-20 20h-70q-19 0-19-20Z" className="art-neutral" />
      <path d="M172 180h56" className="art-stroke" fill="none" />
      {scene === 'selection' ? <><circle cx="265" cy="169" r="28" className="art-paper art-stroke" /><path d="m285 190 24 22" className="art-stroke" fill="none" /><circle cx="265" cy="169" r="13" className="art-sage" /></> : <><path d="M45 149q0-13 14-13h32l12 14h45q14 0 14 15v28q0 15-16 15H62q-17 0-17-15Z" className="art-paper art-stroke" /><path d="M46 166h115" className="art-stroke" fill="none" /></>}
    </>}
    {scene === 'account' && <>
      <rect x="83" y="34" width="174" height="132" rx="28" className="art-paper art-stroke" transform="rotate(-6 170 100)" />
      <path d="m90 45 80 63 78-72" className="art-stroke" fill="none" />
      <path d="M124 142q0-17 18-17h21l10-14h44l11 14h19q18 0 18 18v48q0 18-18 18H143q-19 0-19-18Z" className="art-lavender art-stroke" />
      <circle cx="194" cy="166" r="29" className="art-ink" /><circle cx="194" cy="166" r="19" className="art-peach" /><circle cx="188" cy="159" r="6" className="art-paper" />
      <path d="M67 174q-14-27-28-12 5 24 28 12m0 0q19-24 30-7-10 21-30 7v36" className="art-sage art-stroke" />
    </>}
    {scene === 'delivery' && <>
      <rect x="75" y="34" width="130" height="165" rx="24" className="art-paper art-stroke" transform="rotate(-9 140 116)" />
      <rect x="88" y="49" width="104" height="111" rx="16" className="art-sage" /><circle cx="118" cy="76" r="15" className="art-paper" />
      <path d="M89 131q27-40 49-17t54-9v37q0 18-18 18h-67q-18 0-18-18Z" className="art-neutral" />
      <rect x="185" y="88" width="105" height="123" rx="26" className="art-lavender art-stroke" transform="rotate(6 237 149)" />
      <path d="M222 88v66q0 11 12 11h8q12 0 12-11V88" className="art-peach art-stroke" /><path d="M231 96h14m-14 14h14m-14 14h14m-14 14h14" className="art-stroke" fill="none" />
      <path d="M226 184h24" className="art-stroke" fill="none" />
    </>}
    {scene === 'billing' && <>
      <path d="M105 36q0-12 14-12h112q14 0 14 14v167l-19-10-19 10-19-10-19 10-19-10-19 10-26-10Z" className="art-paper art-stroke" />
      <path d="M128 62h95m-95 20h60m-60 22h95m-95 20h60" className="art-stroke" fill="none" />
      <rect x="164" y="137" width="132" height="78" rx="20" className="art-sage art-stroke" transform="rotate(-7 230 176)" /><path d="M167 162h126" className="art-stroke" fill="none" /><rect x="180" y="182" width="28" height="10" rx="5" className="art-ink" />
      <circle cx="81" cy="167" r="27" className="art-peach art-stroke" /><path d="M72 167h18m-9-9v18" className="art-stroke" fill="none" />
    </>}
    {scene === 'admin' && <>
      <rect x="60" y="40" width="239" height="162" rx="27" className="art-paper art-stroke" /><path d="M60 73h239M120 73v129" className="art-stroke" fill="none" />
      <circle cx="81" cy="57" r="4" className="art-ink" /><circle cx="96" cy="57" r="4" className="art-neutral" />
      <rect x="137" y="89" width="65" height="41" rx="12" className="art-lavender" /><rect x="216" y="89" width="65" height="41" rx="12" className="art-sage" />
      <path d="M139 151h52m-52 18h127M79 99h23m-23 21h23m-23 21h16" className="art-stroke" fill="none" />
      <circle cx="289" cy="191" r="27" className="art-peach art-stroke" /><path d="M289 176v30m-15-15h30" className="art-stroke" fill="none" />
    </>}
    <path d="M293 37v14m-7-7h14M47 99v10m-5-5h10" className="art-stroke" fill="none" /><circle cx="314" cy="117" r="5" className="art-peach" />
  </motion.svg>;
}
