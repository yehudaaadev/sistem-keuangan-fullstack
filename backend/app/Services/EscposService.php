<?php

namespace App\Services;

use App\Models\Penjualan;

class EscposService
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    /**
     * Menghasilkan teks struk rapi berlebar 32 karakter (standar printer thermal 58mm).
     */
    public function formatTeks(Penjualan $p, int $width = 32): string
    {
        $p->loadMissing(['detail.produk', 'pembeli', 'user']);
        $toko = $this->pengaturan->get('nama_toko') ?: 'KEDAI KOPI';
        $alamat = $this->pengaturan->get('alamat') ?: ($this->pengaturan->get('alamat_toko') ?: '');
        $telp = $this->pengaturan->get('telepon_toko') ?: ($this->pengaturan->get('telepon') ?: '');
        $footer = $this->pengaturan->get('footer_nota') ?: 'Terima kasih, sampai jumpa!';
        $pajakPersen = $this->pengaturan->persen('pajak_persen');
        $servicePersen = $this->pengaturan->persen('service_persen');

        $lines = [];
        $lines[] = $this->center(strtoupper($toko), $width);
        if (! empty($alamat)) {
            $lines[] = $this->center($alamat, $width);
        }
        if (! empty($telp)) {
            $lines[] = $this->center('Telp/WA: ' . $telp, $width);
        }
        $lines[] = str_repeat('=', $width);

        $lines[] = $this->duaKolom('Nota:', $p->no_nota ?: '-', $width);
        $lines[] = $this->duaKolom('Waktu:', $p->tanggal->translatedFormat('d/m/y H:i'), $width);
        $lines[] = $this->duaKolom('Kasir:', $p->user?->name ?: '-', $width);

        if ($p->nomor_antrean) {
            $lines[] = $this->duaKolom('Antrean:', '#' . $p->nomor_antrean, $width);
        }
        if ($p->meja) {
            $lines[] = $this->duaKolom('Meja:', $p->meja, $width);
        }
        if ($p->pembeli?->nama) {
            $lines[] = $this->duaKolom('Pembeli:', $p->pembeli->nama, $width);
        }

        $lines[] = str_repeat('-', $width);

        foreach ($p->detail as $item) {
            $nama = $item->produk?->nama ?: ($item->nama_produk ?? 'Item');
            $opsiList = is_array($item->opsi) ? $item->opsi : [];
            $opsi = collect($opsiList)
                ->filter(fn ($o) => ! (is_array($o) && ! empty($o['bawaan']) && (float) ($o['harga'] ?? 0) == 0))
                ->map(fn ($o) => is_array($o) ? ($o['nama'] ?? '') : (string) $o)
                ->filter()
                ->join(', ');

            $lines[] = $nama;
            if ($opsi) {
                $lines[] = ' (' . $opsi . ')';
            }
            $qtyHarga = (float) $item->qty . ' x ' . number_format((float) $item->harga_jual, 0, ',', '.');
            $sub = number_format((float) $item->subtotal, 0, ',', '.');
            $lines[] = $this->duaKolom('  ' . $qtyHarga, $sub, $width);
        }

        $lines[] = str_repeat('-', $width);
        $totalQty = $p->detail->sum('qty');
        $lines[] = $this->duaKolom('Total Qty:', (float) $totalQty . ' item (' . $p->detail->count() . ' menu)', $width);
        $lines[] = $this->duaKolom('Subtotal:', number_format((float) $p->subtotal, 0, ',', '.'), $width);

        if ((float) $p->diskon > 0) {
            $lines[] = $this->duaKolom('Diskon:', '-' . number_format((float) $p->diskon, 0, ',', '.'), $width);
        }
        if ((float) $p->service > 0) {
            $sLabel = 'Service' . ($servicePersen > 0 ? " ({$servicePersen}%)" : '') . ':';
            $lines[] = $this->duaKolom($sLabel, number_format((float) $p->service, 0, ',', '.'), $width);
        }
        if ((float) $p->pajak > 0) {
            $pLabel = 'Pajak PB1' . ($pajakPersen > 0 ? " ({$pajakPersen}%)" : '') . ':';
            $lines[] = $this->duaKolom($pLabel, number_format((float) $p->pajak, 0, ',', '.'), $width);
        }

        $lines[] = str_repeat('=', $width);
        $lines[] = $this->duaKolom('TOTAL:', 'Rp ' . number_format((float) $p->total, 0, ',', '.'), $width);
        $lines[] = $this->duaKolom('Bayar (' . strtoupper($p->metode) . '):', number_format((float) $p->bayar, 0, ',', '.'), $width);
        if ($p->metode === 'tunai') {
            $lines[] = $this->duaKolom('Kembalian:', number_format((float) $p->kembalian, 0, ',', '.'), $width);
        }
        if ($p->catatan) {
            $lines[] = $this->duaKolom('Catatan:', $p->catatan, $width);
        }
        if ($p->void_at) {
            $lines[] = str_repeat('-', $width);
            $lines[] = $this->center('DIBATALKAN (VOID)', $width);
            if ($p->void_alasan) {
                $lines[] = 'Alasan: ' . $p->void_alasan;
            }
        }

        $lines[] = str_repeat('=', $width);
        $lines[] = $this->center($footer, $width);
        $lines[] = $this->center('* ' . ($p->no_nota ?: '-') . ' *', $width);
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Menghasilkan binary buffer ESC/POS siap kirim ke thermal printer (USB/Bluetooth/Network).
     */
    public function formatRawEscpos(Penjualan $p): string
    {
        $esc = "\x1b";
        $gs = "\x1d";

        $init = $esc . "@";           // Initialize printer
        $cut = $esc . "d\x03" . $gs . "V\x01"; // Feed 3 lines & cut paper

        $text = $this->formatTeks($p, 32);

        $barcodeCmd = '';
        if ($p->no_nota) {
            $alignCenter = $esc . "a\x01";
            $barcodeCmd = "\n" . $alignCenter . $gs . "h\x28" . $gs . "w\x02" . $gs . "H\x00" . $gs . "k\x04" . '*' . strtoupper($p->no_nota) . '*' . "\x00\n";
        }

        return $init . $text . $barcodeCmd . "\n" . $cut;
    }

    private function center(string $text, int $width): string
    {
        $len = strlen($text);
        if ($len >= $width) {
            return substr($text, 0, $width);
        }
        $pad = (int) floor(($width - $len) / 2);

        return str_repeat(' ', $pad) . $text;
    }

    private function duaKolom(string $kiri, string $kanan, int $width): string
    {
        $space = $width - strlen($kiri) - strlen($kanan);
        if ($space < 1) {
            return $kiri . ' ' . $kanan;
        }

        return $kiri . str_repeat(' ', $space) . $kanan;
    }
}
