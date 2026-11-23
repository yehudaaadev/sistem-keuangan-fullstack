<?php

namespace App\Http\Requests;

use App\Services\PengaturanService;
use Illuminate\Foundation\Http\FormRequest;

class StoreBebanOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kategori' => ['required', 'string', 'in:listrik,air,gaji,sewa,internet,perawatan,lainnya'],
            'nama' => ['required', 'string', 'max:150'],
            'jumlah' => ['required', 'numeric', 'gt:0'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'kode_akun' => ['nullable', 'string', 'max:20'],
            'bukti_transaksi' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function passedValidation(): void
    {
        app(PengaturanService::class)->cekTutupBuku($this->input('tanggal'));
    }
}

