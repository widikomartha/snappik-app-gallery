export type Quote = { base_idr: number; discount_idr: number; payable_idr: number; currency: string; duration_days: number; voucher_code: string | null; quote_token: string };
export type Purchase = Omit<Quote, 'quote_token'> & { id: string; state: string; provider_state: string | null; created_at: string; checkout_expires_at: string; last_checked_at: string | null; paid_at: string | null; applied_at: string | null; needsReview: boolean; canOpen: boolean; windowElapsed: boolean };
export const idr = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

export async function billingRequest(url: string, data: object) {
  const csrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length) ?? '';
  const response = await fetch(url, { method: 'POST', cache: 'no-store', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf) }, body: JSON.stringify(data) });
  const result = await response.json();
  if (!response.ok) {
    throw new Error(response.status === 422 ? Object.values(result.errors ?? {}).flat().join(' ') : 'Permintaan belum dapat dipastikan. Periksa akun dan koneksi, lalu coba lagi dengan pesanan yang sama.');
  }
  return result;
}
