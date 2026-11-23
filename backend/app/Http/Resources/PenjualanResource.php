<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'no_nota' => $this->no_nota,
            'shift_id' => $this->shift_id,
            'user_id' => $this->user_id,
            'pembeli_id' => $this->pembeli_id,
            'tanggal' => $this->tanggal,
            'sumber' => $this->sumber,
            'tipe' => $this->tipe,
            'meja' => $this->meja,
            'subtotal' => $this->subtotal,
            'diskon' => $this->diskon,
            'voucher_kode' => $this->voucher_kode,
            'service' => $this->service,
            'service_persen' => $this->service_persen,
            'pajak' => $this->pajak,
            'pajak_persen' => $this->pajak_persen,
            'total' => $this->total,
            'metode' => $this->metode,
            'bayar' => $this->bayar,
            'kembalian' => $this->kembalian,
            'catatan' => $this->catatan,
            'nomor_antrean' => $this->nomor_antrean,
            'void_at' => $this->void_at,
            'void_oleh' => $this->void_oleh,
            'void_alasan' => $this->void_alasan,
            'pembeli' => $this->whenLoaded('pembeli'),
            'user' => $this->whenLoaded('user'),
            'detail' => PenjualanDetailResource::collection($this->whenLoaded('detail')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

