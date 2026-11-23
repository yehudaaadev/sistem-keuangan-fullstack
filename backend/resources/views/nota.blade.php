<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota {{ $penjualan->no_nota }}</title>
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; width: 280px; margin: 0 auto; color: #000; }
        h1 { font-size: 15px; text-align: center; margin: 8px 0 2px; letter-spacing: 1px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .opsi { padding-left: 10px; font-size: 11px; }
        .antrean { text-align: center; margin: 8px 0 2px; }
        .antrean small { display: block; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; }
        .antrean b { font-size: 34px; letter-spacing: 3px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        @media print { @page { size: 80mm auto; margin: 4mm; } }
        .void { text-align: center; border: 2px solid #000; font-weight: bold; padding: 4px; margin: 6px 0; letter-spacing: 2px; }
    </style>
</head>
<body>
    @php
        $peng = app(\App\Services\PengaturanService::class)->semua();
        $telpToko = $peng['telepon_toko'] ?? ($peng['telepon'] ?? '');
    @endphp
    <h1>{{ $peng['nama_toko'] }}</h1>
    @if ($peng['alamat'])<div class="center">{{ $peng['alamat'] }}</div>@endif
    @if (!empty($telpToko))<div class="center">Telp/WA: {{ $telpToko }}</div>@endif
    <div class="center">{{ $penjualan->no_nota }}</div>
    <div class="center">{{ $penjualan->tanggal->format('d/m/Y H:i') }}</div>

    @if ($penjualan->void_at)
        <div class="void">DIBATALKAN (VOID)</div>
    @endif
    @if ($penjualan->nomor_antrean)
        <div class="antrean"><small>No. antrean</small><b>{{ $penjualan->nomor_antrean }}</b></div>
    @endif
    <div class="center">
        {{ $penjualan->tipe === 'dine_in' ? 'Makan di tempat' : 'Bawa pulang' }}@if ($penjualan->meja) &middot; Meja {{ $penjualan->meja }}@endif
    </div>
    <hr>
    <div>{{ $penjualan->sumber === 'online' ? 'Pesanan online' : 'Kasir : '.$penjualan->user->name }}</div>
    <div>Pembeli: {{ $penjualan->pembeli->nama ?? 'Umum' }}</div>
    <hr>
    @foreach ($penjualan->detail as $item)
        <div>{{ $item->produk->nama }}</div>
        @foreach ($item->opsi ?? [] as $o)
            <div class="opsi">+ {{ $o['grup'] }}: {{ $o['nama'] }}@if (($o['harga'] ?? 0) > 0) (+{{ number_format($o['harga'], 0, ',', '.') }})@endif</div>
        @endforeach
        <div class="row">
            <span>{{ rtrim(rtrim($item->qty, '0'), '.') }} {{ $item->produk->satuan }} x {{ number_format($item->harga_jual, 0, ',', '.') }}</span>
            <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
        </div>
    @endforeach
    <hr>
    @php
        $rp = fn ($n) => number_format($n, 0, ',', '.');
        $totalQty = $penjualan->detail->sum('qty');
        $pajakPersen = (float) ($peng['pajak_persen'] ?? 0);
        $servicePersen = (float) ($peng['service_persen'] ?? 0);
    @endphp
    <div class="row"><span>Total Qty</span><span>{{ rtrim(rtrim(number_format((float) $totalQty, 2, '.', ''), '0'), '.') }} item ({{ $penjualan->detail->count() }} menu)</span></div>
    @if ((float) $penjualan->diskon > 0 || (float) $penjualan->service > 0 || (float) $penjualan->pajak > 0)
        <div class="row"><span>Subtotal</span><span>{{ $rp($penjualan->subtotal) }}</span></div>
        @if ((float) $penjualan->diskon > 0)<div class="row"><span>Diskon {{ $penjualan->voucher_kode }}</span><span>-{{ $rp($penjualan->diskon) }}</span></div>@endif
        @if ((float) $penjualan->service > 0)<div class="row"><span>Service @if($servicePersen > 0)({{ $peng['service_persen'] }}%)@endif</span><span>{{ $rp($penjualan->service) }}</span></div>@endif
        @if ((float) $penjualan->pajak > 0)<div class="row"><span>Pajak PB1 @if($pajakPersen > 0)({{ $peng['pajak_persen'] }}%)@endif</span><span>{{ $rp($penjualan->pajak) }}</span></div>@endif
    @endif
    <div class="row"><strong>Total</strong><strong>{{ $rp($penjualan->total) }}</strong></div>
    <div class="row"><span>Metode</span><span>{{ strtoupper($penjualan->metode) }}</span></div>
    @if ($penjualan->metode === 'tunai')
        <div class="row"><span>Bayar</span><span>{{ $rp($penjualan->bayar) }}</span></div>
        <div class="row"><span>Kembali</span><span>{{ $rp($penjualan->kembalian) }}</span></div>
    @endif
    @if ($penjualan->catatan)
        <div class="row"><span>Catatan</span><span>{{ $penjualan->catatan }}</span></div>
    @endif
    @if ($penjualan->void_at)
        <hr>
        <div>Void: {{ $penjualan->void_alasan }}</div>
    @endif
    <hr>
    <div class="center">{{ $peng['footer_nota'] ?: 'Terima kasih, sampai jumpa!' }}</div>
    @php
        $map = [
            '0' => 'nnnwwnwnn', '1' => 'wnnnwnnnw', '2' => 'nnwnwnnnw', '3' => 'wnwnwnnnn',
            '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
            '8' => 'wnnwnnwnn', '9' => 'nnwnnnwnn', 'A' => 'wnnnnnwnw', 'B' => 'nnwnnnwnw',
            'C' => 'wnwnnnwnn', 'D' => 'nnnnwnwnw', 'E' => 'wnnnwnwnn', 'F' => 'nnwnwnwnn',
            'G' => 'nnnnnnwwn', 'H' => 'wnnnnnwwn', 'I' => 'nnwnnnwwn', 'J' => 'nnnnwnwwn',
            'K' => 'wnnnnnnnw', 'L' => 'nnwnnnnnw', 'M' => 'wnwnnnnnn', 'N' => 'nnnnwnnnw',
            'O' => 'wnnnwnnnn', 'P' => 'nnwnwnnnn', 'Q' => 'nnnnnnwnw', 'R' => 'wnnnnnwnn',
            'S' => 'nnwnnnwnn', 'T' => 'nnnnwnwnn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
            'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
            '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
        ];
        $barcodeText = '*' . strtoupper(trim($penjualan->no_nota)) . '*';
        $narrow = 1.1;
        $wide = 2.6;
        $bHeight = 24;
        $x = 0;
        $rects = '';
        for ($i = 0; $i < strlen($barcodeText); $i++) {
            $char = $barcodeText[$i];
            if (! isset($map[$char])) continue;
            $pat = $map[$char];
            for ($p = 0; $p < 9; $p++) {
                $isBar = ($p % 2 === 0);
                $isWide = ($pat[$p] === 'w');
                $w = $isWide ? $wide : $narrow;
                if ($isBar) {
                    $rects .= '<rect x="' . round($x, 2) . '" y="0" width="' . round($w, 2) . '" height="' . $bHeight . '" fill="#000" />';
                }
                $x += $w;
            }
            $x += $narrow;
        }
    @endphp
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ round($x, 2) }} {{ $bHeight }}" width="{{ min(round($x, 2), 220) }}" height="{{ $bHeight }}" style="display:block;margin:6px auto 2px;">
        {!! $rects !!}
    </svg>
    <div class="center" style="font-size: 10px; letter-spacing: 1px; margin-bottom: 6px;">{{ $penjualan->no_nota }}</div>
</body>
</html>
