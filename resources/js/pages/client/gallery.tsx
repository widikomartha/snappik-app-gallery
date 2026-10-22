import { Head } from '@inertiajs/react';
import { Shell } from '../../layouts/shell';
import { Status } from '../../components/ui/status';

// No route renders this boundary until gallery authorization is implemented.
export default function Gallery() {
  return <Shell surface="client"><Head title="Galeri" /><h1>Galeri foto</h1><Status>Galeri belum tersedia.</Status></Shell>;
}
