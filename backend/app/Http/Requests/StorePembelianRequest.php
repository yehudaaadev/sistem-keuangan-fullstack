<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['required', Rule::exists('distributor', 'id')->whereNull('deleted_at')],
            'tanggal' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_baku_id' => ['nullable', 'required_without:items.*.produk_id', Rule::exists('bahan_baku', 'id')->whereNull('deleted_at')],
            'items.*.produk_id' => ['nullable', 'required_without:items.*.bahan_baku_id', Rule::exists('produk', 'id')->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_beli' => ['required', 'numeric', 'min:0'],
        ];
    }
}

