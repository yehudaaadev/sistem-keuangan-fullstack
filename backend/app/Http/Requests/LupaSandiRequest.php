<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LupaSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email atau nomor telepon akun wajib diisi.',
        ];
    }
}

