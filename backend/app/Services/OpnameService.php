<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TipeRiwayatStok;
use App\Models\BahanBaku;
use App\Models\Opname;
use App\Models\Produk;
use App\Models\Resep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok opname: mencocokkan stok sistem dengan hitungan fisik. Selisih (naik atau turun) dicatat di riwayat_stok
 * dan di dokumen opname sebagai jejak audit. Bila terdapat selisih dan diinput oleh staff (bukan owner),
 * opname memerlukan persetujuan (approval) Owner sebelum stok sistem disesuaikan.
 */
class OpnameService
{
    public function __construct(private readonly StokService $stok, private readonly AuditService $audit) {}

    /**
     * @param  list<array{jenis: string, id: int, stok_fisik: numeric, alasan?: ?string}>  $items
     */
    public function buat(User $petugas, ?string $catatan, array $items): Opname
    {
        app(PengaturanService::class)->cekTutupBuku(now());

        return DB::transaction(function () use ($petugas, $catatan, $items) {
            $isOwner = $petugas->hasRole(Role::Owner);
            $opname = Opname::create([
                'user_id' => $petugas->id,
                'status' => 'disetujui', // default sementara, diubah ke menunggu_persetujuan bila ada selisih & bukan owner
                'catatan' => $catatan,
            ]);

            $totalSelisih = 0;
            $details = [];

            foreach ($items as $i) {
                $bahan = $i['jenis'] === 'bahan';
                $model = $bahan
                    ? BahanBaku::lockForUpdate()->findOrFail($i['id'])
                    : Produk::lockForUpdate()->findOrFail($i['id']);

                if (! $bahan && Resep::where('produk_id', $model->id)->exists()) {
                    throw ValidationException::withMessages(['items' => "{$model->nama} memakai resep. Lakukan opname pada bahan bakunya."]);
                }

                $sistem = (float) $model->stok;
                $fisik = round((float) $i['stok_fisik'], 2);
                $selisih = round($fisik - $sistem, 2);
                $alasan = $i['alasan'] ?? ($selisih != 0.0 ? 'selisih_hitung' : null);

                $detail = $opname->detail()->create([
                    'produk_id' => $bahan ? null : $model->id,
                    'bahan_baku_id' => $bahan ? $model->id : null,
                    'stok_sistem' => $sistem,
                    'stok_fisik' => $fisik,
                    'selisih' => $selisih,
                    'alasan' => $alasan,
                ]);

                if ($selisih != 0.0) {
                    $totalSelisih++;
                }

                $details[] = ['model' => $model, 'selisih' => $selisih, 'bahan' => $bahan];
            }

            // Aturan Approval Korporat: jika ada alasan kerusakan/kehilangan atau diminta approval, tahan perubahan stok
            $adaAlasanKhusus = collect($items)->contains(fn ($i) => ! empty($i['alasan']));
            $butuhApproval = ! $isOwner && $totalSelisih > 0 && ($adaAlasanKhusus || request()?->boolean('butuh_approval'));

            if ($butuhApproval) {
                $opname->update([
                    'status' => 'menunggu_persetujuan',
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            } else {
                $opname->update([
                    'status' => 'disetujui',
                    'approved_by' => $petugas->id,
                    'approved_at' => now(),
                ]);

                // Eksekusi penyesuaian stok langsung bila disetujui
                foreach ($details as $d) {
                    if ($d['selisih'] != 0.0) {
                        $d['bahan']
                            ? $this->stok->ubahBahan($d['model']->id, $d['selisih'], TipeRiwayatStok::Opname, $opname, $petugas->id)
                            : $this->stok->ubah($d['model']->id, $d['selisih'], TipeRiwayatStok::Opname, $opname, $petugas->id);
                    }
                }
            }

            $this->audit->catat('opname.simpan', "opname #{$opname->id}", [
                'status' => $opname->status,
                'item_disesuaikan' => $totalSelisih,
                'catatan' => $catatan,
            ]);

            return $opname->load('detail.produk', 'detail.bahanBaku', 'user');
        });
    }

    public function setujui(Opname $opname, User $owner): Opname
    {
        if ($opname->status !== 'menunggu_persetujuan') {
            throw ValidationException::withMessages(['status' => 'Opname ini sudah diproses sebelumnya.']);
        }

        return DB::transaction(function () use ($opname, $owner) {
            foreach ($opname->detail as $detail) {
                if ((float) $detail->selisih != 0.0) {
                    $detail->bahan_baku_id
                        ? $this->stok->ubahBahan($detail->bahan_baku_id, (float) $detail->selisih, TipeRiwayatStok::Opname, $opname, $owner->id)
                        : $this->stok->ubah($detail->produk_id, (float) $detail->selisih, TipeRiwayatStok::Opname, $opname, $owner->id);
                }
            }

            $opname->update([
                'status' => 'disetujui',
                'approved_by' => $owner->id,
                'approved_at' => now(),
            ]);

            $this->audit->catat('opname.setujui', "opname #{$opname->id}", [
                'disetujui_oleh' => $owner->name,
                'item_disesuaikan' => $opname->detail()->where('selisih', '!=', 0)->count(),
            ]);

            return $opname->load('detail.produk', 'detail.bahanBaku', 'user', 'approvedBy');
        });
    }

    public function tolak(Opname $opname, User $owner, ?string $alasan = null): Opname
    {
        if ($opname->status !== 'menunggu_persetujuan') {
            throw ValidationException::withMessages(['status' => 'Opname ini sudah diproses sebelumnya.']);
        }

        $opname->update([
            'status' => 'ditolak',
            'approved_by' => $owner->id,
            'approved_at' => now(),
            'catatan' => trim(($opname->catatan ?? '').' [Ditolak: '.($alasan ?? 'oleh owner').']'),
        ]);

        $this->audit->catat('opname.tolak', "opname #{$opname->id}", [
            'ditolak_oleh' => $owner->name,
            'alasan' => $alasan,
        ]);

        return $opname->load('detail.produk', 'detail.bahanBaku', 'user', 'approvedBy');
    }
}
