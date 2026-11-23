        <!-- ============ ADMIN: KUSTOMISASI MENU ============ -->
        <template x-if="cfg().type === 'opsimenu'">
        <div x-data="opsimenu()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="alert alert-info">Pilihan bisa dihubungkan ke bahan baku (mis. Extra shot = 18 g biji kopi) sehingga stok bahan ikut berkurang. Buat kelompok pilihan sekali (mis. Ukuran, Gula, Es, Tambahan), lalu pasang ke produk lewat form <b>Data Produk → Kustomisasi</b>. Harga tambahan otomatis masuk ke total pesanan.</div>
            <div class="toolbar"><span class="grow"></span><button class="btn btn-primary" @click="baru()"><span x-html="icon('plus')"></span>Kelompok Baru</button></div>
            <div class="loading" x-show="loading">Memuat…</div>
            <div class="card empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('sliders')"></div>Belum ada kelompok kustomisasi.</div>
            <div class="qgrid">
                <template x-for="g in rows" :key="g.id">
                    <article class="card ocard">
                        <header>
                            <div class="strong serif" style="font-size:19px" x-text="g.nama"></div>
                            <div class="btn-group"><span class="badge" :class="g.wajib ? 'b-menipis' : 'b-selesai'" x-text="g.wajib ? 'Wajib' : 'Opsional'"></span><span class="badge b-selesai" x-text="g.banyak ? 'Banyak' : 'Pilih satu'"></span></div>
                        </header>
                        <p class="muted" style="margin:0" x-text="ringkas(g)"></p>
                        <footer><span></span><div class="btn-group"><button class="btn btn-sm btn-link" @click="ubah(g)">Ubah</button><button class="btn btn-sm btn-danger" @click="hapus(g)">Hapus</button></div></footer>
                    </article>
                </template>
            </div>

            <div x-show="modal" x-cloak class="backdrop" @keydown.escape.window="modal = false">
                <form @submit.prevent="simpan()" class="modal lg" role="dialog" aria-modal="true" aria-label="Kelompok kustomisasi">
                    <div class="modal-head"><h2 x-text="editId ? 'Ubah kelompok' : 'Kelompok baru'"></h2><button type="button" class="icon-btn" @click="modal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError" role="alert"></div>
                        <div class="field"><label for="og-nama">Nama kelompok</label><input id="og-nama" class="input" x-model="form.nama" required maxlength="60" placeholder="Mis. Ukuran"></div>
                        <div style="display:flex;gap:22px;flex-wrap:wrap">
                            <label class="chk"><input type="checkbox" x-model="form.wajib"><span>Wajib dipilih</span></label>
                            <label class="chk"><input type="checkbox" x-model="form.banyak"><span>Boleh pilih lebih dari satu</span></label>
                        </div>
                        <div class="cart-box">
                            <table class="data">
                                <thead><tr><th>Pilihan</th><th style="width:130px">Tambahan harga</th><th style="width:70px">Bawaan</th><th style="min-width:260px">Memakai bahan (opsional)</th><th></th></tr></thead>
                                <tbody>
                                    <template x-for="(p, i) in form.pilihan" :key="i">
                                        <tr>
                                            <td style="min-width:150px"><input class="input sm" x-model="p.nama" required maxlength="60" aria-label="Nama pilihan" placeholder="Mis. Large"></td>
                                            <td><input class="input sm" type="number" min="0" step="500" x-model.number="p.tambahan_harga" aria-label="Tambahan harga"></td>
                                            <td><input type="checkbox" :checked="p.bawaan" @change="setBawaan(i)" aria-label="Pilihan bawaan"></td>
                                            <td>
                                                <div style="display:grid;grid-template-columns:minmax(0,1fr) 84px 36px;gap:6px;align-items:center">
                                                    <select class="select" style="min-width:0;width:100%;padding:6px 30px 6px 10px" x-model="p.bahan_baku_id" aria-label="Bahan yang terpakai">
                                                        <option value="">— tidak memotong bahan —</option>
                                                        <template x-for="b in bahanList" :key="b.id"><option :value="b.id" :selected="Number(p.bahan_baku_id) === b.id" x-text="b.nama"></option></template>
                                                    </select>
                                                    <input class="input sm" type="number" min="0.01" step="any" x-model.number="p.jumlah_bahan" :required="!!p.bahan_baku_id" :disabled="!p.bahan_baku_id" placeholder="Jml" aria-label="Jumlah bahan per porsi">
                                                    <span class="muted" style="font-size:12px" x-text="satuan(p.bahan_baku_id)"></span>
                                                </div>
                                            </td>
                                            <td class="actions"><button type="button" class="icon-btn" @click="hapusPilihan(i)" :disabled="form.pilihan.length < 2" aria-label="Hapus pilihan" x-html="icon('x')"></button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm" style="align-self:flex-start" @click="tambahPilihan()"><span x-html="icon('plus')"></span>Tambah pilihan</button>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn" @click="modal = false">Batal</button><button class="btn btn-primary" :disabled="saving" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button></div>
                </form>
            </div>
        </div>
        </template>
