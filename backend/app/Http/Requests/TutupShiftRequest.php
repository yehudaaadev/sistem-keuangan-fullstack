<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TutupShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uang_dihitung' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}

