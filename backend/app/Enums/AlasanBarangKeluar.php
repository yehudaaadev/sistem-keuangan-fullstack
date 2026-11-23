<?php

namespace App\Enums;

enum AlasanBarangKeluar: string
{
    case Rusak = 'rusak';
    case Expired = 'expired';
    case Diberikan = 'diberikan';
    case Lainnya = 'lainnya';
}
