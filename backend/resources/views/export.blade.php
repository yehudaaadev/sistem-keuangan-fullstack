<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2a1f16; }
        h1 { font-size: 20px; margin: 0 0 2px; letter-spacing: .5px; }
        .sub { color: #6b5a48; margin-bottom: 14px; }
        .ringkas { margin-bottom: 14px; }
        .ringkas td { padding: 3px 14px 3px 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; background: #2a1f16; color: #f2ead8; padding: 6px 8px; font-size: 10px; text-transform: uppercase; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #d9cdb6; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #2a1f16; border-bottom: 0; }
        .foot { margin-top: 18px; color: #8a7a66; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} — {{ $judul }}</h1>
    <div class="sub">Periode: {{ $periode }} · Dicetak {{ now()->translatedFormat('d F Y H:i') }}</div>

    @if ($ringkasan)
        <table class="ringkas">
            @foreach ($ringkasan as $label => $nilai)
                <tr><td>{{ $label }}</td><td><strong>{{ is_numeric($nilai) ? 'Rp ' . number_format((float) $nilai, 0, ',', '.') : $nilai }}</strong></td></tr>
            @endforeach
        </table>
    @endif

    <table class="data">
        <thead><tr>
            @foreach ($kepala as $i => $k)
                <th @class(['num' => $i > 0 && (in_array($i, $kolomRupiah) || is_numeric($baris->first()[$i] ?? null))])>{{ $k }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse ($baris as $r)
                <tr>
                    @foreach ($r as $i => $v)
                        @php $angka = in_array($i, $kolomRupiah); @endphp
                        <td @class(['num' => $angka || is_numeric($v)])>
                            @if ($angka) Rp {{ number_format((float) $v, 0, ',', '.') }}
                            @else {{ is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') : $v }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($kepala) }}" style="text-align:center;padding:18px">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if ($kolomTotal !== null && $baris->count())
            <tfoot><tr>
                <td colspan="{{ $kolomTotal }}">Total ({{ $baris->count() }} transaksi)</td>
                <td class="num">Rp {{ number_format($total, 0, ',', '.') }}</td>
                @for ($i = $kolomTotal + 1; $i < count($kepala); $i++) <td></td> @endfor
            </tr></tfoot>
        @endif
    </table>
    <div class="foot">Dokumen ini dibuat otomatis oleh Sistem Keuangan {{ config('app.name') }}.</div>
</body>
</html>
