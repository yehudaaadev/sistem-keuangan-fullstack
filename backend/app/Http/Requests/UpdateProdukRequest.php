<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProdukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kategori_id' => ['sometimes', Rule::exists('kategori_produk', 'id')->whereNull('deleted_at')],
            'sku' => ['nullable', 'string', 'max:30'],
            'nama' => ['sometimes', 'string', 'max:255'],
            'satuan' => ['sometimes', 'string', 'max:20'],
            'harga_beli' => ['sometimes', 'numeric', 'min:0'],
            'harga_jual' => ['sometimes', 'numeric', 'min:0'],
            'stok_minimum' => ['nullable', 'numeric', 'min:0'],
            'tanggal_expired' => ['nullable', 'date'],
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'resep' => ['nullable', 'array', 'max:30'],
            'resep.*.bahan_baku_id' => ['required', 'distinct', Rule::exists('bahan_baku', 'id')->whereNull('deleted_at')],
            'resep.*.jumlah' => ['required', 'numeric', 'gt:0'],
            'opsi_grup_ids' => ['nullable', 'array'],
            'opsi_grup_ids.*' => ['integer', Rule::exists('opsi_grup', 'id')->whereNull('deleted_at')],
        ];
    }
}

