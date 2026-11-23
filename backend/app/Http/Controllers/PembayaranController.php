<?php

namespace App\Http\Controllers;

use App\Services\Pembayaran\PembayaranGateway;
use App\Services\PesananService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint publik yang dipanggil gateway pembayaran (webhook). Keaslian dicek lewat tanda tangan, bukan login. */
class PembayaranController extends Controller
{
    public function notifikasi(Request $request, PembayaranGateway $gateway, PesananService $pesanan): JsonResponse
    {
        $hasil = $gateway->bacaNotifikasi($request->all());

        if ($hasil === null) {
            return response()->json(['message' => 'Notifikasi tidak sah.'], 403);
        }

        match ($hasil['hasil']) {
            'lunas' => $pesanan->tandaiDibayar($hasil['kode'], $hasil['ref']),
            'gagal' => $pesanan->tandaiGagal($hasil['kode']),
            default => null,
        };

        return response()->json(['message' => 'ok']);
    }
}
