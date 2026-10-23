import { useState } from 'react';

// This public example is local UI state, independent of real gallery choices.
const allowance = 3;
const scenes = ['a', 'b', 'c', 'd'];

export function SelectionDemo() {
  const [selected, setSelected] = useState([0]);
  const [atLimit, setAtLimit] = useState(false);

  function toggle(index: number) {
    if (selected.includes(index)) {
      setSelected(selected.filter(value => value !== index));
      setAtLimit(false);
    } else if (selected.length < allowance) {
      setSelected([...selected, index]);
      setAtLimit(false);
    } else {
      setAtLimit(true);
    }
  }

  return <div className="landing-gallery-demo" role="group" aria-label="Contoh interaktif galeri pilihan">
    <div className="landing-demo-top"><strong>Coba galeri pilihan</strong><span>Simulasi</span></div>
    <div className="landing-demo-grid">
      {scenes.map((scene, index) => <label className="landing-demo-photo" key={scene}>
        <span className={`landing-synthetic landing-scene-${scene}`} aria-hidden="true"><span /></span>
        <span className="landing-photo-label"><input type="checkbox" checked={selected.includes(index)} onChange={() => toggle(index)} /><span>FOTO_00{index + 1}.jpg</span></span>
      </label>)}
    </div>
    <p className="landing-demo-feedback" role="status">{atLimit
      ? `Batas contoh ini ${allowance} foto. Hapus satu pilihan untuk menggantinya.`
      : `${selected.length} dari ${allowance} pilihan pada contoh ini.`}</p>
    <p className="landing-small">Pilihan contoh tidak disimpan.</p>
  </div>;
}
