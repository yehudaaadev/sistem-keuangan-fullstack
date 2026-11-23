<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KasShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe' => ['required', 'in:masuk,keluar'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'keterangan' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}

