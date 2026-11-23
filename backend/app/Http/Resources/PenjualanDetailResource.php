<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $bisaLihatModal = $user && $user->hasRole(Role::Admin, Role::Owner);

        $data = [
            'id' => $this->id,
            'penjualan_id' => $this->penjualan_id,
            'produk_id' => $this->produk_id,
            'nama_produk' => $this->nama_produk,
            'qty' => $this->qty,
            'harga_jual' => $this->harga_jual,
            'subtotal' => $this->subtotal,
            'kustomisasi' => $this->kustomisasi,
            'opsi_ringkas' => $this->opsi_ringkas,
            'produk' => $this->whenLoaded('produk', fn () => new ProdukResource($this->produk)),
        ];

        if ($bisaLihatModal) {
            $data['harga_modal'] = $this->harga_modal;
        }

        return $data;
    }
}

