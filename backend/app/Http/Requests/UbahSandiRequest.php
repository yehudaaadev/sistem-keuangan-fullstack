<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UbahSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sandi_lama' => ['required', 'string'],
            'sandi_baru' => ['required', 'string', 'min:8', 'max:100', 'different:sandi_lama'],
        ];
    }

    public function messages(): array
    {
        return [
            'sandi_lama.required' => 'Sandi saat ini wajib diisi.',
            'sandi_baru.required' => 'Sandi baru wajib diisi.',
            'sandi_baru.min' => 'Sandi baru minimal 8 karakter.',
            'sandi_baru.different' => 'Sandi baru tidak boleh sama dengan sandi lama.',
        ];
    }
}

