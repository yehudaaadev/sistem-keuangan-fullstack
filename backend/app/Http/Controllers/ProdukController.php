<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TipeRiwayatStok;
use App\Models\Produk;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProdukController extends Controller
{
    /** Stok di bawah atau sama dengan angka ini dianggap menipis. */
    public const STOK_MENIPIS = 5;

    /** Relasi yang selalu dimuat: kategori, resep (untuk perkiraan modal), dan kelompok kustomisasi. */
    private const MUAT = ['kategori', 'resep.bahan', 'opsiGrup.pilihan'];

    public function __construct(private readonly StokService $stok) {}

    public function index(Request $request): JsonResponse
    {
        $produk = Produk::with(self::MUAT)
            ->withAvg('ulasan', 'bintang')
            ->withCount('ulasan')
            ->when($request->boolean('nonaktif'), fn ($q) => $q->onlyTrashed())
            ->when($request->query('search'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->when($request->query('kategori_id'), fn ($q, $id) => $q->where('kategori_id', $id))
            ->when($request->boolean('stok_tersedia'), fn ($q) => $q->where('stok', '>', 0))
            ->when($request->query('stok_status') === 'habis', fn ($q) => $q->where('stok', '<=', 0))
            ->when($request->query('stok_status') === 'menipis', fn ($q) => $q->where('stok', '>', 0)->where(fn ($w) => $w->where('stok', '<=', self::STOK_MENIPIS)->orWhereColumn('stok', '<=', 'stok_minimum')))
            ->orderBy('nama')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($produk);
    }

    /** Stok awal (opsional) dicatat di riwayat_stok; perubahan berikutnya hanya lewat transaksi. */
    public function store(\App\Http\Requests\StoreProdukRequest $request): JsonResponse
    {
        $data = $request->validated();
        $stokAwal = (float) ($request->input('stok') ?: 0);

        $produk = DB::transaction(function () use ($data, $stokAwal, $request) {
            $data['image_path'] = $this->simpanGambar($request->file('gambar'));
            if (empty($data['sku']) && ! empty($data['kategori_id'])) {
                $prefix = \App\Models\KategoriProduk::whereKey($data['kategori_id'])->value('kode') ?: 'PRD';
                $nextId = (int) (\App\Models\Produk::withTrashed()->max('id') ?? 0) + 1;
                $data['sku'] = $prefix.'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
            }
            $produk = Produk::create($data);
            $this->sinkronRelasi($request, $produk);

            // Menu beresep tidak punya stok sendiri (dihitung dari bahan), jadi stok awal hanya untuk menu tanpa resep.
            if ($stokAwal > 0 && $produk->resep()->doesntExist()) {
                $this->stok->ubah($produk->id, $stokAwal, TipeRiwayatStok::StokAwal, $produk, $request->user()->id);
            }

            app(\App\Services\AuditService::class)->catat('produk.buat', $produk->sku ?: $produk->nama, [
                'nama' => $produk->nama,
                'harga_jual' => (float) $produk->harga_jual,
            ]);

            return $produk;
        });

        return response()->json($produk->refresh()->load(self::MUAT), 201);
    }

    public function show(Produk $produk): JsonResponse
    {
        return response()->json(new \App\Http\Resources\ProdukResource($produk->load(self::MUAT)));
    }

    public function update(\App\Http\Requests\UpdateProdukRequest $request, Produk $produk): JsonResponse
    {
        $data = $request->validated();

        if (! $request->user()->hasRole(Role::Owner) && isset($data['harga_jual']) && (float) $data['harga_jual'] !== (float) $produk->harga_jual) {
            throw ValidationException::withMessages([
                'harga_jual' => 'Hanya Owner yang berwenang mengubah harga jual produk.',
            ]);
        }

        if ($request->hasFile('gambar')) {
            $this->hapusGambar($produk->image_path);
            $data['image_path'] = $this->simpanGambar($request->file('gambar'));
        }

        $harga = [(string) $produk->harga_jual, (string) $produk->harga_beli];
        DB::transaction(function () use ($produk, $data, $request) {
            $produk->update($data);
            if ($request->boolean('sync_relasi')) {
                $this->sinkronRelasi($request, $produk);
            }
        });

        if ($harga !== [(string) $produk->refresh()->harga_jual, (string) $produk->harga_beli]) {
            app(\App\Services\AuditService::class)->catat('produk.harga', $produk->nama, [
                'harga_jual' => [$harga[0], (string) $produk->harga_jual], 'harga_beli' => [$harga[1], (string) $produk->harga_beli],
            ]);
        }

        return response()->json($produk->load(self::MUAT));
    }

    /** Menonaktifkan produk (soft delete): tampil hilang dari kasir, riwayat transaksi lama tetap utuh. */
    public function destroy(Produk $produk): JsonResponse
    {
        $produk->delete();

        return response()->json(null, 204);
    }

    public function aktifkan(int $id): JsonResponse
    {
        $produk = Produk::onlyTrashed()->findOrFail($id);
        $produk->restore();

        return response()->json($produk->load(self::MUAT));
    }

    /**
     * Menyimpan resep dan kelompok kustomisasi menu. Pada update, hanya diproses bila ada sync_relasi=1
     * (supaya daftar kosong pada FormData tetap bisa berarti "hapus semua").
     */
    private function sinkronRelasi(Request $request, Produk $produk): void
    {
        $data = $request->validate([
            'resep' => ['nullable', 'array', 'max:30'],
            'resep.*.bahan_baku_id' => ['required', 'distinct', Rule::exists('bahan_baku', 'id')->whereNull('deleted_at')],
            'resep.*.jumlah' => ['required', 'numeric', 'gt:0'],
            'opsi_grup_ids' => ['nullable', 'array'],
            'opsi_grup_ids.*' => ['integer', Rule::exists('opsi_grup', 'id')->whereNull('deleted_at')],
        ]);

        $produk->resep()->delete();
        foreach ($data['resep'] ?? [] as $r) {
            $produk->resep()->create(['bahan_baku_id' => $r['bahan_baku_id'], 'jumlah' => $r['jumlah']]);
        }
        $produk->opsiGrup()->sync($data['opsi_grup_ids'] ?? []);
        $this->stok->hitungUlangMenu($produk->id);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'kategori_id' => [$required, Rule::exists('kategori_produk', 'id')->whereNull('deleted_at')],
            'nama' => [$required, 'string', 'max:255'],
            'satuan' => [$required, 'string', 'max:20'],
            'harga_beli' => [$required, 'numeric', 'min:0'],
            'harga_jual' => [$required, 'numeric', 'min:0'],
            'stok_minimum' => ['nullable', 'numeric', 'min:0'],
            'tanggal_expired' => ['nullable', 'date'],
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    private function simpanGambar(?UploadedFile $file): ?string
    {
        return $file ? $file->store('products', 'public') : null;
    }

    /** File placeholder bawaan (ph-*) dipakai bersama banyak produk, jadi tidak boleh ikut terhapus. */
    private function hapusGambar(?string $path): void
    {
        if ($path && str_starts_with($path, 'products/') && ! str_starts_with(basename($path), 'ph-')) {
            Storage::disk('public')->delete($path);
        }
    }
}
