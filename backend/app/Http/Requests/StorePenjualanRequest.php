<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePenjualanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pembeli_id' => ['nullable', Rule::exists('pembeli', 'id')->whereNull('deleted_at')],
            'tanggal' => ['nullable', 'date'],
            'tipe' => ['nullable', Rule::in(['dine_in', 'bawa_pulang'])],
            'meja' => ['nullable', 'string', 'max:10'],
            'metode' => ['nullable', Rule::in(['tunai', 'qris', 'kartu', 'transfer'])],
            'voucher' => ['nullable', 'string', 'max:30'],
            'tukar_poin' => ['nullable', 'integer', 'min:0'],
            'bayar' => ['required_unless:metode,qris,kartu,transfer', 'nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', Rule::exists('produk', 'id')->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.opsi' => ['nullable', 'array'],
            'items.*.opsi.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Daftar menu pesanan wajib diisi.',
            'items.*.produk_id.required' => 'Menu produk harus dipilih.',
            'items.*.qty.gt' => 'Jumlah pesanan harus lebih dari 0.',
        ];
    }
}

