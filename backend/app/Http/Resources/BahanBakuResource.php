<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BahanBakuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'satuan' => $this->satuan,
            'stok' => $this->stok,
            'harga_terakhir' => $this->harga_terakhir,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

