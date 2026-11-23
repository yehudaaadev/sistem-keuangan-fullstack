        <!-- ============ BELANJA / RESTOCK ============ -->
        <template x-if="cfg().type === 'pembelian'">
        <div x-data="pembelian()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error"></div>
            <div class="toolbar">
                <div class="search"><span x-html="icon('search')"></span>
                    <input class="input" type="search" x-model="search" @input.debounce.400ms="load()" placeholder="Cari no. faktur, distributor…" aria-label="Cari belanja"></div>
                <select class="select" x-model="filter.distributor_id" @change="load()" aria-label="Filter distributor">
                    <option value="">Semua distributor</option>
                    <template x-for="d in distributorList" :key="d.id"><option :value="d.id" x-text="d.nama"></option></template>
                </select>
                <input class="input" style="width:auto" type="month" x-model="filter.bulan" @change="load()" aria-label="Filter bulan">
                <span class="grow"></span>
                <div class="btn-group" x-show="bisaEkspor()">
                    <button class="btn" @click="ekspor('pdf', '/export/pembelian', 'belanja')"><span x-html="icon('download')"></span>PDF</button>
                    <button class="btn" @click="ekspor('csv', '/export/pembelian', 'belanja')"><span x-html="icon('download')"></span>Excel</button>
                </div>
                <button x-show="canWrite()" @click="bukaForm(true)" class="btn" title="Buat draf belanja otomatis dari bahan dan produk yang stoknya menipis"><span x-html="icon('alert')"></span>Restok Otomatis</button>
                <button x-show="canWrite()" @click="bukaForm(false)" class="btn btn-primary"><span x-html="icon('plus')"></span>Belanja Baru</button>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>No. Faktur</th><th>Tanggal</th><th>Distributor</th><th>Petugas</th><th class="num">Total</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="row in rows" :key="row.id">
                                <tr>
                                    <td class="strong" x-text="row.no_faktur"></td>
                                    <td class="muted" x-text="waktu(row.tanggal)"></td>
                                    <td x-text="row.distributor?.nama"></td>
                                    <td class="muted" x-text="row.user?.name"></td>
                                    <td class="num strong" x-text="rupiah(row.total)"></td>
                                    <td class="actions"><button @click="lihat(row)" class="btn btn-sm btn-link">Detail</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat data…</div>
                <div class="empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('inbox')"></div><div>Tidak ada belanja yang cocok.</div></div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>

            <div x-show="form" x-cloak class="backdrop" @keydown.escape.window="form = false">
                <form @submit.prevent="simpan()" class="modal lg" role="dialog" aria-modal="true" aria-label="Pembelian produk dari distributor">
                    <div class="modal-head"><h2>Pembelian Produk dari Distributor<small>Stok produk dan bahan baku bertambah otomatis setelah disimpan</small></h2>
                        <button type="button" class="icon-btn" @click="form = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError"></div>
                        <div style="display:grid;grid-template-columns:1fr 180px;gap:14px">
                            <div class="field"><label for="b-dist">Distributor</label>
                                <select id="b-dist" class="select" x-model="extra.distributor_id" required>
                                    <option value="">Pilih distributor…</option>
                                    <template x-for="d in distributorList" :key="d.id"><option :value="d.id" x-text="d.nama"></option></template>
                                </select></div>
                            <div class="field"><label for="b-tgl">Tanggal</label><input id="b-tgl" class="input" type="date" x-model="tgl" required></div>
                        </div>
                        <div class="field pick"><label for="b-cari">Tambah item (produk atau bahan baku)</label>
                            <input id="b-cari" class="input" type="search" x-model="cari" placeholder="Ketik nama produk atau bahan, mis. Kopi, Susu, Roti…" autocomplete="off">
                            <div class="pick-list" x-show="cari">
                                <template x-for="o in pilihan()" :key="o.tipe + o.id">
                                    <button type="button" @click="tambah(o)"><span><span x-text="o.nama"></span> <span class="tag" :class="o.tipe === 'bahan' ? 'bahan' : 'menu'" x-text="o.tipe === 'bahan' ? 'Bahan' : 'Produk'"></span></span><span class="muted" x-text="`stok ${angka(o.stok)} ${o.satuan}`"></span></button>
                                </template>
                                <div class="empty sm" x-show="!pilihan().length">Item tidak ditemukan</div>
                            </div></div>
                        <div class="cart-box">
                            <table class="data">
                                <thead><tr><th>Item</th><th style="width:110px">Jumlah</th><th style="width:140px">Harga satuan</th><th class="num">Subtotal</th><th></th></tr></thead>
                                <tbody>
                                    <template x-for="(it, i) in items" :key="it.tipe + it.id">
                                        <tr>
                                            <td><div class="strong" x-text="it.nama"></div><div class="muted" style="font-size:12px" x-text="(it.tipe === 'bahan' ? 'Bahan · ' : 'Produk · ') + it.satuan"></div></td>
                                            <td><input class="input sm" type="number" step="any" min="0.01" x-model.number="it.qty" required aria-label="Jumlah"></td>
                                            <td><input class="input sm" type="number" step="any" min="0" x-model.number="it.harga" required aria-label="Harga satuan"></td>
                                            <td class="num" x-text="rupiah(it.qty * it.harga)"></td>
                                            <td class="actions"><button type="button" class="icon-btn" @click="hapusItem(i)" aria-label="Hapus item" x-html="icon('x')"></button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div class="empty sm" x-show="!items.length">Belum ada item dipilih.</div>
                        </div>
                        <div class="summary"><div class="row"><span class="muted">Total pembelian</span><span class="total" x-text="rupiah(total())"></span></div></div>
                        <div class="field"><label for="b-cat">Catatan (opsional)</label><textarea id="b-cat" class="textarea" x-model="catatan"></textarea></div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" @click="form = false" class="btn">Batal</button>
                        <button :disabled="saving || !items.length" class="btn btn-primary" x-text="saving ? 'Menyimpan…' : 'Simpan Pembelian'"></button>
                    </div>
                </form>
            </div>

            <div x-show="detail" x-cloak class="backdrop" @click.self="detail = null" @keydown.escape.window="detail = null">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Detail belanja">
                    <div class="modal-head"><h2><span x-text="detail?.no_faktur"></span><small x-text="`${waktu(detail?.tanggal)} · ${detail?.distributor?.nama}`"></small></h2>
                        <button type="button" class="icon-btn" @click="detail = null" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body" style="gap:0">
                        <template x-for="d in detail?.detail || []" :key="d.id">
                            <div class="kv"><span x-text="`${namaItem(d)} × ${angka(d.qty)} ${satuanItem(d)} @ ${rupiah(d.harga_beli)}`"></span><span x-text="rupiah(d.subtotal)"></span></div>
                        </template>
                        <div class="kv strong"><span>Total</span><span x-text="rupiah(detail?.total)"></span></div>
                        <p class="muted" style="padding-top:10px" x-show="detail?.catatan" x-text="detail?.catatan"></p>
                    </div>
                    <div class="modal-foot"><button @click="detail = null" class="btn btn-primary">Tutup</button></div>
                </div>
            </div>
        </div>
        </template>
