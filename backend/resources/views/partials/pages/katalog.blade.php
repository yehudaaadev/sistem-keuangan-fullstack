        <!-- ============ PELANGGAN: MENU + KERANJANG ============ -->
        <template x-if="cfg().type === 'katalog'">
        <div x-data="katalog()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="katalog">
                <section>
                    <div class="toolbar">
                        <div class="search" style="max-width:none"><span x-html="icon('search')"></span>
                            <input class="input" type="search" x-model="cari" placeholder="Cari kopi, minuman, camilan…" aria-label="Cari menu"></div>
                    </div>
                    <div class="cats">
                        <button type="button" class="cat" :class="!kat && 'on'" @click="kat = ''">Semua</button>
                        <template x-for="c in kategoriList()" :key="c.id"><button type="button" class="cat" :class="kat === c.id && 'on'" @click="kat = c.id" x-text="c.nama"></button></template>
                    </div>
                    <div class="loading" x-show="loading">Menyeduh menu…</div>
                    <div class="pgrid">
                        <template x-for="p in grid()" :key="p.id">
                            <article class="card pcard" :class="Number(p.stok) <= 0 && 'off'">
                                <div class="art thumb" :class="'tone-' + art(p).tone">
                                    <span class="fb" x-html="icon(art(p).icon)"></span>
                                    <template x-if="p.image_url"><img :src="p.image_url" :alt="p.nama" loading="lazy" x-on:error="$el.remove()"></template>
                                    <span class="st badge" :class="Number(p.stok) <= 0 ? 'b-habis' : 'b-menipis'" x-show="sisaText(p)" x-text="sisaText(p)"></span>
                                </div>
                                <div class="pcard-body">
                                    <div style="display:flex;justify-content:space-between;align-items:center">
                                        <small x-text="p.kategori?.nama"></small>
                                        <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:600;font-size:11px;padding:2px 6px" x-show="Number(p.ulasan_count) > 0">
                                            ★ <span x-text="Number(p.ulasan_avg_bintang || 5).toFixed(1)"></span> (<span x-text="p.ulasan_count"></span>)
                                        </span>
                                    </div>
                                    <h4 x-text="p.nama"></h4>
                                    <div class="price"><span x-text="rupiah(p.harga_jual)"></span> <small x-text="'/ ' + p.satuan"></small></div>
                                </div>
                                <div class="pcard-foot">
                                    <template x-if="qtyDi(p) === 0">
                                        <button class="btn btn-primary btn-sm" style="width:100%" :disabled="Number(p.stok) <= 0" @click="tambah(p)" :aria-label="`Tambah ${p.nama}`"><span x-html="icon('plus')"></span><span x-text="Number(p.stok) <= 0 ? 'Habis' : (adaOpsi(p) ? 'Pilih' : 'Tambah')"></span></button>
                                    </template>
                                    <template x-if="qtyDi(p) > 0">
                                        <div class="stepper big">
                                            <button type="button" @click="ubahMenu(p, -1)" aria-label="Kurangi" x-html="icon('minus')"></button>
                                            <span x-text="qtyDi(p)" aria-live="polite"></span>
                                            <button type="button" @click="tambah(p)" aria-label="Tambah" x-html="icon('plus')"></button>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </template>
                    </div>
                    <div class="card empty" x-show="!loading && !grid().length"><div class="ico" x-html="icon('search')"></div>Menu tidak ditemukan.</div>
                </section>

                <aside class="keranjang card" :class="buka && 'open'" aria-label="Pesananmu">
                    <div class="card-head head-pad"><h3>Pesananmu</h3><button type="button" class="icon-btn kx" @click="buka = false" aria-label="Tutup keranjang" x-html="icon('x')"></button></div>
                    <div class="cart-lines">
                        <template x-for="(it, i) in items" :key="it.sig">
                            <div class="line">
                                <div class="art thumb" :class="'tone-' + art(it.produk).tone">
                                    <span class="fb" x-html="icon(art(it.produk).icon)"></span>
                                    <template x-if="it.produk.image_url"><img :src="it.produk.image_url" alt="" x-on:error="$el.remove()"></template>
                                </div>
                                <div style="min-width:0;flex:1">
                                    <div class="nm" x-text="it.produk.nama"></div>
                                        <div class="pr" style="color:var(--accent-2)" x-show="it.label" x-text="it.label"></div>
                                    <div class="pr" x-text="rupiah(it.harga)"></div>
                                    <div class="stepper">
                                        <button type="button" @click="ubah(i, -1)" aria-label="Kurangi" x-html="icon('minus')"></button>
                                        <input type="text" inputmode="numeric" :value="it.qty" readonly aria-label="Jumlah">
                                        <button type="button" @click="ubah(i, 1)" aria-label="Tambah" x-html="icon('plus')"></button>
                                    </div>
                                </div>
                                <div class="sub" x-text="rupiah(it.qty * it.harga)"></div>
                            </div>
                        </template>
                        <div class="empty sm" x-show="!items.length"><div class="ico" x-html="icon('bag')"></div>Belum ada menu dipilih.</div>
                    </div>
                    <div class="pos-pay">
                        <div class="field"><label>Pesanan ini</label>
                            <div class="tipe" role="group" aria-label="Jenis pesanan">
                                <button type="button" :class="tipe === 'bawa_pulang' && 'on'" :aria-pressed="tipe === 'bawa_pulang'" @click="tipe = 'bawa_pulang'">Bawa pulang</button>
                                <button type="button" :class="tipe === 'dine_in' && 'on'" :aria-pressed="tipe === 'dine_in'" @click="tipe = 'dine_in'">Makan di tempat</button>
                            </div>
                            <input class="input" x-show="tipe === 'dine_in'" x-model="meja" maxlength="10" placeholder="Nomor mejamu, mis. 5" aria-label="Nomor meja" style="margin-top:10px"></div>
                        <div class="field"><label for="k-cat">Catatan untuk barista (opsional)</label><textarea id="k-cat" class="textarea" style="min-height:56px" maxlength="300" x-model="catatan" placeholder="Mis. less sugar, tanpa es"></textarea></div>
                        <div class="voucher-box">
                            <div class="vrow" x-show="!vinfo">
                                <input class="input" x-model="kode" maxlength="30" placeholder="Punya kode voucher?" aria-label="Kode voucher" @keydown.enter.prevent="pakaiVoucher()">
                                <button type="button" class="btn btn-sm" :disabled="vbusy || !kode.trim()" @click="pakaiVoucher()">Pakai</button>
                            </div>
                            <div class="vok" x-show="vinfo"><span x-html="icon('ticket')"></span><span>Voucher <b x-text="vinfo?.voucher_kode"></b> terpasang</span>
                                <button type="button" class="btn btn-sm btn-link" @click="lepasVoucher()">Lepas</button></div>
                            <p class="hint neg" x-show="verr" x-text="verr" role="alert"></p>
                        </div>
                        <div class="rincian" x-show="adaTambahan()">
                            <div class="kv"><span class="muted">Subtotal</span><span x-text="rupiah(rincian().subtotal)"></span></div>
                            <div class="kv" x-show="rincian().diskon > 0"><span class="muted">Diskon</span><span class="pos" x-text="'-' + rupiah(rincian().diskon)"></span></div>
                            <div class="kv" x-show="rincian().service > 0"><span class="muted" x-text="`Service ${angka(peng.service_persen)}%`"></span><span x-text="rupiah(rincian().service)"></span></div>
                            <div class="kv" x-show="rincian().pajak > 0"><span class="muted" x-text="`Pajak ${angka(peng.pajak_persen)}%`"></span><span x-text="rupiah(rincian().pajak)"></span></div>
                        </div>
                        <div class="row"><span class="muted">Total</span><span class="total" x-text="rupiah(total())"></span></div>
                        <button type="button" class="btn btn-primary btn-lg btn-block" :disabled="!items.length || saving" @click="pesan()"><span x-html="icon('card')"></span><span x-text="saving ? 'Membuat pesanan…' : 'Bayar sekarang'"></span></button>
                        <p class="hint" style="margin:0;text-align:center">Ambil pesananmu di kedai setelah statusnya “Siap diambil”.</p>
                    </div>
                </aside>
            </div>
            <button type="button" class="cartbar" x-show="items.length && !buka" @click="buka = true">
                <span x-html="icon('bag')"></span><span x-text="`${jumlah()} item`"></span><span class="grow"></span><strong x-text="rupiah(total())"></strong><span>Lihat pesanan</span>
            </button>
        </div>
        </template>
