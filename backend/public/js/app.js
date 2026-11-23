/* Frontend Kedai Kopi — Alpine.js SPA yang memanggil REST API /api/v1 */
const API = '/api/v1';

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
const angka = (n) => Number(n ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
const waktu = (d) => (d ? new Date(d).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-');
const tanggal = (d) => (d ? new Date(d).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-');
const label = (s) => String(s ?? '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
/** Ambil nilai bersarang; "a.b|c.d" berarti pakai a.b, jika kosong pakai c.d. */
const pluck = (o, path) => {
    for (const p of path.split('|')) {
        const v = p.split('.').reduce((a, k) => a?.[k], o);
        if (v !== null && v !== undefined && v !== '') return v;
    }
    return undefined;
};

function formatValue(v, fmt) {
    if (v === null || v === undefined || v === '') return '-';
    switch (fmt) {
        case 'rupiah': return rupiah(v);
        case 'num': return angka(v);
        case 'date': return tanggal(v);
        case 'datetime': return waktu(v);
        case 'label': return label(v);
        default: return v;
    }
}

async function api(method, path, body) {
    const token = localStorage.getItem('token');
    const isForm = body instanceof FormData;
    const res = await fetch(API + path, {
        method,
        headers: {
            Accept: 'application/json',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { Authorization: 'Bearer ' + token } : {}),
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    });
    if (res.status === 401 && path !== '/login') {
        localStorage.removeItem('token');
        location.reload();
    }
    if (res.status === 204) return null;
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const errors = data.errors || {};
        const first = Object.values(errors).flat()[0];
        const err = new Error(first || data.message || 'Terjadi kesalahan');
        err.errors = errors;
        err.status = res.status;
        throw err;
    }
    return data;
}

/** Unduh berkas dari API (PDF/CSV) dengan token, lalu simpan lewat browser. */
async function unduh(path, params, cadangan) {
    const token = localStorage.getItem('token');
    const q = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null));
    const res = await fetch(`${API}${path}?${q}`, { headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf,text/csv,*/*' } });
    if (!res.ok) throw new Error('Gagal membuat laporan. Periksa periode yang dipilih.');
    const m = /filename="?([^";]+)"?/.exec(res.headers.get('Content-Disposition') || '');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(await res.blob());
    a.download = m ? m[1] : cadangan;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}

const cetakNota = (id) => cetakHtml(`/penjualan/${id}/nota`, 'nota');
const cetakRekap = (id) => cetakHtml(`/shift/${id}/rekap`, 'rekap shift');

/** Memuat HTML cetak dari API (dengan token) ke iframe tersembunyi lalu membuka dialog cetak. */
async function cetakHtml(path, nama) {
    const token = localStorage.getItem('token');
    const res = await fetch(API + path, { headers: { Authorization: 'Bearer ' + token } });
    if (!res.ok) throw new Error('Gagal memuat ' + nama);
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0';
    document.body.appendChild(iframe);
    iframe.srcdoc = await res.text();
    iframe.onload = () => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => iframe.remove(), 2000);
    };
}

function formatStrukWa(p) {
    const toko = '☕ KEDAI KOPI';
    const lines = [];
    lines.push(`*${toko}*`);
    lines.push('================================');
    lines.push(`No. Nota : ${p.no_nota || '-'}`);
    lines.push(`Waktu    : ${waktu(p.tanggal)}`);
    if (p.nomor_antrean) lines.push(`Antrean  : #${p.nomor_antrean}`);
    if (p.pembeli?.nama) lines.push(`Pembeli  : ${p.pembeli.nama}`);
    if (p.meja) lines.push(`Meja     : ${p.meja}`);
    lines.push(`Kasir    : ${p.user?.name || '-'}`);
    lines.push('--------------------------------');
    lines.push('*PESANAN:*');
    (p.detail || []).forEach((d) => {
        const itemNama = d.produk?.nama || '-';
        const opsi = (d.opsi || []).filter((o) => !(o.bawaan && !Number(o.harga))).map((o) => o.nama).join(', ');
        lines.push(`• ${itemNama}${opsi ? ' (' + opsi + ')' : ''}`);
        lines.push(`  ${angka(d.qty)} x ${rupiah(d.harga_jual)} = ${rupiah(d.subtotal)}`);
    });
    lines.push('--------------------------------');
    lines.push(`Subtotal     : ${rupiah(p.subtotal)}`);
    if (Number(p.diskon) > 0) lines.push(`Diskon Promo : -${rupiah(p.diskon)}`);
    if (Number(p.diskon_poin) > 0) lines.push(`Diskon Poin  : -${rupiah(p.diskon_poin)}`);
    if (Number(p.service) > 0) lines.push(`Service      : ${rupiah(p.service)}`);
    if (Number(p.pajak) > 0) lines.push(`Pajak        : ${rupiah(p.pajak)}`);
    lines.push(`*TOTAL        : ${rupiah(p.total)}*`);
    lines.push(`Metode       : ${label(p.metode)}`);
    if (p.metode === 'tunai') {
        lines.push(`Bayar        : ${rupiah(p.bayar)}`);
        lines.push(`Kembalian    : ${rupiah(p.kembalian)}`);
    }
    if (p.poin_didapat > 0) lines.push(`*Poin Masuk  : +${p.poin_didapat} poin*`);
    lines.push('================================');
    lines.push('Terima kasih atas kunjungannya!');
    lines.push('Silakan datang kembali 🙏');
    return lines.join('\n');
}

function kirimWa(p, nomor) {
    const raw = nomor || p.pembeli?.telepon || '';
    let clean = raw.replace(/\D/g, '');
    if (clean.startsWith('0')) clean = '62' + clean.slice(1);
    if (!clean.startsWith('62') && clean.length >= 8) clean = '62' + clean;
    const pesan = formatStrukWa(p);
    const url = clean ? `https://wa.me/${clean}?text=${encodeURIComponent(pesan)}` : `https://wa.me/?text=${encodeURIComponent(pesan)}`;
    window.open(url, '_blank');
}

async function kirimStrukWa(p, nomor) {
    if (!p) return;
    const target = nomor || p.pembeli?.telepon;
    try {
        const r = await api('POST', `/penjualan/${p.id}/kirim-wa`, { telepon: target });
        toast('Struk digital terkirim via WhatsApp Fonnte! 📲');
    } catch (e) {
        toast('Membuka WhatsApp web/app...');
        kirimWa(p, nomor);
    }
}

const ICONS = {
    star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    wa: '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    cart: '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    truck: '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/>',
    building: '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M9 13h1M14 9h1M14 13h1M10 21v-4h4v4"/>',
    package: '<path d="M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8"/>',
    tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1"/>',
    outbox: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
    chart: '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    minus: '<path d="M5 12h14"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    printer: '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
    coffee: '<path d="M17 8h1a4 4 0 0 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 2v3M10 2v3M14 2v3"/>',
    bean: '<path d="M12 2.5c4.6 0 7.5 4.2 7.5 9.5s-2.9 9.5-7.5 9.5S4.5 17.3 4.5 12 7.4 2.5 12 2.5z" transform="rotate(30 12 12)"/><path d="M8.2 5.2c3.4 2.4 3.6 5.2 1.4 8.2-2.2 3-1.6 5.2 1.2 7.3" />',
    glass: '<path d="M6 3h12l-1.5 16.2A2 2 0 0 1 14.5 21h-5a2 2 0 0 1-2-1.8z"/><path d="M6.6 9h10.8M13 3l1.6-2"/>',
    plate: '<ellipse cx="12" cy="15" rx="10" ry="4.5"/><path d="M6 14c0-4 2.6-7 6-7s6 3 6 7"/>',
    cake: '<path d="M3 20h18M4 20v-7l16-3v10M4 15c3 1.6 5 1.6 8 0s5-1.6 8 0"/><circle cx="8" cy="8" r="1.4"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1z"/>',
    trendUp: '<path d="m22 7-8.5 8.5-5-5L2 17M16 7h6v6"/>',
    home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    chevL: '<path d="m15 18-6-6 6-6"/>',
    chevR: '<path d="m9 18 6-6-6-6"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    alert: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
    check: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    receipt: '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2V2l-3 2-3-2-3 2-3-2zM8 8h8M8 12h8M8 16h5"/>',
    banknote: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
    wallet: '<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5M16 13h2"/>',
    bag: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
    sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ticket: '<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 1 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zM14 5v14" stroke-dasharray="2 3"/>',
    clipboard: '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 12h6M9 16h4"/>',
    barchart: '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    key: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8M17 6l3 3M14 9l2 2"/>',
    ban: '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
    scroll: '<path d="M8 21h12a2 2 0 0 0 2-2v-2H10v2a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v3h4M19 17V5a2 2 0 0 0-2-2H4"/>',
    flask: '<path d="M9 3h6M10 3v6L4.5 19a2 2 0 0 0 1.8 3h11.4a2 2 0 0 0 1.8-3L14 9V3"/><path d="M7.5 15h9"/>',
};
const icon = (n) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${ICONS[n] || ''}</svg>`;
/** Ringkasan pilihan kustomisasi pada baris pesanan: hanya yang menyimpang dari bawaan (mis. "Large, Extra shot"). */
const opsiLabel = (d) => (d.opsi || []).filter((o) => !(o.bawaan && !Number(o.harga))).map((o) => o.nama).join(', ');
const isNum = (c) => c.fmt === 'rupiah' || c.fmt === 'num';

/* Ilustrasi cadangan (dipakai bila produk belum punya foto) berdasarkan nama kategori. */
const katArt = (nama = '') => {
    const n = String(nama).toLowerCase();
    if (n.includes('non')) return { icon: 'glass', tone: 'sage' };
    if (n.includes('kopi susu')) return { icon: 'coffee', tone: 'caramel' };
    if (n.includes('kopi') || n.includes('brew')) return { icon: 'coffee', tone: 'slate' };
    if (n.includes('makanan')) return { icon: 'plate', tone: 'cream' };
    if (n.includes('dessert')) return { icon: 'cake', tone: 'terra' };
    return { icon: 'bean', tone: 'default' };
};
const STOK_MENIPIS = 5;
const stokState = (s, min) => (Number(s) <= 0 ? 'habis' : Number(s) <= (min !== undefined && min !== null ? Number(min) : STOK_MENIPIS) ? 'menipis' : 'aman');
const hariIsoIni = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/* Metadata tampilan menu: ikon, grup, dan deskripsi halaman. */
const META = {
    'menu-pelanggan': { icon: 'coffee', group: 'Pesan', desc: 'Pilih menu favoritmu, bayar online, lalu ambil di kedai' },
    'pesanan-saya': { icon: 'receipt', group: 'Pesan', desc: 'Pantau status dan riwayat pesananmu' },
    dashboard: { icon: 'home', group: 'Ringkasan', desc: 'Ringkasan aktivitas kedai, tren penjualan, dan peringatan stok' },
    penjualan: { icon: 'cart', group: 'Penjualan', desc: 'Kasir dan riwayat nota penjualan' },
    pembeli: { icon: 'users', group: 'Penjualan', desc: 'Data pembeli / pelanggan kedai' },
    'pesanan-online': { icon: 'bag', group: 'Penjualan', desc: 'Antrean pesanan online yang sudah dibayar dan perlu disiapkan' },
    shift: { icon: 'clock', group: 'Penjualan', desc: 'Riwayat buka/tutup shift kasir beserta selisih kas' },
    voucher: { icon: 'ticket', group: 'Penjualan', desc: 'Kode promo diskon untuk kasir dan pesanan online, lengkap dengan kuota dan masa berlaku' },
    pembelian: { icon: 'truck', group: 'Pembelian', desc: 'Pembelian produk dari distributor, stok bertambah otomatis' },
    distributor: { icon: 'building', group: 'Pembelian', desc: 'Pemasok / distributor produk dan bahan baku' },
    'bahan-baku': { icon: 'flask', group: 'Pembelian', desc: 'Biji kopi, susu, sirup, dan bahan baku lainnya' },
    produk: { icon: 'package', group: 'Manajemen Stok', desc: 'Daftar produk, harga modal, harga jual, dan stok saat ini' },
    'kategori-produk': { icon: 'tag', group: 'Manajemen Stok', desc: 'Pengelompokan kategori produk' },
    'barang-keluar': { icon: 'outbox', group: 'Manajemen Stok', desc: 'Barang keluar non-penjualan: rusak, expired, diberikan' },
    'riwayat-stok': { icon: 'history', group: 'Manajemen Stok', desc: 'Log semua pergerakan stok: barang terjual, pembelian, dan barang keluar' },
    opname: { icon: 'clipboard', group: 'Manajemen Stok', desc: 'Cocokkan stok di sistem dengan hitungan fisik; selisih dicatat sebagai koreksi' },
    'opsi-menu': { icon: 'sliders', group: 'Manajemen Stok', desc: 'Ukuran, gula, es, dan tambahan yang bisa dipilih pelanggan, lengkap dengan harga tambahan' },
    'beban-operasional': { icon: 'banknote', group: 'Laporan', desc: 'Beban operasional (listrik, air, gaji, sewa) untuk perhitungan laba bersih' },
    laporan: { icon: 'chart', group: 'Laporan', desc: 'Laba/rugi dan kontribusi laba tiap kategori produk' },
    'laporan-penjualan': { icon: 'barchart', group: 'Laporan', desc: 'Tren harian, jam ramai, menu terlaris, kinerja kasir, dan metode bayar' },
    ulasan: { icon: 'star', group: 'Penjualan', desc: 'Rating bintang dan ulasan menu dari pelanggan' },
    'wa-blast': { icon: 'wa', group: 'Penjualan', desc: 'Broadcast promo & info kedai ke nomor WhatsApp pelanggan via Fonnte Gateway' },
    pengguna: { icon: 'shield', group: 'Pengaturan', desc: 'Akun kasir, admin, dan owner. Akun yang keluar cukup dinonaktifkan' },
    pengaturan: { icon: 'gear', group: 'Pengaturan', desc: 'Identitas kedai di nota, pajak, dan service charge' },
    'audit-log': { icon: 'scroll', group: 'Pengaturan', desc: 'Jejak perubahan penting: void, harga, akun, voucher, opname, pengaturan' },
};

const ADMIN = ['admin', 'owner'];
const STATUS_LABEL = {
    menunggu_pembayaran: 'Menunggu pembayaran', dibayar: 'Dibayar', diproses: 'Sedang dibuat', siap: 'Siap diambil',
    selesai: 'Selesai', dibatalkan: 'Dibatalkan', kedaluwarsa: 'Kedaluwarsa', perlu_refund: 'Perlu refund',
};
const LANGKAH = [['dibayar', 'Dibayar'], ['diproses', 'Dibuat'], ['siap', 'Siap diambil'], ['selesai', 'Selesai']];
const statusLabel = (s) => STATUS_LABEL[s] || label(s);
const ALASAN = ['rusak', 'expired', 'diberikan', 'lainnya'].map((v) => ({ v, l: label(v) }));
const SATUAN_MENU = ['cup', 'pcs', 'gram'].map((v) => ({ v, l: v }));
const SATUAN_BAHAN = ['gram', 'ml', 'pcs', 'kg', 'liter', 'pak'].map((v) => ({ v, l: v }));
const KONTAK = [
    { key: 'nama', label: 'Nama', type: 'text' },
    { key: 'telepon', label: 'Telepon', type: 'text', optional: true },
    { key: 'alamat', label: 'Alamat', type: 'textarea', optional: true },
];

/*
 * Konfigurasi halaman. `read` = role yang melihat menu, `write` = role yang boleh mengubah.
 * Owner selalu punya akses penuh (lihat menu() dan canWrite()). Kasir hanya melihat halaman kasir.
 */
const PAGES = {
    // Halaman khusus pelanggan (`only` = tidak ditampilkan ke role lain, termasuk owner).
    'menu-pelanggan': { title: 'Menu', type: 'katalog', read: ['pelanggan'], only: ['pelanggan'], write: [] },
    'pesanan-saya': { title: 'Pesanan Saya', type: 'pesanansaya', read: ['pelanggan'], only: ['pelanggan'], write: [] },
    dashboard: { title: 'Dashboard', type: 'dashboard', read: ADMIN, write: [] },
    penjualan: { title: 'Penjualan', type: 'penjualan', endpoint: '/penjualan', read: ['kasir'], write: ['kasir'] },
    pembeli: {
        title: 'Data Pembeli', type: 'crud', endpoint: '/pembeli', read: ['kasir', 'admin'], write: ['kasir', 'admin'], search: true,
        columns: [{ key: 'nama', label: 'Nama' }, { key: 'telepon', label: 'Telepon' }, { key: 'poin', label: 'Poin Member', fmt: 'num' }, { key: 'alamat', label: 'Alamat' }],
        fields: KONTAK,
    },
    'wa-blast': { title: 'WhatsApp Blast', type: 'wablast', read: ['owner'], write: ['owner'] },
    'pesanan-online': { title: 'Pesanan Online', type: 'pesananstaff', read: ['kasir', 'admin'], write: [] },
    shift: { title: 'Riwayat Shift', type: 'shiftlog', read: ['admin'], write: [] },
    voucher: {
        title: 'Voucher & Promo', type: 'crud', endpoint: '/voucher', read: ['admin'], write: ['owner'], search: true, noDelete: true,
        note: 'Voucher tidak dihapus supaya riwayat diskon tetap utuh. Nonaktifkan bila promo selesai.',
        columns: [
            { key: 'kode', label: 'Kode' }, { key: 'nama', label: 'Nama promo' },
            { key: 'potongan', label: 'Potongan', get: (r) => (r.tipe === 'persen' ? `${angka(r.nilai)}%` + (r.maks_diskon ? ` (maks ${rupiah(r.maks_diskon)})` : '') : rupiah(r.nilai)) },
            { key: 'min_belanja', label: 'Min. belanja', fmt: 'rupiah' },
            { key: 'pakai', label: 'Terpakai', get: (r) => `${r.terpakai}${r.kuota ? ' / ' + r.kuota : ''}` },
            { key: 'periode', label: 'Berlaku', get: (r) => (r.berlaku_dari || r.berlaku_sampai ? `${r.berlaku_dari ? tanggal(r.berlaku_dari) : '…'} – ${r.berlaku_sampai ? tanggal(r.berlaku_sampai) : '…'}` : 'Tanpa batas') },
            { key: 'untuk', label: 'Untuk', fmt: 'label' },
            { key: 'status', label: 'Status', get: (r) => (r.aktif ? 'Aktif' : 'Nonaktif') },
        ],
        fields: [
            { key: 'kode', label: 'Kode voucher (huruf/angka)', type: 'text' },
            { key: 'nama', label: 'Nama promo', type: 'text' },
            { key: 'tipe', label: 'Jenis potongan', type: 'select', choices: [{ v: 'persen', l: 'Persen (%)' }, { v: 'nominal', l: 'Nominal (Rp)' }] },
            { key: 'nilai', label: 'Nilai potongan (% atau Rp)', type: 'number' },
            { key: 'maks_diskon', label: 'Maksimal potongan Rp (khusus persen)', type: 'number', optional: true },
            { key: 'min_belanja', label: 'Minimal belanja Rp', type: 'number', optional: true },
            { key: 'kuota', label: 'Kuota pemakaian', type: 'number', optional: true },
            { key: 'berlaku_dari', label: 'Berlaku dari', type: 'date', optional: true },
            { key: 'berlaku_sampai', label: 'Berlaku sampai', type: 'date', optional: true },
            { key: 'untuk', label: 'Bisa dipakai di', type: 'select', choices: [{ v: 'semua', l: 'Kasir dan online' }, { v: 'kasir', l: 'Kasir saja' }, { v: 'online', l: 'Pesanan online saja' }] },
            { key: 'aktif', label: 'Status', type: 'select', choices: [{ v: 1, l: 'Aktif' }, { v: 0, l: 'Nonaktif' }] },
        ],
    },
    pembelian: { title: 'Pembelian Produk', type: 'pembelian', endpoint: '/pembelian', read: ['admin'], write: ['admin'] },
    distributor: {
        title: 'Data Distributor', type: 'crud', endpoint: '/distributor', read: ['admin'], write: ['admin'], search: true,
        columns: [{ key: 'nama', label: 'Nama' }, { key: 'telepon', label: 'Telepon' }, { key: 'alamat', label: 'Alamat' }],
        fields: KONTAK,
    },
    'bahan-baku': {
        title: 'Bahan Baku', type: 'crud', endpoint: '/bahan-baku', read: ['admin'], write: ['admin'], search: true,
        note: 'Stok bahan baku bertambah otomatis saat pembelian disimpan di menu Pembelian Produk.',
        columns: [{ key: 'nama', label: 'Nama' }, { key: 'satuan', label: 'Satuan' }, { key: 'stok', label: 'Stok', fmt: 'num' }, { key: 'harga_terakhir', label: 'Harga Terakhir / Satuan', fmt: 'rupiah' }],
        fields: [{ key: 'nama', label: 'Nama bahan', type: 'text' }, { key: 'satuan', label: 'Satuan', type: 'select', choices: SATUAN_BAHAN }],
    },
    produk: {
        title: 'Data Produk', type: 'crud', endpoint: '/produk', read: ['admin'], write: ['admin'], search: true, cards: true, upload: true, per_page: 12,
        softLabel: 'Nonaktifkan', softConfirm: 'Nonaktifkan produk ini? Produk hilang dari kasir, riwayat transaksi lama tetap tersimpan.',
        note: 'Menu beresep memotong stok bahan baku otomatis saat terjual, dan stok menunya dihitung dari bahan. Produk tanpa resep memakai stoknya sendiri.',
        chips: { key: 'kategori_id', from: '/kategori-produk', all: 'Semua kategori' },
        filters: [{ key: 'stok_status', label: 'Semua stok', choices: [{ v: 'menipis', l: 'Stok menipis' }, { v: 'habis', l: 'Stok habis' }] }],
        nonaktif: true,
        columns: [
            { key: 'sku', label: 'SKU' }, { key: 'nama', label: 'Nama' }, { key: 'kategori.nama', label: 'Kategori' }, { key: 'satuan', label: 'Satuan' },
            { key: 'harga_beli', label: 'Harga Modal', fmt: 'rupiah' }, { key: 'harga_jual', label: 'Harga Jual', fmt: 'rupiah' },
            { key: 'stok', label: 'Stok', fmt: 'num' },
            { key: 'stok_minimum', label: 'Stok Min', fmt: 'num' },
        ],
        fields: [
            { key: 'kategori_id', label: 'Kategori', type: 'select', from: '/kategori-produk', optLabel: 'nama' },
            { key: 'sku', label: 'Kode SKU Produk (otomatis bila kosong)', type: 'text', optional: true },
            { key: 'nama', label: 'Nama produk', type: 'text' },
            { key: 'satuan', label: 'Satuan', type: 'select', choices: SATUAN_MENU },
            { key: 'harga_beli', label: 'Harga modal (per satuan)', type: 'number' },
            { key: 'harga_jual', label: 'Harga jual', type: 'number' },
            { key: 'stok', label: 'Stok awal', type: 'number', createOnly: true, optional: true },
            { key: 'stok_minimum', label: 'Batas stok minimum (peringatan restok)', type: 'number', optional: true, hint: 'Batas aman stok produk ini (default: 5). Peringatan muncul saat stok mencapai atau di bawah angka ini.' },
            { key: 'gambar', label: 'Foto produk', type: 'file', optional: true, hint: 'JPG, PNG, atau WEBP, maks 2 MB. Kosongkan untuk memakai ilustrasi bawaan.' },
            { key: 'resep', label: 'Resep (bahan per 1 porsi)', type: 'resep', optional: true },
            { key: 'opsi_grup_ids', label: 'Kustomisasi yang tersedia', type: 'opsi', optional: true },
        ],
    },
    'kategori-produk': {
        title: 'Kategori Produk', type: 'crud', endpoint: '/kategori-produk', read: ['admin'], write: ['admin'], search: true,
        columns: [{ key: 'kode', label: 'Kode Prefix' }, { key: 'nama', label: 'Nama' }],
        fields: [{ key: 'kode', label: 'Kode Prefix Nota / SKU (mis. KF, MK, NK)', type: 'text', optional: true }, { key: 'nama', label: 'Nama', type: 'text' }],
    },
    'barang-keluar': {
        title: 'Barang Keluar', type: 'crud', endpoint: '/barang-keluar', read: ['admin'], write: ['admin'], immutable: true,
        columns: [
            { key: 'tanggal', label: 'Tanggal', fmt: 'datetime' }, { key: 'produk.nama', label: 'Produk' },
            { key: 'qty', label: 'Qty', fmt: 'num' }, { key: 'alasan', label: 'Alasan', fmt: 'label' },
            { key: 'keterangan', label: 'Keterangan' }, { key: 'user.name', label: 'Petugas' },
        ],
        filters: [{ key: 'alasan', label: 'Semua alasan', choices: ALASAN }],
        fields: [
            { key: 'produk_id', label: 'Produk', type: 'select', from: '/produk', optLabel: 'nama' },
            { key: 'qty', label: 'Jumlah', type: 'number' },
            { key: 'alasan', label: 'Alasan', type: 'select', choices: ALASAN },
            { key: 'keterangan', label: 'Keterangan', type: 'textarea', optional: true },
        ],
    },
    'riwayat-stok': {
        title: 'Riwayat Stok', type: 'crud', endpoint: '/riwayat-stok', read: ['admin'], write: [], readonly: true, immutable: true, search: true,
        columns: [
            { key: 'created_at', label: 'Waktu', fmt: 'datetime' }, { key: 'produk.nama|bahan_baku.nama', label: 'Item' },
            { key: 'tipe', label: 'Tipe', fmt: 'label' }, { key: 'qty', label: 'Qty', fmt: 'num', signed: true },
            { key: 'stok_sebelum', label: 'Sebelum', fmt: 'num' }, { key: 'stok_sesudah', label: 'Sesudah', fmt: 'num' },
            { key: 'user.name', label: 'Petugas' },
        ],
        filters: [{ key: 'tipe', label: 'Semua tipe', choices: [{ v: 'stok_awal', l: 'Stok awal' }, { v: 'penjualan', l: 'Penjualan' }, { v: 'pembelian', l: 'Pembelian' }, { v: 'barang_keluar', l: 'Barang keluar' }, { v: 'pesanan_online', l: 'Pesanan online' }, { v: 'pesanan_batal', l: 'Pesanan batal' }, { v: 'void_penjualan', l: 'Void penjualan' }, { v: 'opname', l: 'Opname' }] }],
    },
    opname: { title: 'Stok Opname', type: 'opname', read: ['admin'], write: ['admin'] },
    'opsi-menu': { title: 'Kustomisasi Menu', type: 'opsimenu', read: ['admin'], write: ['admin'] },
    'beban-operasional': {
        title: 'Beban Operasional', type: 'crud', endpoint: '/beban-operasional', read: ADMIN, write: ['owner'], createRole: ['admin', 'owner'], search: true, upload: true,
        columns: [
            { key: 'no_voucher', label: 'No. Voucher' },
            { key: 'tanggal', label: 'Tanggal', fmt: 'date' },
            { key: 'kode_akun', label: 'COA' },
            { key: 'kategori', label: 'Kategori', fmt: 'label' },
            { key: 'nama', label: 'Nama Beban' },
            { key: 'jumlah', label: 'Nominal', fmt: 'rupiah' },
            { key: 'bukti_url', label: 'Bukti', get: (r) => (r.bukti_url ? `<a href="${r.bukti_url}" target="_blank" class="btn btn-sm btn-link" style="padding:2px 6px">Lihat</a>` : '-') },
            { key: 'user.name', label: 'Petugas' },
        ],
        filters: [{ key: 'kategori', label: 'Semua kategori', choices: [
            { v: 'listrik', l: 'Listrik' }, { v: 'air', l: 'Air' }, { v: 'gaji', l: 'Gaji' },
            { v: 'sewa', l: 'Sewa' }, { v: 'internet', l: 'Internet' }, { v: 'perawatan', l: 'Perawatan' }, { v: 'lainnya', l: 'Lainnya' }
        ]}],
        fields: [
            { key: 'tanggal', label: 'Tanggal', type: 'date', default: () => hariIsoIni() },
            { key: 'kategori', label: 'Kategori', type: 'select', choices: [
                { v: 'listrik', l: 'Listrik' }, { v: 'air', l: 'Air' }, { v: 'gaji', l: 'Gaji' },
                { v: 'sewa', l: 'Sewa' }, { v: 'internet', l: 'Internet' }, { v: 'perawatan', l: 'Perawatan' }, { v: 'lainnya', l: 'Lainnya' }
            ]},
            { key: 'kode_akun', label: 'Kode Akun COA (otomatis bila kosong)', type: 'text', optional: true },
            { key: 'nama', label: 'Nama beban (mis. Listrik PLN, Gaji Barista, Sewa Ruko)', type: 'text' },
            { key: 'jumlah', label: 'Nominal (Rp)', type: 'number' },
            { key: 'bukti_transaksi', label: 'Foto Bukti / Struk (JPG, PNG, PDF maks 2MB)', type: 'file', optional: true },
            { key: 'keterangan', label: 'Keterangan', type: 'textarea', optional: true },
        ],
    },
    ulasan: {
        title: 'Ulasan Pelanggan', type: 'crud', endpoint: '/ulasan', read: ADMIN, write: [], readonly: true, immutable: true, search: true,
        columns: [
            { key: 'created_at', label: 'Waktu', fmt: 'datetime' },
            { key: 'produk.nama', label: 'Menu' },
            { key: 'bintang', label: 'Rating', get: (r) => '⭐'.repeat(r.bintang || 5) + ` (${r.bintang}/5)` },
            { key: 'komentar', label: 'Ulasan Pelanggan' },
            { key: 'user.name', label: 'Pelanggan' },
        ],
    },
    laporan: { title: 'Laporan Laba/Rugi', type: 'laporan', read: [], write: [] },
    'laporan-penjualan': { title: 'Laporan Penjualan', type: 'laporanjual', read: [], write: [] },
    pengguna: { title: 'Pengguna', type: 'pengguna', read: [], write: [] },
    pengaturan: { title: 'Pengaturan Toko', type: 'pengaturan', read: [], write: [] },
    'audit-log': {
        title: 'Log Aktivitas', type: 'crud', endpoint: '/audit-log', read: [], write: [], readonly: true, immutable: true, search: true, per_page: 25,
        columns: [
            { key: 'created_at', label: 'Waktu', fmt: 'datetime' }, { key: 'user.name', label: 'Pelaku' },
            { key: 'aksi', label: 'Aksi', fmt: 'label' }, { key: 'objek', label: 'Objek' },
            { key: 'user_agent', label: 'Perangkat', get: (r) => (r.user_agent ? (r.user_agent.length > 25 ? r.user_agent.slice(0, 25) + '…' : r.user_agent) : '-') },
            { key: 'ringkas', label: 'Rincian', get: (r) => (r.detail ? JSON.stringify(r.detail).slice(0, 110) : '') },
        ],
        filters: [{ key: 'aksi', label: 'Semua aksi', choices: ['auth', 'beban', 'pembelian', 'penjualan', 'produk', 'pengguna', 'voucher', 'pengaturan', 'opname', 'shift', 'akun', 'laporan'].map((v) => ({ v, l: label(v) })) }],
    },
};

document.addEventListener('alpine:init', () => {
    Alpine.store('auth', { user: null, get role() { return this.user?.role; } });
    Alpine.store('toast', {
        items: [],
        push(msg, type = 'ok') {
            const id = Date.now() + Math.random();
            this.items.push({ id, msg, type });
            setTimeout(() => { this.items = this.items.filter((i) => i.id !== id); }, 3800);
        },
    });
    const auth = () => Alpine.store('auth');
    const toast = (m, t) => Alpine.store('toast').push(m, t);
    const bisaLihat = (p) => (p.only ? p.only.includes(auth().role) : auth().role === 'owner' || p.read.includes(auth().role));

    /* Lembar pilihan kustomisasi (ukuran, gula, es, tambahan) yang dipakai kasir dan pelanggan. */
    Alpine.store('opsi', {
        produk: null, pilih: {}, cb: null, err: '',
        buka(p, cb) {
            this.produk = p; this.cb = cb; this.err = '';
            const m = {};
            p.opsi_grup.forEach((g) => { m[g.id] = g.pilihan.filter((x) => x.bawaan).map((x) => x.id).slice(0, g.banyak ? 99 : 1); });
            this.pilih = m;
        },
        tutup() { this.produk = null; this.cb = null; },
        ada(g, x) { return (this.pilih[g.id] || []).includes(x.id); },
        toggle(g, x) {
            let a = [...(this.pilih[g.id] || [])];
            if (g.banyak) a = a.includes(x.id) ? a.filter((i) => i !== x.id) : [...a, x.id];
            else a = a.includes(x.id) && !g.wajib ? [] : [x.id];
            this.pilih = { ...this.pilih, [g.id]: a };
        },
        terpilih() { return this.produk.opsi_grup.flatMap((g) => g.pilihan.filter((x) => this.ada(g, x))); },
        harga() { return Number(this.produk.harga_jual) + this.terpilih().reduce((s, x) => s + Number(x.tambahan_harga), 0); },
        /* Ringkasan hanya pilihan yang menyimpang dari bawaan, mis. "Large, Extra shot". */
        label() { return this.terpilih().filter((x) => !(x.bawaan && Number(x.tambahan_harga) === 0)).map((x) => x.nama).join(', '); },
        kurang() { return this.produk.opsi_grup.find((g) => g.wajib && !(this.pilih[g.id] || []).length); },
        simpan() {
            const k = this.kurang();
            if (k) { this.err = `Pilih ${k.nama} dulu.`; return; }
            const cb = this.cb;
            cb({ opsi: this.terpilih().map((x) => x.id), harga: this.harga(), label: this.label() });
            this.tutup();
        },
    });

    /*
     * Rincian harga: subtotal - diskon voucher + service + pajak. Diskon dihitung server (voucher/cek) agar sama persis
     * dengan yang disimpan; service dan pajak memakai persentase dari Pengaturan.
     */
    const harga = () => ({
        peng: { pajak_persen: 0, service_persen: 0 }, kode: '', vinfo: null, verr: '', vbusy: false,
        async muatPeng() { try { this.peng = await api('GET', '/pengaturan'); } catch { /* pakai bawaan 0% */ } },
        subtotal() { return this.items.reduce((s, i) => s + (Number(i.qty) || 0) * Number(i.harga || 0), 0); },
        rincian() {
            const sub = this.subtotal();
            if (this.vinfo && this.vinfo.subtotal === sub) return this.vinfo;
            const sv = Math.round((sub * Number(this.peng.service_persen)) / 100);
            const pj = Math.round(((sub + sv) * Number(this.peng.pajak_persen)) / 100);
            return { subtotal: sub, diskon: 0, service: sv, pajak: pj, total: sub + sv + pj };
        },
        total() { return this.rincian().total; },
        adaTambahan() { const r = this.rincian(); return r.diskon > 0 || r.service > 0 || r.pajak > 0; },
        async pakaiVoucher() {
            this.verr = '';
            if (!this.kode.trim()) { this.vinfo = null; return; }
            if (this.subtotal() <= 0) { this.verr = 'Tambahkan menu dulu.'; return; }
            this.vbusy = true;
            try { this.vinfo = await api('POST', '/voucher/cek', { kode: this.kode.trim(), subtotal: this.subtotal() }); this.kode = this.vinfo.voucher_kode; }
            catch (e) { this.vinfo = null; this.verr = e.message; }
            this.vbusy = false;
        },
        lepasVoucher() { this.kode = ''; this.vinfo = null; this.verr = ''; },
        /* Isi keranjang berubah: hitung ulang potongan voucher yang sedang dipakai. */
        segarkanVoucher() { if (this.vinfo || this.verr) { if (this.subtotal() > 0 && this.kode) this.pakaiVoucher(); else this.lepasVoucher(); } },
    });

    /* Logika keranjang yang sama untuk kasir (POS) dan pelanggan. Baris dibedakan oleh menu + kombinasi pilihan. */
    const keranjang = () => ({
        sig: (p, opsi) => p.id + ':' + [...opsi].sort((a, b) => a - b).join(','),
        qtyProduk(p) { return this.items.filter((i) => i.produk.id === p.id).reduce((s, i) => s + Number(i.qty), 0); },
        qtyDi(p) { return this.qtyProduk(p); },
        tambah(p) {
            if (Number(p.stok) <= 0) return;
            if (p.opsi_grup?.length) { Alpine.store('opsi').buka(p, (r) => this.tambahBaris(p, r)); return; }
            this.tambahBaris(p, { opsi: [], harga: p.harga_jual, label: '' });
        },
        tambahBaris(p, r) {
            if (this.qtyProduk(p) + 1 > Number(p.stok)) { toast(`Stok ${p.nama} hanya ${angka(p.stok)} ${p.satuan}`, 'err'); return; }
            const sig = this.sig(p, r.opsi);
            const ada = this.items.find((i) => i.sig === sig);
            if (ada) ada.qty = Number(ada.qty) + 1;
            else this.items.push({ sig, produk: p, qty: 1, harga: r.harga, opsi: r.opsi, label: r.label });
        },
        ubah(i, d) {
            const it = this.items[i];
            const q = Math.round((Number(it.qty) + d) * 100) / 100;
            if (q <= 0) { this.items.splice(i, 1); return; }
            if (this.qtyProduk(it.produk) - Number(it.qty) + q > Number(it.produk.stok)) { toast(`Stok ${it.produk.nama} hanya ${angka(it.produk.stok)}`, 'err'); return; }
            it.qty = q;
        },
        /* Tombol +/- pada kartu menu: "-" mengurangi baris terakhir menu itu. */
        ubahMenu(p, d) {
            if (d > 0) return this.tambah(p);
            const i = this.items.map((x) => x.produk.id).lastIndexOf(p.id);
            if (i >= 0) this.ubah(i, -1);
        },
        adaOpsi: (p) => !!p.opsi_grup?.length,
        muatBaris() { return this.items.map((i) => ({ produk_id: i.produk.id, qty: i.qty, opsi: i.opsi })); },
    });

    /* Modal pembayaran bersama (dipakai setelah memesan dan dari "Pesanan Saya"). */
    Alpine.store('bayar', {
        pesanan: null, busy: false, err: '', now: Date.now(), t: null, poll: null,
        buka(p) {
            this.tutupDiam();
            this.pesanan = p; this.err = ''; this.now = Date.now();
            this.t = setInterval(() => { this.now = Date.now(); }, 1000);
            this.poll = setInterval(() => this.cek(), 4000);
        },
        tutupDiam() { clearInterval(this.t); clearInterval(this.poll); this.pesanan = null; },
        tutup() { this.tutupDiam(); window.dispatchEvent(new Event('pesanan-berubah')); },
        sisa() {
            if (!this.pesanan) return '';
            const s = Math.max(0, Math.floor((new Date(this.pesanan.kedaluwarsa_pada) - this.now) / 1000));
            return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        },
        habis() { return !!this.pesanan && new Date(this.pesanan.kedaluwarsa_pada) < this.now; },
        simulator() { return this.pesanan?.pembayaran_driver === 'simulator'; },
        selesaiBayar() { toast('Pembayaran diterima. Pesananmu diteruskan ke dapur.'); this.tutup(); location.hash = '#pesanan-saya'; },
        /* Memeriksa status ke server (dipanggil berkala selama pelanggan membayar di tab lain). */
        async cek() {
            if (!this.pesanan) return;
            try {
                const r = await api('GET', '/pelanggan/pesanan/' + this.pesanan.kode);
                if (!this.pesanan || r.status === 'menunggu_pembayaran') return;
                if (r.status === 'dibayar') return this.selesaiBayar();
                toast('Pesanan ' + statusLabel(r.status).toLowerCase(), 'err');
                this.tutup();
            } catch { /* jaringan putus: coba lagi pada putaran berikutnya */ }
        },
        async simulasi() {
            this.busy = true; this.err = '';
            try { await api('POST', `/pelanggan/pesanan/${this.pesanan.kode}/simulasi-bayar`); this.selesaiBayar(); }
            catch (e) { this.err = e.message; }
            this.busy = false;
        },
        async batalkan() {
            if (!confirm('Batalkan pesanan ini?')) return;
            this.busy = true;
            try { await api('POST', `/pelanggan/pesanan/${this.pesanan.kode}/batal`); toast('Pesanan dibatalkan'); this.tutup(); }
            catch (e) { this.err = e.message; }
            this.busy = false;
        },
    });

    Alpine.data('shell', () => ({
        ready: false, page: null, menuOpen: false, email: '', password: '', loginError: '', busy: false,
        mode: 'masuk', nama: '', telepon: '', antrean: null, timer: null, statusLama: {},
        otp: '', sandiBaru: '', infoMsg: '',
        async init() {
            if (localStorage.getItem('token')) {
                // Tetap masuk: token hanya dibuang bila server menolaknya (401, ditangani api()). Server mati atau
                // jaringan putus tidak boleh membuat pengguna keluar, jadi coba lagi beberapa kali.
                for (let i = 0; i < 4 && !auth().user; i++) {
                    try { auth().user = await api('GET', '/me'); }
                    catch (e) { if (e.status) break; await new Promise((r) => setTimeout(r, 1500)); }
                }
                if (!auth().user) this.loginError = 'Tidak bisa terhubung ke server. Sesimu tetap tersimpan, muat ulang halaman setelah koneksi pulih.';
            }
            this.ready = true;
            window.addEventListener('hashchange', () => this.route());
            this.route();
            this.mulaiPolling();
        },
        /* Staf: cek antrean pesanan online tiap 20 detik dan beri tahu bila ada yang baru. */
        mulaiPolling() {
            clearInterval(this.timer);
            if (auth().role === 'pelanggan') return this.mulaiPollingPelanggan();
            if (!['kasir', 'admin', 'owner'].includes(auth().role)) return;
            const cek = async () => {
                try {
                    const r = await api('GET', '/pesanan-online/ringkasan');
                    if (this.antrean !== null && r.dibayar > this.antrean) toast('Pesanan online baru masuk!');
                    this.antrean = r.dibayar;
                } catch { /* diabaikan */ }
            };
            cek();
            this.timer = setInterval(cek, 20000);
        },
        /* Pelanggan: beri tahu (toast, notifikasi browser, judul tab) begitu pesanan berubah menjadi "siap diambil". */
        mulaiPollingPelanggan() {
            const cek = async () => {
                try {
                    const r = await api('GET', '/pelanggan/pesanan?per_page=6');
                    r.data.forEach((p) => {
                        if (this.statusLama[p.kode] && this.statusLama[p.kode] !== p.status && p.status === 'siap') this.notifSiap(p);
                        this.statusLama[p.kode] = p.status;
                    });
                } catch { /* diabaikan */ }
            };
            cek();
            this.timer = setInterval(cek, 15000);
        },
        notifSiap(p) {
            const teks = `Pesananmu ${p.nomor_antrean || p.kode} sudah siap diambil!`;
            toast(teks);
            if ('Notification' in window && Notification.permission === 'granted') new Notification('Kedai Kopi', { body: teks });
            const judul = document.title;
            document.title = 'Pesanan siap! · ' + judul;
            setTimeout(() => { document.title = judul; }, 10000);
            window.dispatchEvent(new Event('pesanan-berubah'));
        },
        menu() {
            let last = null;
            return Object.entries(PAGES).filter(([, p]) => bisaLihat(p) || (p.type === 'laporan' && auth().role === 'owner')).map(([key, p]) => {
                const m = META[key];
                const item = { key, title: p.title, icon: m.icon, group: m.group, first: m.group !== last, badge: key === 'pesanan-online' ? this.antrean || 0 : 0 };
                last = m.group;
                return item;
            });
        },
        namaDepan() { return (auth().user?.name || '').split(' ')[0]; },
        initial() { return (auth().user?.name || '?').charAt(0).toUpperCase(); },
        sapa() {
            const h = new Date().getHours();
            const w = h < 11 ? 'pagi' : h < 15 ? 'siang' : h < 18 ? 'sore' : 'malam';
            return `Selamat ${w}, ${this.namaDepan()}`;
        },
        judul() { return this.page === 'dashboard' ? this.sapa() : this.cfg().title; },
        eyebrow() { return this.page === 'dashboard' ? this.hariIni() : META[this.page].group; },
        hariIni() { return new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
        route() {
            if (!auth().user) return;
            const wanted = location.hash.slice(1);
            const allowed = this.menu().map((m) => m.key);
            this.page = allowed.includes(wanted) ? wanted : allowed[0];
            this.menuOpen = false;
        },
        cfg() { return PAGES[this.page] || {}; },
        async login() {
            this.busy = true; this.loginError = '';
            try {
                const r = await api('POST', '/login', { email: this.email, password: this.password });
                localStorage.setItem('token', r.token);
                auth().user = r.user;
                this.password = '';
                this.route();
                this.mulaiPolling();
            } catch (e) { this.loginError = e.message; }
            this.busy = false;
        },
        /* Pendaftaran akun pelanggan, lalu langsung masuk. */
        async daftar() {
            this.busy = true; this.loginError = '';
            try {
                const r = await api('POST', '/daftar', { name: this.nama, email: this.email, telepon: this.telepon, password: this.password });
                localStorage.setItem('token', r.token);
                auth().user = r.user;
                this.password = '';
                location.hash = '';
                this.route();
            } catch (e) { this.loginError = e.message; }
            this.busy = false;
        },
        async mintaOtp() {
            this.busy = true; this.loginError = ''; this.infoMsg = '';
            try {
                const r = await api('POST', '/lupa-sandi', { email: this.email });
                this.infoMsg = r.message + (r.demo_otp ? ` (Kode OTP: ${r.demo_otp})` : '');
                this.mode = 'reset';
            } catch (e) { this.loginError = e.message; }
            this.busy = false;
        },
        async prosesReset() {
            this.busy = true; this.loginError = '';
            try {
                const r = await api('POST', '/reset-sandi', { email: this.email, otp: this.otp, password: this.sandiBaru });
                toast(r.message);
                this.mode = 'masuk';
                this.password = '';
                this.otp = '';
                this.sandiBaru = '';
                this.infoMsg = '';
            } catch (e) { this.loginError = e.message; }
            this.busy = false;
        },
        sandi: { modal: false, lama: '', baru: '', err: '', busy: false },
        bukaSandi() { this.sandi = { modal: true, lama: '', baru: '', err: '', busy: false }; },
        async ubahSandi() {
            this.sandi.busy = true; this.sandi.err = '';
            try {
                await api('POST', '/akun/sandi', { sandi_lama: this.sandi.lama, sandi_baru: this.sandi.baru });
                this.sandi.modal = false; toast('Sandi diganti. Perangkat lain otomatis keluar.');
            } catch (e) { this.sandi.err = e.message; }
            this.sandi.busy = false;
        },
        async logout() {
            try { await api('POST', '/logout'); } catch {}
            localStorage.removeItem('token');
            location.hash = '';
            location.reload();
        },
    }));

    /* Basis daftar: pencarian, filter, paginasi. Memakai method (bukan getter) agar aman di-spread. */
    const list = (cfg) => ({
        cfg, rows: [], meta: {}, search: '', filter: {}, loading: false, error: '',
        fmt: (row, c) => (c.get ? c.get(row) : formatValue(pluck(row, c.key), c.fmt)),
        cls(row, c) {
            const v = Number(row[c.key]);
            if (c.signed) return v < 0 ? 'neg strong' : 'pos strong';
            if (c.key === 'stok' && cfg.endpoint === '/produk' && v <= STOK_MENIPIS) return 'neg strong';
            if (c.key === 'nama') return 'strong';
            return '';
        },
        canCreate() { return !cfg.readonly && (auth().role === 'owner' || (cfg.createRole ? cfg.createRole.includes(auth().role) : cfg.write.includes(auth().role))); },
        canWrite() { return !cfg.readonly && (auth().role === 'owner' || cfg.write.includes(auth().role)); },
        bisaEkspor() { return ADMIN.includes(auth().role); },
        async load(page = 1) {
            this.loading = true; this.error = '';
            const q = new URLSearchParams({ page });
            if (cfg.per_page) q.set('per_page', cfg.per_page);
            if (this.search) q.set('search', this.search);
            Object.entries(this.filter).forEach(([k, v]) => v && q.set(k, v));
            try {
                const r = await api('GET', `${cfg.endpoint}?${q}`);
                this.rows = r.data; this.meta = r;
            } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        async ekspor(format, path, nama) {
            try {
                await unduh(path, { format, bulan: this.filter.bulan, search: this.search, distributor_id: this.filter.distributor_id }, nama + '.' + format);
                toast(format === 'pdf' ? 'Laporan PDF diunduh' : 'Laporan Excel (CSV) diunduh');
            } catch (e) { toast(e.message, 'err'); }
        },
    });

    /* CRUD generik (tabel atau kartu) + form modal, unggah gambar, dan nonaktifkan/aktifkan */
    Alpine.data('crud', (cfg) => ({
        ...list(cfg),
        modal: false, form: {}, editId: null, saving: false, formError: '', options: {}, chips: [], preview: {}, bahanOpsi: [], opsiOpsi: [],
        async init() {
            if (cfg.endpoint === '/produk') {
                const s = sessionStorage.getItem('stokFilter'); // datang dari peringatan stok di dashboard
                if (s) { this.filter.stok_status = s; sessionStorage.removeItem('stokFilter'); }
            }
            await this.load();
            if (cfg.chips) this.chips = (await api('GET', `${cfg.chips.from}?per_page=100`)).data;
            if (!this.canCreate() && !this.canWrite()) return;
            for (const f of cfg.fields || []) {
                if (f.from) this.options[f.key] = (await api('GET', `${f.from}?per_page=500`)).data;
            }
            if (cfg.endpoint === '/produk') {
                this.bahanOpsi = (await api('GET', '/bahan-baku?per_page=500')).data;
                this.opsiOpsi = await api('GET', '/opsi-grup');
            }
        },
        /* --- resep & kustomisasi pada form menu --- */
        tambahResep() { this.form.resep.push({ bahan_baku_id: '', jumlah: '' }); },
        hapusResep(i) { this.form.resep.splice(i, 1); },
        satuanBahan(id) { return this.bahanOpsi.find((b) => b.id === Number(id))?.satuan || ''; },
        modalDariResep() {
            return (this.form.resep || []).reduce((s, r) => s + (Number(r.jumlah) || 0) * Number(this.bahanOpsi.find((b) => b.id === Number(r.bahan_baku_id))?.harga_terakhir || 0), 0);
        },
        pakaiModalResep() { this.form.harga_beli = Math.round(this.modalDariResep()); },
        opsiAda(id) { return (this.form.opsi_grup_ids || []).includes(id); },
        toggleOpsi(id) {
            const a = this.form.opsi_grup_ids || [];
            this.form.opsi_grup_ids = a.includes(id) ? a.filter((x) => x !== id) : [...a, id];
        },
        ringkasResep: (row) => (row.resep || []).length,
        pilihChip(v) { this.filter[cfg.chips.key] = v; this.load(); },
        chipAktif(v) { return !!cfg.chips && String(this.filter[cfg.chips.key] || '') === String(v); },
        toggleNonaktif() { this.filter.nonaktif = this.filter.nonaktif ? '' : '1'; this.load(); },
        nonaktifMode() { return !!this.filter.nonaktif; },
        art: (row) => katArt(row.kategori?.nama),
        state: (row) => stokState(row.stok, row.stok_minimum),
        margin(row) {
            const j = Number(row.harga_jual); const b = Number(row.harga_beli);
            return j > 0 ? Math.round(((j - b) / j) * 100) : 0;
        },
        pct(row) {
            const maks = Math.max(20, ...this.rows.map((r) => Number(r.stok)));
            return Math.min(100, (Number(row.stok) / maks) * 100);
        },
        choices(f) {
            if (f.choices) return f.choices;
            return (this.options[f.key] || []).map((o) => ({ v: o.id, l: o[f.optLabel] }));
        },
        bidang() { return (cfg.fields || []).filter((f) => !(f.createOnly && this.editId) && !(f.key === 'stok' && (this.form.resep || []).length)); },
        /* Peringatan non-blokir: harga jual di bawah modal. */
        peringatan() {
            if (cfg.endpoint !== '/produk') return '';
            const j = Number(this.form.harga_jual); const b = Number(this.form.harga_beli);
            if (!this.form.harga_jual || !this.form.harga_beli) return '';
            if (j < b) return `Harga jual (${rupiah(j)}) di bawah harga modal (${rupiah(b)}). Menu ini akan rugi ${rupiah(b - j)} per satuan. Tetap bisa disimpan.`;
            return `Margin ${rupiah(j - b)} per satuan (${Math.round(((j - b) / j) * 100)}%).`;
        },
        kurangUntung() { return cfg.endpoint === '/produk' && Number(this.form.harga_jual) < Number(this.form.harga_beli); },
        pilihFile(f, e) {
            const file = e.target.files[0] || null;
            this.form[f.key] = file;
            this.preview[f.key] = file ? URL.createObjectURL(file) : (this.editImage || null);
        },
        openNew() {
            this.editId = null; this.formError = ''; this.editImage = null; this.preview = {};
            this.form = Object.fromEntries(cfg.fields.map((f) => [f.key, f.type === 'file' ? null : (f.type === 'resep' || f.type === 'opsi') ? [] : (f.default ?? (f.key === 'aktif' ? 1 : ''))]));
            this.modal = true;
        },
        edit(row) {
            this.editId = row.id; this.formError = ''; this.editImage = row.image_url || null;
            this.preview = { gambar: this.editImage };
            this.form = Object.fromEntries(cfg.fields.map((f) => [f.key, this.nilaiAwal(f, row)]));
            this.modal = true;
        },
        nilaiAwal(f, row) {
            if (f.type === 'file') return null;
            if (f.type === 'resep') return (row.resep || []).map((r) => ({ bahan_baku_id: r.bahan_baku_id, jumlah: Number(r.jumlah) }));
            if (f.type === 'opsi') return (row.opsi_grup || []).map((g) => g.id);
            if (typeof row[f.key] === 'boolean') return row[f.key] ? 1 : 0;
            return row[f.key] ?? '';
        },
        payload() {
            const nilai = Object.fromEntries(this.bidang().map((f) => [f.key, this.form[f.key]]));
            if (!cfg.upload) return nilai;
            const fd = new FormData();
            Object.entries(nilai).forEach(([k, v]) => { if (!Array.isArray(v) && v !== '' && v !== null && v !== undefined) fd.append(k, v); });
            // Resep & kustomisasi dikirim sebagai array bertingkat; sync_relasi=1 membuat daftar kosong berarti "hapus semua".
            (this.form.resep || []).filter((r) => r.bahan_baku_id && Number(r.jumlah) > 0).forEach((r, i) => {
                fd.append(`resep[${i}][bahan_baku_id]`, r.bahan_baku_id);
                fd.append(`resep[${i}][jumlah]`, r.jumlah);
            });
            (this.form.opsi_grup_ids || []).forEach((id) => fd.append('opsi_grup_ids[]', id));
            fd.append('sync_relasi', '1');
            if (this.editId) fd.append('_method', 'PUT'); // PUT multipart tidak terbaca PHP, jadi di-spoof lewat POST
            return fd;
        },
        async save() {
            this.saving = true; this.formError = '';
            try {
                const lewatPost = cfg.upload || !this.editId;
                await api(lewatPost ? 'POST' : 'PUT', cfg.endpoint + (this.editId ? '/' + this.editId : ''), this.payload());
                this.modal = false;
                toast(this.editId ? 'Perubahan tersimpan' : 'Data berhasil ditambahkan');
                await this.load(this.editId ? this.meta.current_page : 1);
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
        async remove(row) {
            if (!confirm(cfg.softConfirm || 'Hapus data ini?')) return;
            try { await api('DELETE', `${cfg.endpoint}/${row.id}`); toast(cfg.softLabel ? 'Menu dinonaktifkan' : 'Data dihapus'); await this.load(this.meta.current_page); }
            catch (e) { this.error = e.message; }
        },
        async aktifkan(row) {
            try { await api('POST', `${cfg.endpoint}/${row.id}/aktifkan`); toast('Menu diaktifkan kembali'); await this.load(this.meta.current_page); }
            catch (e) { this.error = e.message; }
        },
    }));

    /* Penjualan: daftar nota + kasir (POS) layar penuh */
    Alpine.data('penjualan', () => ({
        ...list(PAGES.penjualan),
        ...keranjang(),
        ...harga(),
        tipe: 'dine_in', meja: '', metode: 'tunai', keranjangBuka: false,
        metodeList: [['tunai', 'Tunai', 'banknote'], ['qris', 'QRIS', 'wallet'], ['kartu', 'Kartu', 'card'], ['transfer', 'Transfer', 'building']],
        kasModal: false, kas: { tipe: 'keluar', jumlah: '', keterangan: '' }, busyKas: false, errKas: '',
        voidRow: null, alasanVoid: '', busyVoid: false, errVoid: '',
        shift: null, modalAwal: '', tutupModal: false, uangDihitung: '', catatanShift: '', busyShift: false, errShift: '',
        detail: null, form: false, saving: false, formError: '', produkList: [], cari: '', items: [], extra: {}, bayar: '', catatan: '',
        pembeliList: [], kat: '',
        antreanDitahan: JSON.parse(localStorage.getItem('kasir_hold') || '[]'),
        holdModal: false,
        suksesModal: false,
        terakhirSukses: null,
        waNomor: '',
        tukarPoin: 0,
        art: (p) => katArt(p?.kategori?.nama),
        async init() {
            await this.load(); await this.muatShift(); this.muatPeng();
            this.$watch(() => this.subtotal(), () => this.segarkanVoucher());
        },
        pembeliTerpilih() {
            return this.pembeliList.find((p) => p.id === Number(this.extra.pembeli_id));
        },
        diskonPoin() {
            const p = this.pembeliTerpilih();
            if (!p || !this.tukarPoin || Number(p.poin) <= 0) return 0;
            const r = this.rincian();
            return Math.min(Number(this.tukarPoin) * 1000, r.total);
        },
        togglePakaiPoin() {
            const p = this.pembeliTerpilih();
            if (!p || Number(p.poin) <= 0) return;
            if (this.tukarPoin > 0) {
                this.tukarPoin = 0;
            } else {
                const r = this.rincian();
                const butuh = Math.ceil(r.total / 1000);
                this.tukarPoin = Math.min(Number(p.poin), butuh);
            }
        },
        total() {
            const r = this.rincian();
            return Math.max(0, r.total - this.diskonPoin());
        },
        tahanAntrean() {
            if (!this.items.length) { toast('Keranjang masih kosong.', 'err'); return; }
            const pb = this.pembeliTerpilih();
            const nama = pb ? pb.nama : (this.meja ? `Meja ${this.meja}` : `Antrean #${this.antreanDitahan.length + 1}`);
            const order = {
                id: Date.now(),
                waktu: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                nama,
                tipe: this.tipe,
                meja: this.meja,
                extra: { ...this.extra },
                catatan: this.catatan,
                items: JSON.parse(JSON.stringify(this.items)),
                kodeVoucher: this.kode,
                tukarPoin: this.tukarPoin,
                total: this.total(),
            };
            this.antreanDitahan.push(order);
            localStorage.setItem('kasir_hold', JSON.stringify(this.antreanDitahan));
            this.items = []; this.meja = ''; this.extra = {}; this.catatan = ''; this.bayar = ''; this.tukarPoin = 0; this.lepasVoucher();
            toast(`Pesanan "${order.nama}" ditahan. Siap melayani pelanggan berikutnya.`);
        },
        pulihkanAntrean(i) {
            const o = this.antreanDitahan[i];
            if (!o) return;
            this.items = o.items || [];
            this.tipe = o.tipe || 'dine_in';
            this.meja = o.meja || '';
            this.extra = o.extra || {};
            this.catatan = o.catatan || '';
            this.tukarPoin = o.tukarPoin || 0;
            if (o.kodeVoucher) { this.kode = o.kodeVoucher; this.pakaiVoucher(); }
            this.antreanDitahan.splice(i, 1);
            localStorage.setItem('kasir_hold', JSON.stringify(this.antreanDitahan));
            this.holdModal = false;
            toast(`Pesanan "${o.nama}" dipanggil kembali.`);
        },
        hapusTahanAntrean(i) {
            this.antreanDitahan.splice(i, 1);
            localStorage.setItem('kasir_hold', JSON.stringify(this.antreanDitahan));
            toast('Antrean ditahan dihapus.');
        },
        /* Kasir hanya boleh void nota di shift yang sedang berjalan (aturan yang sama dijaga server). */
        bisaVoid(row) {
            if (!row.id || row.void_at || row.sumber === 'online') return false;
            if (auth().role === 'kasir') return !!this.shift && row.shift_id === this.shift.id;
            return ['admin', 'owner'].includes(auth().role);
        },
        siapVoid(row) { this.voidRow = row; this.alasanVoid = ''; this.errVoid = ''; },
        async jalankanVoid() {
            this.busyVoid = true; this.errVoid = '';
            try {
                await api('POST', `/penjualan/${this.voidRow.id}/void`, { alasan: this.alasanVoid });
                toast(`Nota ${this.voidRow.no_nota} dibatalkan, stok dikembalikan.`);
                this.voidRow = null; this.detail = null;
                await this.load(this.meta.current_page || 1); await this.muatShift();
            } catch (e) { this.errVoid = e.message; }
            this.busyVoid = false;
        },
        siapKas(tipe) { this.kas = { tipe, jumlah: '', keterangan: '' }; this.errKas = ''; this.kasModal = true; },
        async simpanKas() {
            this.busyKas = true; this.errKas = '';
            try { this.shift = await api('POST', '/shift/kas', this.kas); this.kasModal = false; toast(this.kas.tipe === 'masuk' ? 'Kas masuk dicatat' : 'Kas keluar dicatat'); }
            catch (e) { this.errKas = e.message; }
            this.busyKas = false;
        },
        metodeNama: (m) => ({ tunai: 'Tunai', qris: 'QRIS', kartu: 'Kartu', transfer: 'Transfer', online: 'Online' }[m] || m),
        /* Kasir wajib membuka shift (modal awal) sebelum berjualan; admin/owner tidak. */
        perluShift: () => auth().role === 'kasir',
        async muatShift() { if (this.perluShift()) { try { this.shift = (await api('GET', '/shift/aktif')).shift; } catch (e) { this.error = e.message; } } },
        async bukaShift() {
            this.busyShift = true; this.errShift = '';
            try { this.shift = await api('POST', '/shift/buka', { modal_awal: this.modalAwal }); this.modalAwal = ''; toast('Shift dibuka. Selamat berjualan!'); }
            catch (e) { this.errShift = e.message; }
            this.busyShift = false;
        },
        async siapTutup() {
            await this.muatShift();
            this.uangDihitung = ''; this.catatanShift = ''; this.errShift = ''; this.tutupModal = true;
        },
        /* Selisih sementara: uang yang dihitung - kas seharusnya (negatif = kurang). */
        selisihShift() { return (Number(this.uangDihitung) || 0) - Number(this.shift?.rekap.kas_seharusnya || 0); },
        async tutupShift() {
            this.busyShift = true; this.errShift = '';
            try {
                const r = await api('POST', '/shift/tutup', { uang_dihitung: this.uangDihitung, catatan: this.catatanShift || null });
                this.tutupModal = false; this.shift = null;
                const sel = Number(r.selisih);
                toast(sel === 0 ? 'Shift ditutup, kas pas.' : `Shift ditutup, kas ${sel < 0 ? 'kurang' : 'lebih'} ${rupiah(Math.abs(sel))}.`);
                await this.load();
                try { await cetakRekap(r.id); } catch (e) { toast(e.message, 'err'); }
            } catch (e) { this.errShift = e.message; }
            this.busyShift = false;
        },
        async bukaForm() {
            if (this.perluShift() && !this.shift) { toast('Buka shift dulu sebelum berjualan.', 'err'); return; }
            this.items = []; this.cari = ''; this.bayar = ''; this.catatan = ''; this.formError = ''; this.extra = {}; this.kat = ''; this.tipe = 'dine_in'; this.meja = ''; this.metode = 'tunai'; this.keranjangBuka = false; this.tukarPoin = 0; this.lepasVoucher(); await this.muatPeng();
            this.produkList = (await api('GET', '/produk?per_page=500')).data;
            this.pembeliList = (await api('GET', '/pembeli?per_page=500')).data;
            this.form = true;
        },
        kategoriList() {
            const m = new Map();
            this.produkList.forEach((p) => p.kategori && m.set(p.kategori.id, p.kategori.nama));
            return [...m].map(([id, nama]) => ({ id, nama }));
        },
        grid() {
            const q = this.cari.toLowerCase();
            return this.produkList.filter((p) => (!q || p.nama.toLowerCase().includes(q)) && (!this.kat || p.kategori_id === this.kat));
        },
        jumlahItem() { return this.items.reduce((s, i) => s + Number(i.qty), 0); },
        kembalian() { return this.metode === 'tunai' ? (Number(this.bayar) || 0) - this.total() : 0; },
        bisaBayar() { return this.items.length > 0 && this.kembalian() >= 0; },
        cepat() {
            const t = this.total();
            if (t <= 0 || this.metode !== 'tunai') return [];
            const naik = (n) => Math.ceil(t / n) * n;
            return [...new Set([t, naik(10000), naik(50000), naik(100000)])].filter((v) => v >= t).slice(0, 4);
        },
        async lihat(row) { this.detail = await api('GET', `/penjualan/${row.id}`); },
        async simpan() {
            this.saving = true; this.formError = '';
            try {
                const r = await api('POST', '/penjualan', {
                    pembeli_id: this.extra.pembeli_id || null, bayar: this.metode === 'tunai' ? this.bayar : null, catatan: this.catatan,
                    metode: this.metode, voucher: this.vinfo ? this.kode : null, tukar_poin: this.tukarPoin || null,
                    tipe: this.tipe, meja: this.tipe === 'dine_in' ? this.meja || null : null, items: this.muatBaris(),
                });
                this.terakhirSukses = r;
                this.waNomor = r.pembeli?.telepon || '';
                this.suksesModal = true;
                this.form = false;
                toast(`Nota ${r.no_nota} tersimpan · Antrean ${r.nomor_antrean}`);
                await this.muatShift();
                await this.load();
                try { await cetakNota(r.id); } catch { /* dialog cetak */ }
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
        pembeliBaruModal: false, pembeliBaru: { nama: '', telepon: '', alamat: '' }, busyPembeliBaru: false, errPembeliBaru: '',
        bukaModalPembeli() {
            this.pembeliBaru = { nama: '', telepon: '', alamat: '' };
            this.errPembeliBaru = '';
            this.pembeliBaruModal = true;
        },
        async simpanPembeliBaru() {
            if (!this.pembeliBaru.nama?.trim()) { this.errPembeliBaru = 'Nama pembeli wajib diisi.'; return; }
            this.busyPembeliBaru = true; this.errPembeliBaru = '';
            try {
                const r = await api('POST', '/pembeli', this.pembeliBaru);
                this.pembeliList = (await api('GET', '/pembeli?per_page=500')).data;
                this.extra.pembeli_id = r.id;
                this.pembeliBaruModal = false;
                toast('Pembeli berhasil ditambahkan');
            } catch (e) { this.errPembeliBaru = e.message; }
            this.busyPembeliBaru = false;
        },
        async nota(id) { try { await cetakNota(id); } catch (e) { this.error = e.message; } },
    }));

    /* Belanja / restock: item bisa bahan baku atau menu jadi */
    Alpine.data('pembelian', () => ({
        ...list(PAGES.pembelian),
        detail: null, form: false, saving: false, formError: '', distributorList: [], bahanList: [], produkList: [],
        cari: '', items: [], extra: {}, catatan: '', tgl: '',
        async init() {
            await this.load();
            this.distributorList = (await api('GET', '/distributor?per_page=500')).data;
        },
        async bukaForm(hanyaMenipis = false) {
            this.items = []; this.cari = ''; this.catatan = hanyaMenipis ? 'Restok otomatis dari stok menipis' : ''; this.formError = ''; this.extra = {}; this.tgl = hariIsoIni();
            this.bahanList = (await api('GET', '/bahan-baku?per_page=500')).data;
            this.produkList = (await api('GET', '/produk?per_page=500')).data;
            if (hanyaMenipis || sessionStorage.getItem('autoRestock')) {
                sessionStorage.removeItem('autoRestock');
                this.bahanList.filter((b) => Number(b.stok) <= 5).forEach((b) => {
                    const saranQty = Math.max(10, Math.ceil(15 - Number(b.stok)));
                    this.items.push({ tipe: 'bahan', id: b.id, nama: b.nama, satuan: b.satuan, stok: b.stok, harga: b.harga_terakhir || 0, qty: saranQty });
                });
                this.produkList.filter((p) => Number(p.stok) <= (Number(p.stok_minimum) || 5) && !(p.resep || []).length).forEach((p) => {
                    const min = Number(p.stok_minimum) || 5;
                    const saranQty = Math.max(min, Math.ceil((min * 2) - Number(p.stok)));
                    this.items.push({ tipe: 'produk', id: p.id, nama: p.nama, satuan: p.satuan, stok: p.stok, harga: p.harga_beli || 0, qty: saranQty });
                });
                toast(`Draf belanja otomatis terisi ${this.items.length} item.`);
            }
            this.form = true;
        },
        pilihan() {
            const q = this.cari.toLowerCase();
            const semua = [
                ...this.bahanList.map((b) => ({ tipe: 'bahan', id: b.id, nama: b.nama, satuan: b.satuan, stok: b.stok, harga: 0 })),
                ...this.produkList.map((p) => ({ tipe: 'produk', id: p.id, nama: p.nama, satuan: p.satuan, stok: p.stok, harga: p.harga_beli })),
            ];
            return semua.filter((o) => (!q || o.nama.toLowerCase().includes(q)) && !this.items.some((i) => i.tipe === o.tipe && i.id === o.id)).slice(0, 10);
        },
        tambah(o) { this.items.push({ ...o, qty: 1 }); this.cari = ''; },
        hapusItem(i) { this.items.splice(i, 1); },
        total() { return this.items.reduce((s, i) => s + (Number(i.qty) || 0) * Number(i.harga || 0), 0); },
        namaItem: (d) => d.bahan_baku?.nama ?? d.produk?.nama ?? '-',
        satuanItem: (d) => d.bahan_baku?.satuan ?? d.produk?.satuan ?? '',
        async lihat(row) { this.detail = await api('GET', `/pembelian/${row.id}`); },
        async simpan() {
            this.saving = true; this.formError = '';
            try {
                const r = await api('POST', '/pembelian', {
                    distributor_id: this.extra.distributor_id, catatan: this.catatan, tanggal: this.tgl || null,
                    items: this.items.map((i) => ({ [i.tipe === 'bahan' ? 'bahan_baku_id' : 'produk_id']: i.id, qty: i.qty, harga_beli: i.harga })),
                });
                this.form = false; toast(`Belanja ${r.no_faktur} tersimpan, stok bertambah`);
                await this.load();
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
    }));

    /* Pelanggan: katalog menu + keranjang, lalu bayar online */
    Alpine.data('katalog', () => ({
        ...keranjang(),
        ...harga(),
        produkList: [], kat: '', cari: '', items: [], catatan: '', loading: true, error: '', saving: false, buka: false,
        tipe: 'bawa_pulang', meja: '',
        art: (p) => katArt(p?.kategori?.nama),
        async init() {
            await this.muat(); this.loading = false; this.muatPeng();
            this.$watch(() => this.subtotal(), () => this.segarkanVoucher());
        },
        async muat() {
            try { this.produkList = (await api('GET', '/produk?per_page=500')).data; } catch (e) { this.error = e.message; }
        },
        kategoriList() {
            const m = new Map();
            this.produkList.forEach((p) => p.kategori && m.set(p.kategori.id, p.kategori.nama));
            return [...m].map(([id, nama]) => ({ id, nama }));
        },
        grid() {
            const q = this.cari.toLowerCase();
            return this.produkList.filter((p) => (!q || p.nama.toLowerCase().includes(q)) && (!this.kat || p.kategori_id === this.kat));
        },
        sisaText(p) { const s = Number(p.stok); return s <= 0 ? 'Habis' : s <= STOK_MENIPIS ? `Tinggal ${angka(s)}` : ''; },
        jumlah() { return this.items.reduce((s, i) => s + i.qty, 0); },
        async pesan() {
            if (this.tipe === 'dine_in' && !this.meja.trim()) { this.error = 'Isi nomor mejamu.'; return; }
            // Izin notifikasi diminta di sini (dipicu klik) agar pelanggan diberi tahu saat pesanan siap.
            if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
            this.saving = true; this.error = '';
            try {
                const r = await api('POST', '/pelanggan/pesanan', { catatan: this.catatan, voucher: this.vinfo ? this.kode : null, tipe: this.tipe, meja: this.tipe === 'dine_in' ? this.meja.trim() : null, items: this.muatBaris() });
                this.items = []; this.catatan = ''; this.lepasVoucher(); this.buka = false;
                await this.muat(); // stok terbaru setelah dipesan
                Alpine.store('bayar').buka(r);
            } catch (e) { this.error = e.message; await this.muat(); }
            this.saving = false;
        },
    }));

    Alpine.data('pesanansaya', () => ({
        rows: [], meta: {}, loading: true, error: '', timer: null, handler: null,
        async init() {
            await this.load();
            this.timer = setInterval(() => this.load(this.meta.current_page || 1, true), 10000);
            this.handler = () => this.load(1, true);
            window.addEventListener('pesanan-berubah', this.handler);
        },
        destroy() { clearInterval(this.timer); window.removeEventListener('pesanan-berubah', this.handler); },
        async load(page = 1, diam = false) {
            if (!diam) this.loading = true;
            try { const r = await api('GET', `/pelanggan/pesanan?page=${page}&per_page=8`); this.rows = r.data; this.meta = r; }
            catch (e) { this.error = e.message; }
            this.loading = false;
        },
        statusLabel,
        menunggu: (p) => p.status === 'menunggu_pembayaran',
        /* Indeks langkah pelacakan (0..3) atau -1 bila belum dibayar / sudah ditutup. */
        langkah: (p) => LANGKAH.findIndex(([k]) => k === p.status),
        langkahList: LANGKAH,
        tutup: (p) => ['dibatalkan', 'kedaluwarsa', 'perlu_refund'].includes(p.status),
        bayar(p) { Alpine.store('bayar').buka(p); },
        async batal(p) {
            if (!confirm('Batalkan pesanan ini?')) return;
            try { await api('POST', `/pelanggan/pesanan/${p.kode}/batal`); toast('Pesanan dibatalkan'); await this.load(this.meta.current_page || 1, true); }
            catch (e) { this.error = e.message; }
        },
        ulasanModal: false,
        ulasanPesanan: null,
        ulasanBintang: 5,
        ulasanKomentar: '',
        ulasanBusy: false,
        ulasanErr: '',
        bukaUlasan(p) {
            this.ulasanPesanan = p;
            this.ulasanBintang = 5;
            this.ulasanKomentar = '';
            this.ulasanErr = '';
            this.ulasanModal = true;
        },
        sudahDiulas(p) {
            return (p.ulasan || []).length > 0;
        },
        bisaUlas(p) {
            return ['selesai', 'siap', 'dibayar', 'diproses'].includes(p.status) && !this.sudahDiulas(p);
        },
        async simpanUlasan() {
            if (!this.ulasanPesanan) return;
            this.ulasanBusy = true;
            this.ulasanErr = '';
            try {
                const items = (this.ulasanPesanan.detail || []).map((d) => ({
                    produk_id: d.produk_id,
                    bintang: Number(this.ulasanBintang),
                    komentar: this.ulasanKomentar || null,
                }));
                await api('POST', `/pelanggan/pesanan/${this.ulasanPesanan.kode}/ulasan`, { items });
                toast('Ulasan berhasil dikirim! Terima kasih atas penilaianmu ⭐');
                this.ulasanModal = false;
                await this.load(this.meta.current_page || 1, true);
            } catch (e) {
                this.ulasanErr = e.message;
            }
            this.ulasanBusy = false;
        },
    }));

    /* Kasir/admin: antrean pesanan online */
    Alpine.data('pesananstaff', () => ({
        tab: 'dibayar', rows: [], hitung: {}, loading: true, error: '', timer: null, busy: '',
        tabs: [['dibayar', 'Perlu diproses'], ['diproses', 'Diproses'], ['siap', 'Siap diambil'], ['selesai', 'Selesai'], ['perlu_refund', 'Perlu refund']],
        async init() { await this.load(); this.timer = setInterval(() => this.load(true), 15000); },
        destroy() { clearInterval(this.timer); },
        async load(diam = false) {
            if (!diam) this.loading = true;
            try {
                this.rows = (await api('GET', `/pesanan-online?status=${this.tab}&per_page=50`)).data;
                this.hitung = await api('GET', '/pesanan-online/ringkasan');
            } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        async pilih(k) { this.tab = k; this.rows = []; await this.load(); },
        aksi: (p) => ({ dibayar: 'Mulai buat', diproses: 'Tandai siap', siap: 'Serahkan (selesai)' }[p.status]),
        async maju(p) {
            const next = { dibayar: 'diproses', diproses: 'siap', siap: 'selesai' }[p.status];
            this.busy = p.kode;
            try { await api('PATCH', `/pesanan-online/${p.kode}/status`, { status: next }); toast(`${p.kode}: ${statusLabel(next)}`); await this.load(true); }
            catch (e) { toast(e.message, 'err'); }
            this.busy = '';
        },
        statusLabel,
        art: (pd) => katArt(pd?.kategori?.nama),
    }));

    /* Kelola kelompok kustomisasi (Ukuran, Gula, Es, Tambahan) */
    Alpine.data('opsimenu', () => ({
        rows: [], loading: true, error: '', modal: false, form: { nama: '', banyak: false, wajib: false, pilihan: [] }, editId: null, saving: false, formError: '',
        bahanList: [],
        async init() { await this.load(); this.bahanList = (await api('GET', '/bahan-baku?per_page=500')).data; },
        async load() {
            try { this.rows = await api('GET', '/opsi-grup'); } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        satuan(id) { return this.bahanList.find((b) => b.id === Number(id))?.satuan || ''; },
        baru() {
            this.editId = null; this.formError = '';
            this.form = { nama: '', banyak: false, wajib: false, pilihan: [{ nama: '', tambahan_harga: 0, bawaan: true, bahan_baku_id: '', jumlah_bahan: '' }] };
            this.modal = true;
        },
        ubah(g) {
            this.editId = g.id; this.formError = '';
            this.form = { nama: g.nama, banyak: !!g.banyak, wajib: !!g.wajib, pilihan: g.pilihan.map((p) => ({ nama: p.nama, tambahan_harga: Number(p.tambahan_harga), bawaan: !!p.bawaan, bahan_baku_id: p.bahan_baku_id || '', jumlah_bahan: p.jumlah_bahan ? Number(p.jumlah_bahan) : '' })) };
            this.modal = true;
        },
        tambahPilihan() { this.form.pilihan.push({ nama: '', tambahan_harga: 0, bawaan: false, bahan_baku_id: '', jumlah_bahan: '' }); },
        hapusPilihan(i) { this.form.pilihan.splice(i, 1); },
        /* Pada grup "pilih satu" hanya satu pilihan boleh menjadi bawaan. */
        setBawaan(i) {
            const nilai = !this.form.pilihan[i].bawaan;
            if (!this.form.banyak) this.form.pilihan.forEach((p) => { p.bawaan = false; });
            this.form.pilihan[i].bawaan = nilai;
        },
        async simpan() {
            this.saving = true; this.formError = '';
            try {
                const badan = { ...this.form, pilihan: this.form.pilihan.map((p) => ({ ...p, bahan_baku_id: p.bahan_baku_id || null, jumlah_bahan: p.bahan_baku_id ? p.jumlah_bahan : null })) };
                await api(this.editId ? 'PUT' : 'POST', '/opsi-grup' + (this.editId ? '/' + this.editId : ''), badan);
                this.modal = false; toast('Kustomisasi tersimpan'); await this.load();
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
        async hapus(g) {
            if (!confirm(`Hapus kelompok "${g.nama}"? Menu yang memakainya tidak lagi menampilkannya. Transaksi lama tidak berubah.`)) return;
            try { await api('DELETE', `/opsi-grup/${g.id}`); toast('Kelompok dihapus'); await this.load(); } catch (e) { this.error = e.message; }
        },
        ringkas: (g) => g.pilihan.map((p) => p.nama + (Number(p.tambahan_harga) ? ` (+${rupiah(p.tambahan_harga)})` : '') + (p.bahan_baku_id ? ' ⚙' : '')).join(' · '),
    }));

    /* Stok opname: hitung fisik bahan baku / menu tanpa resep, selisih otomatis dikoreksi */
    Alpine.data('opname', () => ({
        rows: [], meta: {}, loading: true, error: '', form: false, saving: false, formError: '', catatan: '', jenis: 'bahan',
        bahan: [], produk: [], fisik: {}, alasanMap: {}, detail: null, cari: '',
        async init() { await this.load(); },
        async load(page = 1) {
            this.loading = true;
            try { const r = await api('GET', `/opname?page=${page}`); this.rows = r.data; this.meta = r; }
            catch (e) { this.error = e.message; }
            this.loading = false;
        },
        async baru() {
            this.catatan = ''; this.fisik = {}; this.alasanMap = {}; this.formError = ''; this.cari = ''; this.jenis = 'bahan';
            this.bahan = (await api('GET', '/bahan-baku?per_page=500')).data;
            this.produk = (await api('GET', '/produk?per_page=500')).data.filter((p) => !(p.resep || []).length);
            this.form = true;
        },
        daftar() {
            const q = this.cari.toLowerCase();
            return (this.jenis === 'bahan' ? this.bahan : this.produk).filter((x) => !q || x.nama.toLowerCase().includes(q));
        },
        kunci(x) { return this.jenis + ':' + x.id; },
        selisih(x) { const v = this.fisik[this.kunci(x)]; return v === '' || v === undefined ? null : Number(v) - Number(x.stok); },
        jumlahBeda() { return Object.entries(this.fisik).filter(([k, v]) => {
            if (v === '' || v === undefined) return false;
            const [j, id] = k.split(':'); const x = (j === 'bahan' ? this.bahan : this.produk).find((y) => y.id === Number(id));
            return x && Number(v) !== Number(x.stok);
        }).length; },
        async simpan() {
            const items = Object.entries(this.fisik).filter(([, v]) => v !== '' && v !== undefined).map(([k, v]) => {
                const [jenis, id] = k.split(':');
                return { jenis, id: Number(id), stok_fisik: v, alasan: this.alasanMap[k] || 'selisih_hitung' };
            });
            if (!items.length) { this.formError = 'Isi minimal satu hitungan fisik.'; return; }
            this.saving = true; this.formError = '';
            try {
                const res = await api('POST', '/opname', { catatan: this.catatan || null, items });
                this.form = false;
                toast(res.status === 'menunggu_persetujuan' ? 'Opname tersimpan, menunggu persetujuan Owner.' : 'Opname disetujui, stok disesuaikan.');
                await this.load();
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
        async setujui(o) {
            if (!confirm(`Setujui opname #${o.id}? Stok akan disesuaikan sesuai hitungan fisik.`)) return;
            try {
                await api('POST', `/opname/${o.id}/setujui`);
                toast('Opname disetujui, stok disesuaikan.');
                this.detail = await api('GET', `/opname/${o.id}`);
                await this.load();
            } catch (e) { alert(e.message); }
        },
        async tolak(o) {
            const alasan = prompt('Alasan penolakan opname:');
            if (alasan === null) return;
            try {
                await api('POST', `/opname/${o.id}/tolak`, { alasan });
                toast('Opname ditolak.');
                this.detail = await api('GET', `/opname/${o.id}`);
                await this.load();
            } catch (e) { alert(e.message); }
        },
        async lihat(o) { this.detail = await api('GET', `/opname/${o.id}`); },
    }));

    /* Pengaturan toko (owner): nama & alamat di nota, pajak, service charge */
    Alpine.data('pengaturan', () => ({
        f: { nama_toko: '', alamat: '', footer_nota: '', pajak_persen: 0, service_persen: 0, fonnte_token: '', npwp: '', nib: '', badan_usaha: '', bank_rekening: '', tutup_buku_sampai: '' }, loading: true, saving: false, error: '',
        async init() {
            try { this.f = { ...this.f, ...(await api('GET', '/pengaturan')) }; } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        contoh() {
            const sub = 50000; const sv = Math.round(sub * Number(this.f.service_persen || 0) / 100);
            const pj = Math.round((sub + sv) * Number(this.f.pajak_persen || 0) / 100);
            return { sub, sv, pj, total: sub + sv + pj };
        },
        async simpan() {
            this.saving = true; this.error = '';
            try { this.f = { ...this.f, ...(await api('PUT', '/pengaturan', this.f)) }; toast('Pengaturan disimpan'); }
            catch (e) { this.error = e.message; }
            this.saving = false;
        },
    }));

    /* WhatsApp Blast via Fonnte Gateway */
    Alpine.data('wablast', () => ({
        rows: [], meta: {}, loading: true, error: '', sending: false,
        fonnteKonfigurasi: false, totalWa: 0, totalLoyal: 0,
        f: { judul: '', target_tipe: 'semua', nomor_manual: '', pesan: '' },
        templateList: [
            {
                judul: 'Promo Diskon Kopi 20%',
                pesan: 'Halo Sobat Kopi! ☕\nNikmati diskon spesial 20% untuk semua menu kopi hari ini di kedai kami. Tunjukkan pesan ini ke kasir ya! Sampai jumpa 🙏',
            },
            {
                judul: 'Info Menu Baru Kedai',
                pesan: 'Hai Pecinta Kopi! ✨\nAda menu baru nih di Kedai Kopi! Diracik dari biji kopi pilihan terbaik. Yuk mampir dan cicipi sensasi segarnya hari ini!',
            },
            {
                judul: 'Pemberitahuan Poin Member',
                pesan: 'Halo Kak! 🎁\nPoin member kedai kopi kamu siap ditukarkan dengan potongan belanja menarik. Kunjungi kedai kami dan nikmati diskon poinmu sekarang!',
            },
        ],
        async init() { await this.load(); },
        async load(page = 1) {
            this.loading = true; this.error = '';
            try {
                const r = await api('GET', `/wa-blast?page=${page}`);
                this.rows = r.data;
                this.meta = r;
                this.fonnteKonfigurasi = r.fonnte_konfigurasi;
                this.totalWa = r.total_kontak_wa;
                this.totalLoyal = r.total_kontak_loyal;
            } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        pakaiTemplate(t) {
            this.f.judul = t.judul;
            this.f.pesan = t.pesan;
        },
        targetEstimasi() {
            if (this.f.target_tipe === 'semua') return `${this.totalWa} Pelanggan`;
            if (this.f.target_tipe === 'loyal') return `${this.totalLoyal} Pelanggan Loyal (Punya Poin)`;
            const m = (this.f.nomor_manual || '').split(/[\r\n,]+/).filter((x) => x.trim().length > 3);
            return `${m.length} Nomor Manual`;
        },
        async kirim() {
            if (!this.f.judul.trim() || !this.f.pesan.trim()) { toast('Judul dan pesan WA wajib diisi.', 'err'); return; }
            if (this.f.target_tipe === 'manual' && !this.f.nomor_manual.trim()) { toast('Masukkan nomor WhatsApp tujuan.', 'err'); return; }
            this.sending = true;
            try {
                const r = await api('POST', '/wa-blast', this.f);
                toast(r.message || 'WhatsApp blast berhasil dikirim! 🚀');
                this.f.judul = '';
                this.f.pesan = '';
                this.f.nomor_manual = '';
                await this.load();
            } catch (e) { toast(e.message, 'err'); }
            this.sending = false;
        },
    }));

    /* Laporan penjualan lanjutan (owner) */
    Alpine.data('laporanjual', () => {
        const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        const n = new Date();
        return {
            dari: iso(new Date(n.getFullYear(), n.getMonth(), 1)), sampai: iso(n), d: null, loading: false, error: '',
            async init() { await this.load(); },
            async load() {
                this.loading = true; this.error = '';
                try { this.d = await api('GET', `/laporan/penjualan?dari=${this.dari}&sampai=${this.sampai}`); }
                catch (e) { this.error = e.message; this.d = null; }
                this.loading = false;
            },
            preset(hari) {
                const a = new Date(); const b = new Date(); b.setDate(a.getDate() - hari + 1);
                this.dari = iso(b); this.sampai = iso(a); this.load();
            },
            bulanIni() { const a = new Date(); this.dari = iso(new Date(a.getFullYear(), a.getMonth(), 1)); this.sampai = iso(a); this.load(); },
            maks: (arr, k) => Math.max(1, ...arr.map((x) => Number(x[k]))),
            pct(x, arr, k) { return Math.max(x[k] > 0 ? 3 : 0, (Number(x[k]) / this.maks(arr, k)) * 100); },
            jamRamai() { const j = [...this.d.per_jam].sort((a, b) => b.transaksi - a.transaksi)[0]; return j && j.transaksi ? `${String(j.jam).padStart(2, '0')}.00–${String(j.jam + 1).padStart(2, '0')}.00` : '-'; },
            metodeNama: (m) => ({ tunai: 'Tunai', qris: 'QRIS', kartu: 'Kartu', transfer: 'Transfer', online: 'Online' }[m] || m),
            tglPendek: (t) => new Date(t + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }),
            rupiah,
        };
    });

    /* Riwayat shift semua kasir (admin/owner) */
    Alpine.data('shiftlog', () => ({
        rows: [], meta: {}, bulan: '', loading: true, error: '',
        async init() { await this.load(); },
        async load(page = 1) {
            this.loading = true;
            try { const r = await api('GET', `/shift?page=${page}` + (this.bulan ? `&bulan=${this.bulan}` : '')); this.rows = r.data; this.meta = r; }
            catch (e) { this.error = e.message; }
            this.loading = false;
        },
        selisihCls: (r) => (!r.ditutup_pada ? '' : Number(r.selisih) === 0 ? 'b-aman' : Number(r.selisih) < 0 ? 'b-habis' : 'b-menipis'),
        selisihTeks: (r) => (!r.ditutup_pada ? 'Berjalan' : Number(r.selisih) === 0 ? 'Pas' : (Number(r.selisih) < 0 ? 'Kurang ' : 'Lebih ') + rupiah(Math.abs(r.selisih))),
        async cetak(r) { try { await cetakRekap(r.id); } catch (e) { toast(e.message, 'err'); } },
    }));

    /* Manajemen akun staf (khusus owner) */
    Alpine.data('pengguna', () => ({
        rows: [], meta: {}, search: '', loading: true, error: '', modal: false, form: {}, editId: null, saving: false, formError: '',
        peran: [['kasir', 'Kasir'], ['admin', 'Admin'], ['owner', 'Owner']],
        saya: () => auth().user?.id,
        async init() { await this.load(); },
        async load(page = 1) {
            this.loading = true;
            try { const r = await api('GET', `/pengguna?page=${page}&search=${encodeURIComponent(this.search)}`); this.rows = r.data; this.meta = r; }
            catch (e) { this.error = e.message; }
            this.loading = false;
        },
        baru() { this.editId = null; this.formError = ''; this.form = { name: '', email: '', telepon: '', role: 'kasir', password: '' }; this.modal = true; },
        ubah(u) { this.editId = u.id; this.formError = ''; this.form = { name: u.name, email: u.email, telepon: u.telepon || '', role: u.role, password: '' }; this.modal = true; },
        async simpan() {
            this.saving = true; this.formError = '';
            try {
                await api(this.editId ? 'PUT' : 'POST', '/pengguna' + (this.editId ? '/' + this.editId : ''), this.form);
                this.modal = false; toast(this.editId ? 'Akun diperbarui' : 'Akun dibuat'); await this.load(this.meta.current_page || 1);
            } catch (e) { this.formError = e.message; }
            this.saving = false;
        },
        async toggleAktif(u) {
            const aktif = !u.aktif;
            if (!aktif && !confirm(`Nonaktifkan akun ${u.name}? Sesi yang sedang berjalan akan diputus.`)) return;
            try { await api('PUT', `/pengguna/${u.id}`, { aktif }); toast(aktif ? 'Akun diaktifkan' : 'Akun dinonaktifkan'); await this.load(this.meta.current_page || 1); }
            catch (e) { toast(e.message, 'err'); }
        },
    }));

    Alpine.data('dashboard', () => ({
        d: null, error: '', loading: true, hari: 30,
        async init() { await this.muat(); },
        async muat() {
            try { this.d = await api('GET', `/dashboard?hari=${this.hari}`); } catch (e) { this.error = e.message; }
            this.loading = false;
        },
        async ganti(h) { this.hari = h; await this.muat(); },
        /* Grafik area: viewBox 700x230, titik x tersebar rata di antara 50..650. */
        titik() {
            const g = this.d.grafik; const n = g.length;
            const maks = Math.max(1, ...g.map((x) => x.total));
            return g.map((x, i) => ({ x: 50 + (n > 1 ? (i * 600) / (n - 1) : 0), y: 200 - (x.total / maks) * 165, g: x, i }));
        },
        garis() {
            const p = this.titik();
            return p.reduce((s, t, i) => {
                if (i === 0) return `M${t.x},${t.y}`;
                const a = p[i - 1]; const mx = (a.x + t.x) / 2;
                return `${s} C${mx},${a.y} ${mx},${t.y} ${t.x},${t.y}`;
            }, '');
        },
        area() { const p = this.titik(); return `${this.garis()} L${p[p.length - 1].x},215 L${p[0].x},215 Z`; },
        dots() {
            const p = this.titik(); const kecil = p.length > 14;
            return p.map((t, i) => `<circle class="dot${i === p.length - 1 ? ' last' : ''}" cx="${t.x}" cy="${t.y}" r="${kecil ? 3.2 : 5}"><title>${tanggal(t.g.tanggal)}: ${rupiah(t.g.total)}</title></circle>`).join('');
        },
        /* Label sumbu-x: harian untuk 7 hari, tiap 5 hari untuk 30 hari. */
        sumbu() {
            const p = this.titik(); const n = p.length; const langkah = n > 14 ? 5 : 1;
            return p.filter((t) => (n - 1 - t.i) % langkah === 0).map((t) => ({
                kiri: (t.x / 700) * 100,
                teks: n > 14 ? new Date(t.g.tanggal + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : new Date(t.g.tanggal + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short' }),
                hariIni: t.i === n - 1,
            }));
        },
        totalGrafik() { return this.d.grafik.reduce((s, g) => s + g.total, 0); },
        rataGrafik() { return this.totalGrafik() / this.d.grafik.length; },
        /* Daftar restok: yang menipis dulu; bila semua aman tampilkan yang paling tipis agar kartu tidak kosong. */
        restok() {
            const s = this.d.stok_menipis;
            const tipis = [...s.item.map((x) => ({ ...x, jenis: 'Menu' })), ...s.bahan.map((x) => ({ ...x, jenis: 'Bahan' }))].sort((a, b) => a.stok - b.stok);
            return (tipis.length ? tipis : s.terendah).slice(0, 5);
        },
        tglPendek() { return new Date().toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }); },
        delta() {
            const k = this.d.hari_ini.kemarin; const t = this.d.hari_ini.total;
            if (!k) return { txt: t ? 'Kemarin belum ada penjualan' : 'Kemarin belum ada penjualan', cls: '' };
            const p = Math.round(((t - k) / k) * 100);
            return { txt: `${p >= 0 ? '▲' : '▼'} ${Math.abs(p)}% dari kemarin (${rupiah(k)})`, cls: p >= 0 ? 'up' : 'down' };
        },
        jamBars() {
            const jam = new Date().getHours();
            // Jendela standar 07.00–22.00; melebar bila ada nota di luar jam itu supaya tidak ada penjualan yang tak terlihat.
            const ada = this.d.hari_ini.per_jam.filter((j) => j.transaksi > 0).map((j) => j.jam);
            const awal = Math.min(7, ...ada); const akhir = Math.max(22, ...ada);
            const pj = this.d.hari_ini.per_jam.filter((j) => j.jam >= awal && j.jam <= akhir);
            const maks = Math.max(1, ...pj.map((j) => j.transaksi));
            return pj.map((j) => ({ ...j, h: j.transaksi ? Math.max(12, (j.transaksi / maks) * 100) : 5, now: j.jam === jam }));
        },
        jamRamai() { const j = [...this.d.hari_ini.per_jam].sort((a, b) => b.transaksi - a.transaksi)[0]; return `${String(j.jam).padStart(2, '0')}.00–${String(j.jam + 1).padStart(2, '0')}.00`; },
        metodeNama: (m) => ({ tunai: 'Tunai', qris: 'QRIS', kartu: 'Kartu', transfer: 'Transfer', online: 'Online' }[m] || m),
        metodeBar() {
            const m = this.d.hari_ini.per_metode; const t = m.reduce((s, x) => s + x.total, 0) || 1;
            return m.map((x) => ({ ...x, pct: Math.max(4, Math.round((x.total / t) * 100)) }));
        },
        mini() { const g = this.d.grafik.slice(-14); const m = Math.max(1, ...g.map((x) => x.total)); return g.map((x) => ({ tgl: x.tanggal, t: x.total, h: Math.max(6, (x.total / m) * 100) })); },
        margin() { const p = this.d.bulan_ini.penjualan; return p > 0 ? Math.round((this.d.bulan_ini.hasil / p) * 100) : 0; },
        aman() { const s = this.d.stok_menipis; const n = s.total_menu + s.total_bahan; return n ? Math.round(((n - s.jumlah) / n) * 100) : 100; },
        menipisSaja() { return this.d.stok_menipis.jumlah - this.d.stok_menipis.habis; },
        lihatStok(status) { sessionStorage.setItem('stokFilter', status); location.hash = '#produk'; },
        pctStok(p) {
            const min = p.stok_minimum !== undefined && p.stok_minimum !== null ? Number(p.stok_minimum) : (this.d.stok_menipis.batas || 5);
            return Math.min(100, Math.max(4, (Number(p.stok) / (min * (Number(p.stok) > min ? 4 : 1))) * 100));
        },
        stokCls: (s, min) => stokState(s, min),
        tipeIcon: (t) => ({ stok_awal: 'package', penjualan: 'cart', pembelian: 'truck', barang_keluar: 'outbox', pesanan_online: 'bag', pesanan_batal: 'ban', void_penjualan: 'ban', opname: 'clipboard' }[t] || 'history'),
        tipeCls: (t) => ({ stok_awal: 'accent', penjualan: 'info', pembelian: 'ok', barang_keluar: 'bad', pesanan_online: 'info', pesanan_batal: 'ok', void_penjualan: 'ok', opname: 'accent' }[t] || 'accent'),
    }));

    Alpine.data('laporan', () => {
        const now = new Date();
        return {
            tahun: now.getFullYear(), bulan: now.getMonth() + 1, data: null, loading: false, error: '',
            subtab: 'labarugi',
            bulanNama: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            rupiah,
            // Opsi bulan dirender belakangan; set ulang setelah render agar <select> menampilkan bulan aktif.
            init() {
                const b = this.bulan;
                this.bulan = 0;
                this.$nextTick(() => { this.bulan = b; this.load(); });
            },
            async load() {
                this.loading = true; this.error = '';
                try { this.data = await api('GET', `/laporan/laba-rugi?tahun=${this.tahun}` + (this.bulan ? `&bulan=${this.bulan}` : '')); }
                catch (e) { this.error = e.message; this.data = null; }
                this.loading = false;
            },
            art: (nama) => katArt(nama),
            async ekspor(format) {
                try {
                    let ep = '/export/laba-rugi';
                    let fname = 'laba-rugi';
                    let params = { format, tahun: this.tahun, bulan: this.bulan || '' };
                    if (this.subtab === 'aruskas') {
                        ep = '/export/arus-kas';
                        fname = 'arus-kas';
                        const thn = this.tahun;
                        const bln = this.bulan ? String(this.bulan).padStart(2, '0') : null;
                        params = {
                            format,
                            dari: bln ? `${thn}-${bln}-01` : `${thn}-01-01`,
                            sampai: bln ? new Date(thn, this.bulan, 0).toISOString().slice(0, 10) : `${thn}-12-31`,
                        };
                    } else if (this.subtab === 'neraca') {
                        ep = '/export/neraca';
                        fname = 'neraca';
                        const thn = this.tahun;
                        params = {
                            format,
                            per_tanggal: new Date(thn, this.bulan || 12, 0).toISOString().slice(0, 10),
                        };
                    }
                    await unduh(ep, params, `${fname}.${format}`);
                    toast(format === 'pdf' ? 'Laporan PDF diunduh' : 'Laporan Excel (CSV) diunduh');
                } catch (e) { toast(e.message, 'err'); }
            },
        };
    });
});
