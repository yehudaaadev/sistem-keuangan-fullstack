<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BukaShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modal_awal' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}

