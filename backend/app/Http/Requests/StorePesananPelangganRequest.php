<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePesananPelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catatan' => ['nullable', 'string', 'max:300'],
            'voucher' => ['nullable', 'string', 'max:30'],
            'tipe' => ['required', Rule::in(['dine_in', 'bawa_pulang'])],
            'meja' => ['nullable', 'required_if:tipe,dine_in', 'string', 'max:10'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.produk_id' => ['required', Rule::exists('produk', 'id')->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:99'],
            'items.*.opsi' => ['nullable', 'array'],
            'items.*.opsi.*' => ['integer'],
        ];
    }
}

