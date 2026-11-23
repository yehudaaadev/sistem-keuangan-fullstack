<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdukResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $bisaLihatModal = $user && $user->hasRole(Role::Admin, Role::Owner);

        $data = [
            'id' => $this->id,
            'kategori_id' => $this->kategori_id,
            'nama' => $this->nama,
            'satuan' => $this->satuan,
            'harga_jual' => $this->harga_jual,
            'stok' => $this->stok,
            'stok_minimum' => $this->stok_minimum,
            'tanggal_expired' => $this->tanggal_expired?->format('Y-m-d'),
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'kategori' => $this->whenLoaded('kategori'),
            'opsi_grup' => $this->whenLoaded('opsiGrup'),
            'ulasan_avg_bintang' => $this->ulasan_avg_bintang,
            'ulasan_count' => $this->ulasan_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Kerahasiaan harga modal & resep terlindungi secara formal di Resource layer:
        if ($bisaLihatModal) {
            $data['harga_beli'] = $this->harga_beli;
            $data['modal_resep'] = $this->modal_resep;
            if ($this->relationLoaded('resep')) {
                $data['resep'] = $this->resep;
            }
        }

        return $data;
    }
}

