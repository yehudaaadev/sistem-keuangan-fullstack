        <!-- ============ PELANGGAN: PESANAN SAYA ============ -->
        <template x-if="cfg().type === 'pesanansaya'">
        <div x-data="pesanansaya()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="loading" x-show="loading">Memuat pesanan…</div>
            <div class="card empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('bag')"></div><div style="margin-bottom:14px">Kamu belum punya pesanan.</div><a class="btn btn-primary" href="#menu-pelanggan">Pesan sekarang</a></div>
            <div class="olist">
                <template x-for="p in rows" :key="p.id">
                    <article class="card ocard">
                        <header>
                            <div><div class="strong" x-text="p.kode"></div><div class="muted" x-text="waktu(p.created_at)"></div></div>
                            <span class="badge" :class="'b-' + p.status" x-text="statusLabel(p.status)"></span>
                        </header>
                        <div class="antrean-box" x-show="p.nomor_antrean">
                            <small>Nomor antreanmu</small><b x-text="p.nomor_antrean"></b>
                            <span class="muted" x-text="p.tipe === 'dine_in' ? 'Makan di tempat' + (p.meja ? ' · Meja ' + p.meja : '') : 'Bawa pulang'"></span>
                        </div>
                        <ol class="steps" x-show="langkah(p) >= 0" aria-label="Status pesanan">
                            <template x-for="(l, i) in langkahList" :key="l[0]"><li :class="{ done: i <= langkah(p), now: i === langkah(p) }"><i></i><span x-text="l[1]"></span></li></template>
                        </ol>
                        <div class="alert alert-ok" style="margin:0" x-show="p.status === 'siap'" role="status">Pesananmu sudah siap! Sebutkan kode <b x-text="p.kode"></b> ke kasir.</div>
                        <div class="alert alert-info" style="margin:0" x-show="menunggu(p)">Selesaikan pembayaran sebelum <b x-text="waktu(p.kedaluwarsa_pada)"></b>, atau pesanan otomatis dibatalkan.</div>
                        <div class="alert alert-error" style="margin:0" x-show="p.status === 'perlu_refund'">Pembayaran masuk setelah pesanan kedaluwarsa. Hubungi kedai untuk pengembalian dana.</div>
                        <ul class="oitems">
                            <template x-for="d in p.detail" :key="d.id"><li><span><span x-text="`${angka(d.qty)} × ${d.produk.nama}`"></span><small class="muted blk" x-show="opsiLabel(d)" x-text="opsiLabel(d)"></small></span><span class="muted" x-text="rupiah(d.subtotal)"></span></li></template>
                        </ul>
                        <p class="muted" style="margin:0;font-size:13px" x-show="p.catatan" x-text="'Catatan: ' + p.catatan"></p>
                        <footer>
                            <div><span class="muted">Total</span> <strong class="serif" style="font-size:20px" x-text="rupiah(p.total)"></strong></div>
                            <div class="btn-group" x-show="menunggu(p)">
                                <button class="btn btn-sm btn-danger" @click="batal(p)">Batalkan</button>
                                <button class="btn btn-sm btn-primary" @click="bayar(p)"><span x-html="icon('card')"></span>Bayar sekarang</button>
                            </div>
                            <div x-show="bisaUlas(p)">
                                <button type="button" class="btn btn-sm" style="background:#fef3c7;color:#b45309;border-color:#fde68a" @click="bukaUlasan(p)">
                                    <span x-html="icon('star')"></span>Beri Ulasan
                                </button>
                            </div>
                            <div x-show="sudahDiulas(p)">
                                <span class="badge" style="background:#fef3c7;color:#b45309">★ Sudah Diulas</span>
                            </div>
                        </footer>
                    </article>
                </template>
            </div>
            <div class="pager free" x-show="meta.last_page > 1">
                <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
            </div>

            <!-- Modal Beri Ulasan Menu (Pelanggan) -->
            <div x-show="ulasanModal" x-cloak class="backdrop" @keydown.escape.window="ulasanModal = false" style="z-index:92">
                <form @submit.prevent="simpanUlasan()" class="modal" role="dialog" aria-modal="true" aria-label="Beri Ulasan Menu">
                    <div class="modal-head">
                        <h2>Beri Ulasan Menu<small x-text="ulasanPesanan?.kode"></small></h2>
                        <button type="button" class="icon-btn" @click="ulasanModal = false" aria-label="Tutup" x-html="icon('x')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="ulasanErr" x-text="ulasanErr" role="alert"></div>
                        
                        <div style="text-align:center;padding:10px 0">
                            <label style="display:block;margin-bottom:8px;font-weight:600">Berapa bintang untuk pesananmu?</label>
                            <div style="display:inline-flex;gap:8px">
                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                    <button type="button" @click="ulasanBintang = star" :style="star <= ulasanBintang ? 'color:#f59e0b;font-size:32px;background:none;border:none;cursor:pointer' : 'color:#d1d5db;font-size:32px;background:none;border:none;cursor:pointer'" :aria-label="star + ' Bintang'">
                                        ★
                                    </button>
                                </template>
                            </div>
                            <div style="font-weight:600;color:#b45309;margin-top:4px" x-text="`${ulasanBintang} dari 5 Bintang`"></div>
                        </div>

                        <div class="field">
                            <label for="ul-komentar">Ulasan & Masukan (opsional)</label>
                            <textarea id="ul-komentar" class="textarea" x-model="ulasanKomentar" placeholder="Ceritakan rasa kopi, pelayanan, atau suasana kedai…" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn" @click="ulasanModal = false">Batal</button>
                        <button class="btn btn-primary" :disabled="ulasanBusy" x-text="ulasanBusy ? 'Mengirim…' : 'Kirim Ulasan'"></button>
                    </div>
                </form>
            </div>
        </div>
        </template>
