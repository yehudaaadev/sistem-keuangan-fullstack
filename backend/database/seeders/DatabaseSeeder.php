<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TipeRiwayatStok;
use App\Models\BahanBaku;
use App\Models\Distributor;
use App\Models\KategoriProduk;
use App\Models\OpsiGrup;
use App\Models\Pembeli;
use App\Models\Pembelian;
use App\Models\Produk;
use App\Models\User;
use App\Services\PembelianService;
use App\Services\PenjualanService;
use App\Services\PesananService;
use App\Services\StokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Menu kedai kopi: kategori => [file placeholder, [[nama, harga jual, harga modal, satuan, stok awal], ...]].
     *
     * CATATAN: harga jual berasal dari daftar menu; harga modal di bawah adalah PERKIRAAN (±35-45% dari harga jual)
     * hanya agar laporan margin punya angka. Ganti dengan modal sebenarnya lewat menu Data Produk.
     */
    private const MENU = [
        'Kopi Susu' => ['ph-kopi-susu', [
            ['Es Kopi Susu Gula Aren', 22000, 8500, 'cup', 40],
            ['Kopi Susu Original', 20000, 7500, 'cup', 40],
            ['Piccolo Latte', 20000, 7800, 'cup', 40],
            ['Caramel Macchiato', 26000, 10500, 'cup', 40],
            ['Hazelnut Latte', 26000, 10500, 'cup', 40],
        ]],
        'Kopi Hitam / Manual Brew' => ['ph-kopi-hitam', [
            ['Americano', 18000, 5500, 'cup', 40],
            ['Kopi Tubruk', 15000, 4500, 'cup', 40],
            ['V60 Manual Brew', 28000, 10000, 'cup', 30],
            ['Espresso Single Shot', 15000, 4800, 'cup', 40],
            ['Cold Brew', 24000, 8500, 'cup', 30],
        ]],
        'Non-Kopi' => ['ph-non-kopi', [
            ['Matcha Latte', 25000, 10500, 'cup', 35],
            ['Taro Latte', 23000, 8500, 'cup', 35],
            ['Red Velvet Latte', 23000, 8800, 'cup', 35],
            ['Chocolate', 23000, 8600, 'cup', 35],
            ['Teh Tarik', 18000, 5500, 'cup', 35],
            ['Lemon Tea', 15000, 4500, 'cup', 35],
        ]],
        'Makanan Ringan' => ['ph-makanan', [
            ['Croissant Butter', 18000, 8000, 'pcs', 20],
            ['Roti Bakar Coklat Keju', 15000, 6000, 'pcs', 20],
            ['Pisang Goreng Keju', 15000, 5500, 'pcs', 20],
            ['Kentang Goreng', 15000, 5000, 'pcs', 20],
            ['Pastel Isi Ayam', 8000, 3200, 'pcs', 30],
        ]],
        'Dessert' => ['ph-dessert', [
            ['Cheesecake Slice', 25000, 11000, 'pcs', 12],
            ['Brownies', 15000, 6000, 'pcs', 15],
            ['Pudding Coklat', 12000, 4500, 'pcs', 15],
        ]],
    ];

    /** [nama, satuan, jumlah belanja awal, harga satuan] */
    private const BAHAN = [
        ['Biji Kopi Arabika', 'gram', 5000, 150],
        ['Biji Kopi Robusta', 'gram', 5000, 90],
        ['Susu UHT', 'ml', 20000, 20],
        ['Gula Aren Cair', 'ml', 5000, 30],
        ['Sirup Karamel', 'ml', 2000, 60],
        ['Sirup Hazelnut', 'ml', 2000, 60],
        ['Bubuk Matcha', 'gram', 1000, 350],
        ['Bubuk Taro', 'gram', 1000, 120],
        ['Bubuk Red Velvet', 'gram', 1000, 130],
        ['Bubuk Cokelat', 'gram', 2000, 110],
        ['Teh Hitam', 'gram', 1000, 100],
        ['Lemon', 'pcs', 60, 3000],
        ['Cup Plastik 16oz', 'pcs', 500, 700],
        ['Oat Milk', 'ml', 5000, 45],
        ['Whipped Cream', 'ml', 2000, 60],
    ];

    /** Resep 1 porsi: menu => [[bahan baku, jumlah dalam satuan bahan], ...]. Angka umum kedai, sesuaikan dengan takaran sebenarnya. */
    private const RESEP = [
        'Es Kopi Susu Gula Aren' => [['Biji Kopi Arabika', 18], ['Susu UHT', 120], ['Gula Aren Cair', 20], ['Cup Plastik 16oz', 1]],
        'Kopi Susu Original' => [['Biji Kopi Arabika', 18], ['Susu UHT', 130], ['Gula Aren Cair', 10], ['Cup Plastik 16oz', 1]],
        'Piccolo Latte' => [['Biji Kopi Arabika', 18], ['Susu UHT', 70], ['Cup Plastik 16oz', 1]],
        'Caramel Macchiato' => [['Biji Kopi Arabika', 18], ['Susu UHT', 150], ['Sirup Karamel', 20], ['Cup Plastik 16oz', 1]],
        'Hazelnut Latte' => [['Biji Kopi Arabika', 18], ['Susu UHT', 150], ['Sirup Hazelnut', 20], ['Cup Plastik 16oz', 1]],
        'Americano' => [['Biji Kopi Arabika', 18], ['Cup Plastik 16oz', 1]],
        'Kopi Tubruk' => [['Biji Kopi Robusta', 15], ['Cup Plastik 16oz', 1]],
        'V60 Manual Brew' => [['Biji Kopi Arabika', 15], ['Cup Plastik 16oz', 1]],
        'Espresso Single Shot' => [['Biji Kopi Arabika', 18], ['Cup Plastik 16oz', 1]],
        'Cold Brew' => [['Biji Kopi Arabika', 40], ['Cup Plastik 16oz', 1]],
        'Matcha Latte' => [['Bubuk Matcha', 6], ['Susu UHT', 150], ['Cup Plastik 16oz', 1]],
        'Taro Latte' => [['Bubuk Taro', 25], ['Susu UHT', 150], ['Cup Plastik 16oz', 1]],
        'Red Velvet Latte' => [['Bubuk Red Velvet', 25], ['Susu UHT', 150], ['Cup Plastik 16oz', 1]],
        'Chocolate' => [['Bubuk Cokelat', 25], ['Susu UHT', 150], ['Cup Plastik 16oz', 1]],
        'Teh Tarik' => [['Teh Hitam', 5], ['Susu UHT', 80], ['Cup Plastik 16oz', 1]],
        'Lemon Tea' => [['Teh Hitam', 4], ['Lemon', 0.5], ['Cup Plastik 16oz', 1]],
    ];

    /** Kelompok kustomisasi: nama => [banyak?, wajib?, [[pilihan, tambahan harga, bawaan?], ...]]. */
    private const OPSI = [
        'Ukuran' => [false, true, [['Regular', 0, true], ['Large', 5000, false]]],
        'Gula' => [false, true, [['Normal', 0, true], ['Less sugar', 0, false], ['Tanpa gula', 0, false]]],
        'Es' => [false, true, [['Normal', 0, true], ['Less ice', 0, false], ['Tanpa es', 0, false]]],
        // Pilihan ke-4/5 (opsional): bahan baku yang terpakai per porsi dan jumlahnya.
        'Tambahan' => [true, false, [['Extra shot', 5000, false, 'Biji Kopi Arabika', 18], ['Oat milk', 6000, false, 'Oat Milk', 100], ['Whipped cream', 4000, false, 'Whipped Cream', 30]]],
    ];

    /** Kelompok kustomisasi per kategori; pengecualian per menu ada di OPSI_KHUSUS. */
    private const OPSI_KATEGORI = [
        'Kopi Susu' => ['Ukuran', 'Gula', 'Es', 'Tambahan'],
        'Non-Kopi' => ['Ukuran', 'Gula', 'Es'],
        'Kopi Hitam / Manual Brew' => ['Ukuran', 'Es', 'Tambahan'],
    ];

    private const OPSI_KHUSUS = [
        'Kopi Tubruk' => ['Gula'],
        'V60 Manual Brew' => ['Gula'],
        'Espresso Single Shot' => [],
    ];

    /** Password semua user contoh: "password". */
    public function run(): void
    {
        // Seeder ini membuat akun demo dengan sandi 'password'; jangan pernah jalan di produksi.
        if (app()->isProduction() && ! env('SEED_DEMO')) {
            $this->command?->error('Seeder demo dinonaktifkan di produksi. Buat owner dengan: php artisan pengguna:buat email nama');

            return;
        }

        $namaAkun = [
            'kasir' => ['Nayaka Wirasatya', '081355510021'],
            'admin' => ['Kirana Laksmi Dewanti', '081355510022'],
            'owner' => ['Aryasatya Kusumo Wardhana', '081355510023'],
            'pelanggan' => ['Zahra Anindita Prakoso', '081355510024'],
        ];
        foreach (Role::cases() as $role) {
            User::updateOrCreate(
                ['email' => $role->value.'@example.com'],
                ['name' => $namaAkun[$role->value][0], 'telepon' => $namaAkun[$role->value][1], 'password' => 'password', 'role' => $role],
            );
        }
        $admin = User::where('role', Role::Admin)->firstOrFail();
        $kasir = User::where('role', Role::Kasir)->firstOrFail();

        $this->salinPlaceholder();

        \App\Models\Voucher::firstOrCreate(['kode' => 'HEMAT10'], ['nama' => 'Diskon 10% (maks Rp 15.000)', 'tipe' => 'persen', 'nilai' => 10, 'maks_diskon' => 15000, 'min_belanja' => 30000, 'untuk' => 'semua', 'aktif' => true]);
        \App\Models\Voucher::firstOrCreate(['kode' => 'NGOPI5K'], ['nama' => 'Potongan Rp 5.000 pesanan online', 'tipe' => 'nominal', 'nilai' => 5000, 'min_belanja' => 20000, 'kuota' => 100, 'untuk' => 'online', 'aktif' => true]);
        $stok = app(StokService::class);

        foreach ([
            ['Dimas Erlangga Pratama', '081355520001', 'Jl. Cikutra Barat 14, Bandung'],
            ['Larasati Wulandari', '081355520002', 'Perum Griya Asri B7, Cimahi'],
            ['Gilang Ramadhan Putra', '081355520003', 'Jl. Dipatiukur 88, Bandung'],
            ['Tiara Ayu Nirmala', '081355520004', 'Apartemen Sky Terrace 12C'],
            ['Bagas Setyo Aji', '081355520005', 'Jl. Sukajadi 210, Bandung'],
            ['Nadine Callista Rahmani', '081355520006', 'Jl. Setiabudi 45, Bandung'],
            ['Fikri Alamsyah Nugraha', '081355520007', 'Jl. Buah Batu 301, Bandung'],
            ['Kenanga Puspitasari', '081355520008', 'Jl. Riau 27, Bandung'],
        ] as [$nama, $telepon, $alamat]) {
            Pembeli::firstOrCreate(['nama' => $nama], ['telepon' => $telepon, 'alamat' => $alamat]);
        }
        $distributor = Distributor::firstOrCreate(['nama' => 'CV Sarana Sangrai Nusantara'], ['telepon' => '081355530001', 'alamat' => 'Jl. Kopi Gayo 5, Medan']);
        Distributor::firstOrCreate(['nama' => 'PT Susu Segar Lembang Makmur'], ['telepon' => '081355530002', 'alamat' => 'Jl. Raya Lembang 120, Bandung Barat']);
        Distributor::firstOrCreate(['nama' => 'UD Kemasan Cakrawala'], ['telepon' => '081355530003', 'alamat' => 'Jl. Industri Kecil 9, Bekasi']);

        // Bahan baku distok lewat pembelian agar tercatat di riwayat_stok (aturan bisnis).
        if (BahanBaku::count() === 0) {
            $bahan = collect(self::BAHAN)->map(fn ($b) => [BahanBaku::create(['nama' => $b[0], 'satuan' => $b[1]]), $b]);
            app(PembelianService::class)->buat([
                'distributor_id' => $distributor->id,
                'tanggal' => now()->subDays(29)->setTime(9, 0),
                'catatan' => 'Belanja bahan baku awal (seeder)',
                'items' => $bahan->map(fn ($p) => ['bahan_baku_id' => $p[0]->id, 'qty' => $p[1][2], 'harga_beli' => $p[1][3]])->all(),
            ], $admin);
        }

        $grup = $this->opsiGrup();

        foreach (self::MENU as $namaKategori => [$placeholder, $items]) {
            $kategori = KategoriProduk::firstOrCreate(['nama' => $namaKategori]);

            foreach ($items as [$nama, $jual, $modal, $satuan, $stokAwal]) {
                $produk = Produk::firstOrCreate(['nama' => $nama], [
                    'kategori_id' => $kategori->id,
                    'satuan' => $satuan,
                    'harga_jual' => $jual,
                    'harga_beli' => $modal,
                    'image_path' => "products/{$placeholder}.svg",
                ]);
                if (! $produk->wasRecentlyCreated) {
                    continue;
                }

                if (isset(self::RESEP[$nama])) {
                    // Menu beresep: stok = porsi yang bisa dibuat dari stok bahan (dihitung, bukan diisi).
                    foreach (self::RESEP[$nama] as [$namaBahan, $jumlah]) {
                        $produk->resep()->create(['bahan_baku_id' => BahanBaku::where('nama', $namaBahan)->value('id'), 'jumlah' => $jumlah]);
                    }
                    $stok->hitungUlangMenu($produk->id);
                } else {
                    // Stok awal dicatat di riwayat_stok, bukan diisi langsung.
                    DB::transaction(fn () => $stok->ubah($produk->id, $stokAwal, TipeRiwayatStok::StokAwal, $produk, $admin->id));
                }

                $pakai = self::OPSI_KHUSUS[$nama] ?? self::OPSI_KATEGORI[$namaKategori] ?? [];
                $produk->opsiGrup()->sync(collect($pakai)->map(fn ($n) => $grup[$n])->all());
            }
        }

        // Belanja susulan di bulan berjalan untuk bahan yang cepat habis.
        if (Pembelian::count() === 1) {
            $cepatHabis = BahanBaku::whereIn('nama', ['Susu UHT', 'Gula Aren Cair', 'Cup Plastik 16oz', 'Biji Kopi Arabika'])->get();
            $harga = ['Susu UHT' => [10000, 20], 'Gula Aren Cair' => [2000, 30], 'Cup Plastik 16oz' => [200, 700], 'Biji Kopi Arabika' => [2000, 150]];
            app(PembelianService::class)->buat([
                'distributor_id' => $distributor->id,
                'tanggal' => now()->subDays(3)->setTime(10, 30),
                'catatan' => 'Restock bahan cepat habis (seeder)',
                'items' => $cepatHabis->map(fn ($b) => ['bahan_baku_id' => $b->id, 'qty' => $harga[$b->nama][0], 'harga_beli' => $harga[$b->nama][1]])->all(),
            ], $admin);
        }

        $this->transaksiContoh($kasir);
        $this->shiftContoh($kasir);
        $this->pesananContoh();
    }

    /** @return array<string, int> nama kelompok => id */
    private function opsiGrup(): array
    {
        $hasil = [];
        $urutan = 0;
        foreach (self::OPSI as $nama => [$banyak, $wajib, $pilihan]) {
            $g = OpsiGrup::firstOrCreate(['nama' => $nama], ['banyak' => $banyak, 'wajib' => $wajib, 'urutan' => $urutan++]);
            if ($g->wasRecentlyCreated) {
                foreach ($pilihan as $i => $baris) {
                    [$namaPilihan, $tambah, $bawaan] = $baris;
                    $g->pilihan()->create([
                        'nama' => $namaPilihan, 'tambahan_harga' => $tambah, 'bawaan' => $bawaan, 'urutan' => $i,
                        'bahan_baku_id' => isset($baris[3]) ? BahanBaku::where('nama', $baris[3])->value('id') : null,
                        'jumlah_bahan' => $baris[4] ?? null,
                    ]);
                }
            }
            $hasil[$nama] = $g->id;
        }

        return $hasil;
    }

    /**
     * Satu shift tertutup per hari penjualan contoh (kecuali hari ini, yang dibiarkan tanpa shift supaya kasir
     * mencoba membukanya). Selisih kas dibuat bervariasi agar riwayat shift terlihat realistis.
     */
    private function shiftContoh(User $kasir): void
    {
        $selisih = [0, 0, 0, -5000, 0, 2000, 0, -10000, 0, 0];
        $i = 0;

        \App\Models\Penjualan::whereNull('shift_id')->whereNull('pesanan_id')->whereDate('tanggal', '<', now()->toDateString())
            ->orderBy('tanggal')->get()->groupBy(fn ($p) => $p->tanggal->toDateString())
            ->each(function ($hari, $tanggal) use ($kasir, $selisih, &$i) {
                $modal = 200000;
                $total = (float) $hari->sum('total');
                $shift = \App\Models\Shift::create([
                    'user_id' => $kasir->id,
                    'dibuka_pada' => $tanggal.' 08:00:00',
                    'ditutup_pada' => $tanggal.' 22:00:00',
                    'modal_awal' => $modal,
                    'jumlah_transaksi' => $hari->count(),
                    'total_penjualan' => $total,
                    'kas_seharusnya' => $modal + $total,
                    'uang_dihitung' => $modal + $total + $selisih[$i % count($selisih)],
                    'selisih' => $selisih[$i % count($selisih)],
                ]);
                \App\Models\Penjualan::whereIn('id', $hari->pluck('id'))->update(['shift_id' => $shift->id]);
                $i++;
            });
    }

    /** Dua pesanan online contoh (sudah dibayar) supaya antrean kasir dan halaman "Pesanan Saya" tidak kosong. */
    private function pesananContoh(): void
    {
        $pelanggan = User::where('role', Role::Pelanggan)->first();
        if (! $pelanggan || \App\Models\Pesanan::count() > 0) {
            return;
        }

        $service = app(PesananService::class);
        $menu = Produk::whereIn('nama', ['Es Kopi Susu Gula Aren', 'Croissant Butter', 'Matcha Latte'])->get()->keyBy('nama');

        $pertama = $service->buat($pelanggan, ['catatan' => 'Less sugar ya', 'tipe' => 'dine_in', 'meja' => '5', 'items' => [
            ['produk_id' => $menu['Es Kopi Susu Gula Aren']->id, 'qty' => 2],
            ['produk_id' => $menu['Croissant Butter']->id, 'qty' => 1],
        ]]);
        $service->tandaiDibayar($pertama->kode, 'SIM-'.$pertama->kode);
        $service->ubahStatus($pertama->refresh(), \App\Enums\StatusPesanan::Diproses);

        $kedua = $service->buat($pelanggan, ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $menu['Matcha Latte']->id, 'qty' => 1]]]);
        $service->tandaiDibayar($kedua->kode, 'SIM-'.$kedua->kode);
    }

    /** Menyalin ilustrasi bawaan ke storage/app/public/products supaya bisa diakses lewat /storage. */
    private function salinPlaceholder(): void
    {
        foreach (File::files(__DIR__.'/assets/products') as $berkas) {
            Storage::disk('public')->put('products/'.$berkas->getFilename(), File::get($berkas->getPathname()));
        }
    }

    /** ±45 penjualan tersebar 30 hari terakhir supaya grafik tren dan laporan per kategori terisi. Deterministik. */
    private function transaksiContoh(User $kasir): void
    {
        if (\App\Models\Penjualan::count() > 0) {
            return;
        }

        mt_srand(42);
        $produk = Produk::all();
        $pembeliIds = Pembeli::pluck('id');
        $service = app(PenjualanService::class);

        // Urut dari yang paling lama supaya nomor nota naik searah waktu.
        $waktu = collect(range(1, 45))
            ->map(fn () => now()->subDays(mt_rand(0, 29))->setTime(mt_rand(8, 21), mt_rand(0, 59)))
            ->take(37)
            // Beberapa nota selalu jatuh di hari ini (sebelum jam sekarang) supaya dashboard tidak kosong saat demo.
            ->concat(collect(range(1, 8))->map(fn () => now()->startOfDay()->addMinutes(mt_rand(5, max(10, (int) abs(now()->startOfDay()->diffInMinutes(now())) - 5)))))
            ->sort()->values();

        foreach ($waktu as $tanggal) {
            $item = $produk->shuffle()->take(mt_rand(1, 3))->map(fn ($p) => ['produk_id' => $p->id, 'qty' => mt_rand(1, 2)]);
            $total = $item->sum(fn ($i) => $i['qty'] * (float) $produk->firstWhere('id', $i['produk_id'])->harga_jual);

            $service->buat([
                'pembeli_id' => mt_rand(0, 2) === 0 ? $pembeliIds->random() : null,
                'tipe' => mt_rand(0, 2) ? 'dine_in' : 'bawa_pulang',
                'meja' => mt_rand(1, 12),
                'tanggal' => $tanggal,
                'bayar' => ceil($total / 50000) * 50000,
                'items' => $item->all(),
            ], $kasir);
        }
    }
}
