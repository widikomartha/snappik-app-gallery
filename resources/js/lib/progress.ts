export type Page<T> = { data: T[]; total: number; prev_page_url: string | null; next_page_url: string | null };
export type Progress = {
  id: string; name: string; allowance: number; count: number;
  status: 'not_started' | 'selecting' | 'confirmed'; context: string[];
  epoch: number; version: number; revision: string | null; updatedAt: string | null; confirmedAt: string | null;
  confirmationCount: number; catalogueState: string; catalogueStale: boolean;
  publication: { selection: boolean; delivery: boolean };
};
export type Overview = { accountState: string; projects: Page<Progress> | null; checkedAt: string };
export const selectionLabels = { not_started: 'Belum dimulai', selecting: 'Sedang memilih', confirmed: 'Dikonfirmasi' };
export const contextLabels: Record<string, string> = {
  expired: 'Dibekukan · akses akun berakhir', archived: 'Ditutup · proyek diarsipkan',
  unpublished: 'Ditutup · pemilihan belum diterbitkan', deadline: 'Ditutup · batas waktu pemilihan berakhir',
};
export function localTime(value: string | null) {
  return value ? `${new Date(value).toLocaleString('id-ID', { timeZone: 'Asia/Makassar' })} WITA` : 'Belum ada pembaruan tercatat';
}
