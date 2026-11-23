<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap shift #{{ $shift->id }}</title>
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; width: 300px; margin: 0 auto; color: #000; }
        h1 { font-size: 15px; text-align: center; margin: 8px 0 2px; letter-spacing: 1px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .selisih { font-size: 14px; font-weight: bold; }
        @media print { @page { size: 80mm auto; margin: 4mm; } }
    </style>
</head>
<body>
    <h1>{{ app(\App\Services\PengaturanService::class)->semua()['nama_toko'] }}</h1>
    <div class="center"><strong>REKAP TUTUP SHIFT</strong></div>
    <div class="center">Shift #{{ $shift->id }}</div>
    <hr>
    <div class="row"><span>Kasir</span><span>{{ $shift->user->name }}</span></div>
    <div class="row"><span>Dibuka</span><span>{{ $shift->dibuka_pada->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Ditutup</span><span>{{ $shift->ditutup_pada ? $shift->ditutup_pada->format('d/m/Y H:i') : 'masih berjalan' }}</span></div>
    <hr>
    <div class="row"><span>Transaksi</span><span>{{ $rekap['jumlah_transaksi'] }}</span></div>
    <div class="row"><span>Di tempat / bawa pulang</span><span>{{ $rekap['dine_in'] }} / {{ $rekap['bawa_pulang'] }}</span></div>
    <div class="row"><span>Total penjualan</span><span>{{ number_format($rekap['total_penjualan'], 0, ',', '.') }}</span></div>
    <hr>
    <div class="row"><span>Modal awal</span><span>{{ number_format($shift->modal_awal, 0, ',', '.') }}</span></div>
    <div class="row"><span>+ Penjualan tunai</span><span>{{ number_format($rekap['penjualan_tunai'], 0, ',', '.') }}</span></div>
    @if ($rekap['kas_masuk'] > 0)<div class="row"><span>+ Kas masuk</span><span>{{ number_format($rekap['kas_masuk'], 0, ',', '.') }}</span></div>@endif
    @if ($rekap['kas_keluar'] > 0)<div class="row"><span>- Kas keluar</span><span>{{ number_format($rekap['kas_keluar'], 0, ',', '.') }}</span></div>@endif
    <div class="row"><strong>Kas seharusnya</strong><strong>{{ number_format($rekap['kas_seharusnya'], 0, ',', '.') }}</strong></div>
    @if ($shift->ditutup_pada)
        <div class="row"><span>Uang dihitung</span><span>{{ number_format($shift->uang_dihitung, 0, ',', '.') }}</span></div>
        <div class="row selisih">
            <span>{{ $shift->selisih < 0 ? 'KURANG' : ($shift->selisih > 0 ? 'LEBIH' : 'PAS') }}</span>
            <span>{{ number_format(abs($shift->selisih), 0, ',', '.') }}</span>
        </div>
    @endif
    @if (count($rekap['per_metode']) > 0)
        <hr>
        <div><strong>Per metode bayar</strong></div>
        @foreach ($rekap['per_metode'] as $m)
            <div class="row"><span>{{ strtoupper($m['metode']) }} ({{ $m['jumlah'] }})</span><span>{{ number_format($m['total'], 0, ',', '.') }}</span></div>
        @endforeach
    @endif
    @if ($rekap['jumlah_void'] > 0)
        <div class="row"><span>Void ({{ $rekap['jumlah_void'] }})</span><span>{{ number_format($rekap['nilai_void'], 0, ',', '.') }}</span></div>
    @endif
    @if ($shift->kas->count())
        <hr>
        <div><strong>Kas masuk/keluar</strong></div>
        @foreach ($shift->kas as $k)
            <div class="row"><span>{{ $k->tipe === 'masuk' ? '+' : '-' }} {{ $k->keterangan }}</span><span>{{ number_format($k->jumlah, 0, ',', '.') }}</span></div>
        @endforeach
    @endif
    @if ($rekap['terlaris'])
        <hr>
        <div><strong>Terlaris shift ini</strong></div>
        @foreach ($rekap['terlaris'] as $t)
            <div class="row"><span>{{ $t['nama'] }}</span><span>{{ rtrim(rtrim(number_format($t['qty'], 2, '.', ''), '0'), '.') }}</span></div>
        @endforeach
    @endif
    @if ($shift->catatan)
        <hr>
        <div>Catatan: {{ $shift->catatan }}</div>
    @endif
    <hr>
    <div class="center">Tanda tangan kasir: ______________</div>
</body>
</html>
