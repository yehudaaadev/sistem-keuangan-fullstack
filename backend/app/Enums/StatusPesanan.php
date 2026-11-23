<?php

namespace App\Enums;

enum StatusPesanan: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Dibayar = 'dibayar';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';
    case Kedaluwarsa = 'kedaluwarsa';
    /** Pembayaran masuk setelah pesanan batal/kedaluwarsa: uang harus dikembalikan manual. */
    case PerluRefund = 'perlu_refund';

    /** Langkah berikutnya yang boleh dilakukan staf, atau null bila tidak ada. */
    public function berikutnya(): ?self
    {
        return match ($this) {
            self::Dibayar => self::Diproses,
            self::Diproses => self::Siap,
            self::Siap => self::Selesai,
            default => null,
        };
    }
}
