<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pengguna = $this->route('pengguna');
        $id = $pengguna instanceof \App\Models\User ? $pengguna->id : $pengguna;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'telepon' => ['nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'required', Rule::in(['kasir', 'admin', 'owner'])],
            'aktif' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ];
    }
}

