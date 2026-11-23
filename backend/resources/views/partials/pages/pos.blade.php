        <!-- ============ PENJUALAN + KASIR (POS) ============ -->
        <template x-if="cfg().type === 'penjualan'">
        <div x-data="penjualan()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error"></div>
            <!-- Status shift kasir -->
            <template x-if="perluShift()">
                <div class="card shift-bar" :class="shift ? 'on' : 'off'">
                    <template x-if="shift">
                        <div class="shift-row">
                            <span class="dot-ico ok" x-html="icon('clock')"></span>
                            <div class="grow"><b>Shift aktif</b> sejak <span x-text="waktu(shift.dibuka_pada)"></span>
                                <div class="muted" x-text="`Modal ${rupiah(shift.modal_awal)} · ${shift.rekap.jumlah_transaksi} transaksi · penjualan ${rupiah(shift.rekap.total_penjualan)}`"></div></div>
                            <button class="btn btn-sm" @click="siapKas('keluar')"><span x-html="icon('minus')"></span>Kas keluar</button>
                            <button class="btn btn-sm" @click="siapKas('masuk')"><span x-html="icon('plus')"></span>Kas masuk</button>
                            <button class="btn btn-sm" @click="siapTutup()"><span x-html="icon('lock')"></span>Tutup shift</button>
                        </div>
                    </template>
                    <template x-if="!shift">
                        <form class="shift-row" @submit.prevent="bukaShift()">
                            <span class="dot-ico bad" x-html="icon('lock')"></span>
                            <div class="grow"><b>Belum ada shift aktif</b>
                                <div class="muted">Hitung uang di laci, lalu masukkan sebagai modal awal untuk mulai berjualan.</div>
                                <div class="alert alert-error" style="margin:8px 0 0" x-show="errShift" x-text="errShift" role="alert"></div></div>
                            <label class="sr-only" for="modal-awal">Modal awal</label>
                            <input id="modal-awal" class="input" style="max-width:190px" type="number" min="0" step="any" x-model="modalAwal" required placeholder="Modal awal (Rp)">
                            <button class="btn btn-primary" :disabled="busyShift" x-text="busyShift ? 'Membuka…' : 'Buka shift'"></button>
                        </form>
                    </template>
                </div>
            </template>


            <div class="toolbar">
                <div class="search"><span x-html="icon('search')"></span>
                    <input class="input" type="search" x-model="search" @input.debounce.400ms="load()" placeholder="Cari no. nota, pembeli, kasir…" aria-label="Cari nota"></div>
                <input class="input" style="width:auto" type="month" x-model="filter.bulan" @change="load()" aria-label="Filter bulan">
                <span class="grow"></span>
                <div class="btn-group" x-show="bisaEkspor()">
                    <button class="btn" @click="ekspor('pdf', '/export/penjualan', 'penjualan')"><span x-html="icon('download')"></span>PDF</button>
                    <button class="btn" @click="ekspor('csv', '/export/penjualan', 'penjualan')"><span x-html="icon('download')"></span>Excel</button>
                </div>
                <button x-show="canWrite()" @click="bukaForm()" :disabled="perluShift() && !shift" class="btn btn-primary btn-lg"><span x-html="icon('cart')"></span>Buka Kasir</button>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>No. Nota</th><th>Antrean</th><th>Tanggal</th><th>Pembeli</th><th>Kasir</th><th>Bayar</th><th class="num">Total</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="row in rows" :key="row.id">
                                <tr :class="row.void_at && 'row-void'">
                                    <td class="strong" x-text="row.no_nota"></td>
                                    <td><span class="badge b-dibayar" x-show="row.nomor_antrean" x-text="row.nomor_antrean"></span> <span class="muted" x-text="row.tipe === 'dine_in' ? (row.meja ? 'Meja ' + row.meja : 'Di tempat') : 'Bawa pulang'"></span></td>
                                    <td class="muted" x-text="waktu(row.tanggal)"></td>
                                    <td x-text="row.pembeli?.nama ?? 'Umum'"></td>
                                    <td class="muted" x-text="row.sumber === 'online' ? 'Online' : row.user?.name"></td>
                                    <td><span class="badge b-habis" x-show="row.void_at">VOID</span><span x-show="!row.void_at" class="muted" x-text="metodeNama(row.metode)"></span></td>
                                    <td class="num strong" x-text="rupiah(row.total)"></td>
                                    <td class="actions">
                                        <button @click="lihat(row)" class="btn btn-sm btn-link">Detail</button>
                                        <button x-show="canWrite() && !row.void_at" @click="nota(row.id)" class="btn btn-sm btn-ghost"><span x-html="icon('printer')"></span>Cetak</button>
                                        <button x-show="bisaVoid(row)" @click="siapVoid(row)" class="btn btn-sm btn-danger"><span x-html="icon('ban')"></span>Void</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat data…</div>
                <div class="empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('inbox')"></div><div>Tidak ada penjualan yang cocok.</div></div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>

            <!-- Tutup shift -->
            <div x-show="tutupModal" x-cloak class="backdrop" @keydown.escape.window="tutupModal = false">
                <form @submit.prevent="tutupShift()" class="modal" role="dialog" aria-modal="true" aria-labelledby="tutup-judul">
                    <div class="modal-head"><h2 id="tutup-judul">Tutup Shift<small>Hitung uang di laci, lalu cocokkan dengan catatan sistem</small></h2>
                        <button type="button" class="icon-btn" @click="tutupModal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body" style="gap:0">
                        <div class="alert alert-error" x-show="errShift" x-text="errShift" role="alert"></div>
                        <div class="kv"><span class="muted">Modal awal</span><span x-text="rupiah(shift?.modal_awal)"></span></div>
                        <div class="kv"><span class="muted">+ Penjualan tunai</span><span x-text="rupiah(shift?.rekap.penjualan_tunai)"></span></div>
                        <div class="kv" x-show="shift?.rekap.kas_masuk > 0"><span class="muted">+ Kas masuk</span><span x-text="rupiah(shift?.rekap.kas_masuk)"></span></div>
                        <div class="kv" x-show="shift?.rekap.kas_keluar > 0"><span class="muted">- Kas keluar</span><span x-text="rupiah(shift?.rekap.kas_keluar)"></span></div>
                        <div class="kv strong"><span>Kas seharusnya</span><span x-text="rupiah(shift?.rekap.kas_seharusnya)"></span></div>
                        <div class="rekap-metode" x-show="shift?.rekap.per_metode?.length">
                            <small class="muted">Penjualan shift ini (termasuk non-tunai)</small>
                            <template x-for="m in shift?.rekap.per_metode || []" :key="m.metode"><div class="kv"><span x-text="`${metodeNama(m.metode)} · ${m.jumlah} transaksi`"></span><span x-text="rupiah(m.total)"></span></div></template>
                            <p class="hint" style="margin:6px 0 0">Hanya tunai yang masuk hitungan kas laci.</p>
                        </div>
                        <div class="field" style="margin-top:16px"><label for="uang-hitung">Uang yang dihitung di laci</label>
                            <input id="uang-hitung" class="input" type="number" min="0" step="any" x-model="uangDihitung" required placeholder="0" style="font-size:18px"></div>
                        <div class="selisih-box" x-show="uangDihitung !== ''" :class="selisihShift() === 0 ? 'pas' : (selisihShift() < 0 ? 'kurang' : 'lebih')" role="status">
                            <span x-text="selisihShift() === 0 ? 'Kas pas' : (selisihShift() < 0 ? 'Uang kurang' : 'Uang lebih')"></span>
                            <b x-text="rupiah(Math.abs(selisihShift()))"></b>
                        </div>
                        <div class="field" style="margin-top:14px"><label for="cat-shift">Catatan (opsional)</label><textarea id="cat-shift" class="textarea" x-model="catatanShift" maxlength="500" placeholder="Mis. kembalian kurang, uang pecahan"></textarea></div>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn" @click="tutupModal = false">Batal</button>
                        <button class="btn btn-primary" :disabled="busyShift" x-text="busyShift ? 'Menutup…' : 'Tutup & cetak rekap'"></button></div>
                </form>
            </div>

            <!-- KASIR layar penuh -->
            <div x-show="form" x-cloak class="pos-screen" @keydown.escape.window="form = false" role="dialog" aria-modal="true" aria-label="Kasir">
                <div class="pos-head">
                    <div class="logo" style="width:38px;height:38px;font-size:20px;border-radius:12px" x-html="icon('bean')"></div>
                    <div><h2>Kasir</h2><small>Ketuk menu untuk menambahkan ke pesanan</small></div>
                    <span style="flex:1"></span>
                    <button type="button" class="btn" @click="form = false"><span x-html="icon('x')"></span>Tutup</button>
                </div>
                <div class="pos-body">
                    <section class="pos-catalog">
                        <div class="search" style="max-width:none">
                            <span x-html="icon('search')"></span>
                            <input class="input" type="search" x-model="cari" placeholder="Cari kopi, minuman, camilan…" autocomplete="off" aria-label="Cari menu">
                        </div>
                        <div class="cats">
                            <button type="button" class="cat" :class="!kat && 'on'" @click="kat = ''">Semua</button>
                            <template x-for="c in kategoriList()" :key="c.id"><button type="button" class="cat" :class="kat === c.id && 'on'" @click="kat = c.id" x-text="c.nama"></button></template>
                        </div>
                        <div class="pitems">
                            <template x-for="p in grid()" :key="p.id">
                                <button type="button" class="pitem" @click="tambah(p)" :disabled="Number(p.stok) <= 0" :aria-label="`Tambah ${p.nama}`">
                                    <span class="qty-badge" x-show="qtyDi(p) > 0" x-text="angka(qtyDi(p))"></span>
                                    <span class="opt-flag" x-show="adaOpsi(p)">Custom</span>
                                    <div class="art thumb" :class="'tone-' + art(p).tone">
                                        <span class="fb" x-html="icon(art(p).icon)"></span>
                                        <template x-if="p.image_url"><img :src="p.image_url" alt="" loading="lazy" x-on:error="$el.remove()"></template>
                                    </div>
                                    <div class="info"><div class="nm" x-text="p.nama"></div>
                                        <div class="pr" x-text="rupiah(p.harga_jual)"></div>
                                        <div class="sk" x-text="Number(p.stok) <= 0 ? 'Habis' : `Stok ${angka(p.stok)} ${p.satuan}`"></div></div>
                                </button>
                            </template>
                        </div>
                        <div class="empty" x-show="!grid().length"><div class="ico" x-html="icon('search')"></div>Menu tidak ditemukan.</div>
                    </section>

                    <aside class="pos-cart" :class="keranjangBuka && 'open'" aria-label="Pesanan">
                        <div class="head">
                            <button type="button" class="icon-btn kx" @click="keranjangBuka = false" aria-label="Tutup pesanan" x-html="icon('x')"></button>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                <h3 style="margin:0">Pesanan</h3>
                                <div style="display:flex;gap:4px">
                                    <button type="button" class="btn btn-sm" x-show="items.length" @click="tahanAntrean()" title="Tahan pesanan antrean ini"><span x-html="icon('clock')"></span>Tahan</button>
                                    <button type="button" class="btn btn-sm btn-outline" x-show="antreanDitahan.length" @click="holdModal = true" title="Daftar pesanan yang ditahan"><span x-html="icon('clipboard')"></span>Ditahan (<span x-text="antreanDitahan.length"></span>)</button>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px">
                                <select class="select" style="flex:1" x-model="extra.pembeli_id" @change="tukarPoin = 0" aria-label="Pembeli">
                                    <option value="">Pembeli umum</option>
                                    <template x-for="b in pembeliList" :key="b.id"><option :value="b.id" x-text="`${b.nama} (Poin: ${b.poin || 0})`"></option></template>
                                </select>
                                <button type="button" class="btn btn-sm" @click="bukaModalPembeli()" title="Tambah pembeli baru">+ Pembeli</button>
                            </div>
                            <div x-show="pembeliTerpilih() && pembeliTerpilih().poin > 0" style="display:flex;align-items:center;justify-content:space-between;background:rgba(212,165,116,0.15);border:1px solid rgba(212,165,116,0.3);padding:6px 10px;border-radius:8px;font-size:12px;margin-bottom:8px">
                                <span>Poin: <strong x-text="pembeliTerpilih().poin"></strong> (<span x-text="rupiah(pembeliTerpilih().poin * 1000)"></span>)</span>
                                <button type="button" class="btn btn-sm" @click="togglePakaiPoin()" x-text="tukarPoin > 0 ? 'Batal Pakai' : 'Pakai Poin'"></button>
                            </div>
                            <div class="tipe" role="group" aria-label="Jenis pesanan">
                                <button type="button" :class="tipe === 'dine_in' && 'on'" :aria-pressed="tipe === 'dine_in'" @click="tipe = 'dine_in'">Makan di tempat</button>
                                <button type="button" :class="tipe === 'bawa_pulang' && 'on'" :aria-pressed="tipe === 'bawa_pulang'" @click="tipe = 'bawa_pulang'">Bawa pulang</button>
                            </div>
                            <input class="input" x-show="tipe === 'dine_in'" x-model="meja" maxlength="10" placeholder="No. meja (opsional)" aria-label="Nomor meja" style="margin-top:10px">
                        </div>
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
                                        <div class="pr" x-text="`${rupiah(it.harga)} / ${it.produk.satuan}`"></div>
                                        <div class="stepper">
                                            <button type="button" @click="ubah(i, -1)" aria-label="Kurangi" x-html="icon('minus')"></button>
                                            <input type="text" inputmode="numeric" :value="it.qty" readonly aria-label="Jumlah">
                                            <button type="button" @click="ubah(i, 1)" aria-label="Tambah" x-html="icon('plus')"></button>
                                        </div>
                                    </div>
                                    <div class="sub" x-text="rupiah(it.qty * it.harga)"></div>
                                </div>
                            </template>
                            <div class="empty sm" x-show="!items.length"><div class="ico" x-html="icon('cart')"></div>Pesanan masih kosong.</div>
                        </div>
                        <div class="pos-pay">
                            <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError"></div>
                            <div class="voucher-box">
                                <div class="vrow" x-show="!vinfo">
                                    <input class="input" x-model="kode" maxlength="30" placeholder="Kode voucher" aria-label="Kode voucher" @keydown.enter.prevent="pakaiVoucher()">
                                    <button type="button" class="btn btn-sm" :disabled="vbusy || !kode.trim()" @click="pakaiVoucher()">Pakai</button>
                                </div>
                                <div class="vok" x-show="vinfo"><span x-html="icon('ticket')"></span><span>Voucher <b x-text="vinfo?.voucher_kode"></b> terpasang</span>
                                    <button type="button" class="btn btn-sm btn-link" @click="lepasVoucher()">Lepas</button></div>
                                <p class="hint neg" x-show="verr" x-text="verr" role="alert"></p>
                            </div>
                            <div class="rincian" x-show="adaTambahan() || diskonPoin() > 0">
                                <div class="kv"><span class="muted">Subtotal</span><span x-text="rupiah(rincian().subtotal)"></span></div>
                                <div class="kv" x-show="rincian().diskon > 0"><span class="muted">Diskon</span><span class="pos" x-text="'-' + rupiah(rincian().diskon)"></span></div>
                                <div class="kv" x-show="diskonPoin() > 0"><span class="muted" x-text="`Diskon Poin (${tukarPoin} poin)`"></span><span class="pos" x-text="'-' + rupiah(diskonPoin())"></span></div>
                                <div class="kv" x-show="rincian().service > 0"><span class="muted" x-text="`Service ${angka(peng.service_persen)}%`"></span><span x-text="rupiah(rincian().service)"></span></div>
                                <div class="kv" x-show="rincian().pajak > 0"><span class="muted" x-text="`Pajak ${angka(peng.pajak_persen)}%`"></span><span x-text="rupiah(rincian().pajak)"></span></div>
                            </div>
                            <div class="row"><span class="muted">Total</span><span class="total" x-text="rupiah(total())"></span></div>
                            <div class="metode" role="group" aria-label="Metode pembayaran">
                                <template x-for="m in metodeList" :key="m[0]"><button type="button" :class="metode === m[0] && 'on'" :aria-pressed="metode === m[0]" @click="metode = m[0]"><span x-html="icon(m[2])"></span><span x-text="m[1]"></span></button></template>
                            </div>
                            <div class="row" x-show="metode === 'tunai'"><label class="muted" for="uang">Uang diterima</label><input id="uang" class="input right" style="max-width:190px" type="number" min="0" step="any" x-model="bayar" placeholder="0"></div>
                            <div class="quick" x-show="metode === 'tunai'"><template x-for="v in cepat()" :key="v"><button type="button" @click="bayar = v" x-text="v === total() ? 'Uang pas' : rupiah(v)"></button></template></div>
                            <div class="row" x-show="metode === 'tunai'"><span class="muted" x-text="kembalian() < 0 ? 'Kurang' : 'Kembalian'"></span>
                                <span class="strong" :class="kembalian() < 0 ? 'neg' : 'pos'" x-text="rupiah(Math.abs(kembalian()))"></span></div>
                            <p class="hint" x-show="metode !== 'tunai'" style="margin:0">Pastikan pembayaran <b x-text="metodeNama(metode)"></b> sudah diterima sebelum menekan tombol.</p>
                            <button type="button" @click="simpan()" :disabled="saving || !bisaBayar()" class="btn btn-primary btn-lg btn-block"><span x-html="icon('printer')"></span><span x-text="saving ? 'Memproses…' : 'Bayar & Cetak Nota'"></span></button>
                        </div>
                    </aside>
                </div>
                <button type="button" class="pos-bar" x-show="!keranjangBuka" @click="keranjangBuka = true">
                    <span x-html="icon('cart')"></span><span x-text="`${jumlahItem()} item`"></span><span class="grow"></span><strong x-text="rupiah(total())"></strong><span>Lihat pesanan</span>
                </button>
            </div>

            <!-- Detail nota -->
            <div x-show="detail" x-cloak class="backdrop" @click.self="detail = null" @keydown.escape.window="detail = null">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Detail nota">
                    <div class="modal-head"><h2><span x-text="detail?.no_nota"></span><small x-text="`${detail?.nomor_antrean ?? ''} · ${detail?.tipe === 'dine_in' ? 'Makan di tempat' + (detail?.meja ? ' (meja ' + detail.meja + ')' : '') : 'Bawa pulang'} · ${waktu(detail?.tanggal)}`"></small></h2>
                        <button type="button" class="icon-btn" @click="detail = null" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body" style="gap:0">
                        <template x-for="d in detail?.detail || []" :key="d.id">
                            <div class="kv"><span><span x-text="`${d.produk.nama} × ${angka(d.qty)} ${d.produk.satuan}`"></span><small class="muted blk" x-show="opsiLabel(d)" x-text="opsiLabel(d)"></small></span><span x-text="rupiah(d.subtotal)"></span></div>
                        </template>
                        <div class="kv" x-show="Number(detail?.diskon) > 0 || Number(detail?.service) > 0 || Number(detail?.pajak) > 0"><span class="muted">Subtotal</span><span x-text="rupiah(detail?.subtotal)"></span></div>
                        <div class="kv" x-show="Number(detail?.diskon) > 0"><span class="muted" x-text="`Diskon ${detail?.voucher_kode ?? ''}`"></span><span class="pos" x-text="'-' + rupiah(detail?.diskon)"></span></div>
                        <div class="kv" x-show="Number(detail?.service) > 0"><span class="muted">Service</span><span x-text="rupiah(detail?.service)"></span></div>
                        <div class="kv" x-show="Number(detail?.pajak) > 0"><span class="muted">Pajak</span><span x-text="rupiah(detail?.pajak)"></span></div>
                        <div class="kv strong"><span>Total</span><span x-text="rupiah(detail?.total)"></span></div>
                        <div class="kv"><span class="muted">Metode</span><span x-text="metodeNama(detail?.metode)"></span></div>
                        <div class="kv" x-show="detail?.metode === 'tunai'"><span class="muted">Bayar</span><span x-text="rupiah(detail?.bayar)"></span></div>
                        <div class="kv" x-show="detail?.metode === 'tunai'"><span class="muted">Kembalian</span><span x-text="rupiah(detail?.kembalian)"></span></div>
                        <div class="alert alert-error" style="margin:12px 0 0" x-show="detail?.void_at"><b>Nota dibatalkan (void)</b><span class="blk" x-text="`${waktu(detail?.void_at)} · ${detail?.void_alasan ?? ''}`"></span></div>
                    </div>
                    <div class="modal-foot">
                        <button x-show="bisaVoid(detail || {})" @click="siapVoid(detail)" class="btn btn-danger"><span x-html="icon('ban')"></span>Void</button>
                        <button x-show="canWrite() && !detail?.void_at" @click="kirimStrukWa(detail, detail?.pembeli?.telepon)" class="btn" style="background:#25d366;color:#fff;border-color:#25d366"><span x-html="icon('wa')"></span>Kirim WA</button>
                        <button x-show="canWrite() && !detail?.void_at" @click="nota(detail.id)" class="btn"><span x-html="icon('printer')"></span>Cetak Nota</button>
                        <a x-show="canWrite() && !detail?.void_at" :href="`/api/v1/penjualan/${detail?.id}/escpos?format=text`" target="_blank" class="btn">Teks ESC/POS</a>
                        <button @click="detail = null" class="btn btn-primary">Tutup</button>
                    </div>
                </div>
            </div>

            <!-- Void nota -->
            <div x-show="voidRow" x-cloak class="backdrop" @keydown.escape.window="voidRow = null">
                <form @submit.prevent="jalankanVoid()" class="modal" role="dialog" aria-modal="true" aria-label="Void nota">
                    <div class="modal-head"><h2>Void nota<small x-text="voidRow?.no_nota + ' · ' + rupiah(voidRow?.total)"></small></h2>
                        <button type="button" class="icon-btn" @click="voidRow = null" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-info" style="margin:0">Stok menu dan bahan dikembalikan, voucher dibebaskan, dan nota keluar dari laporan. Aksi ini tercatat di log aktivitas.</div>
                        <div class="alert alert-error" style="margin:0" x-show="errVoid" x-text="errVoid" role="alert"></div>
                        <div class="field"><label for="void-alasan">Alasan void</label>
                            <input id="void-alasan" class="input" x-model="alasanVoid" required minlength="3" maxlength="200" placeholder="Mis. salah input menu, pelanggan batal"></div>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn" @click="voidRow = null">Batal</button>
                        <button class="btn btn-danger" :disabled="busyVoid" x-text="busyVoid ? 'Memproses…' : 'Void nota'"></button></div>
                </form>
            </div>

            <!-- Kas masuk/keluar -->
            <div x-show="kasModal" x-cloak class="backdrop" @keydown.escape.window="kasModal = false">
                <form @submit.prevent="simpanKas()" class="modal" role="dialog" aria-modal="true" aria-label="Kas laci">
                    <div class="modal-head"><h2 x-text="kas.tipe === 'masuk' ? 'Kas masuk' : 'Kas keluar'"></h2>
                        <button type="button" class="icon-btn" @click="kasModal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-info" style="margin:0" x-text="kas.tipe === 'masuk' ? 'Uang yang ditambahkan ke laci di luar penjualan, mis. tambahan kembalian.' : 'Uang yang diambil dari laci untuk keperluan kedai, mis. beli es batu atau galon.'"></div>
                        <div class="alert alert-error" style="margin:0" x-show="errKas" x-text="errKas" role="alert"></div>
                        <div class="field"><label for="kas-jumlah">Jumlah (Rp)</label><input id="kas-jumlah" class="input" type="number" min="1" step="any" x-model="kas.jumlah" required style="font-size:18px"></div>
                        <div class="field"><label for="kas-ket">Keterangan</label><input id="kas-ket" class="input" x-model="kas.keterangan" required minlength="3" maxlength="200" placeholder="Wajib diisi"></div>
                        <div x-show="shift?.kas?.length"><small class="muted">Catatan kas shift ini</small>
                            <template x-for="k in shift?.kas || []" :key="k.id"><div class="kv"><span x-text="(k.tipe === 'masuk' ? '+ ' : '- ') + k.keterangan"></span><span :class="k.tipe === 'masuk' ? 'pos' : 'neg'" x-text="rupiah(k.jumlah)"></span></div></template></div>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn" @click="kasModal = false">Batal</button>
                        <button class="btn btn-primary" :disabled="busyKas" x-text="busyKas ? 'Menyimpan…' : 'Catat'"></button></div>
                </form>
            </div>

            <!-- Tambah pembeli baru cepat di POS -->
            <div x-show="pembeliBaruModal" x-cloak class="backdrop" @keydown.escape.window="pembeliBaruModal = false" style="z-index:92">
                <form @submit.prevent="simpanPembeliBaru()" class="modal" role="dialog" aria-modal="true" aria-label="Tambah Pembeli Baru">
                    <div class="modal-head">
                        <h2>Tambah Pembeli<small>Pelanggan baru akan otomatis terpilih di kasir</small></h2>
                        <button type="button" class="icon-btn" @click="pembeliBaruModal = false" aria-label="Tutup" x-html="icon('x')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="errPembeliBaru" x-text="errPembeliBaru" role="alert"></div>
                        <div class="field"><label for="pb-nama">Nama pembeli</label><input id="pb-nama" class="input" type="text" x-model="pembeliBaru.nama" required placeholder="Nama lengkap"></div>
                        <div class="field"><label for="pb-telp">Nomor telepon (opsional)</label><input id="pb-telp" class="input" type="tel" x-model="pembeliBaru.telepon" placeholder="08xxxxxxxxxx"></div>
                        <div class="field"><label for="pb-alamat">Alamat (opsional)</label><textarea id="pb-alamat" class="textarea" x-model="pembeliBaru.alamat" placeholder="Alamat pembeli"></textarea></div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn" @click="pembeliBaruModal = false">Batal</button>
                        <button class="btn btn-primary" :disabled="busyPembeliBaru" x-text="busyPembeliBaru ? 'Menyimpan…' : 'Simpan Pembeli'"></button>
                    </div>
                </form>
            </div>

            <!-- Modal Antrean Ditahan (Hold / Recall Orders) -->
            <div x-show="holdModal" x-cloak class="backdrop" @keydown.escape.window="holdModal = false" style="z-index:92">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Daftar Antrean Ditahan">
                    <div class="modal-head">
                        <h2>Antrean Ditahan<small x-text="antreanDitahan.length + ' transaksi sedang menunggu'"></small></h2>
                        <button type="button" class="icon-btn" @click="holdModal = false" aria-label="Tutup" x-html="icon('x')"></button>
                    </div>
                    <div class="modal-body">
                        <template x-if="!antreanDitahan.length">
                            <div class="empty"><div class="ico" x-html="icon('history')"></div>Tidak ada antrean yang sedang ditahan.</div>
                        </template>
                        <template x-if="antreanDitahan.length">
                            <div class="grid" style="display:grid;gap:10px">
                                <template x-for="(o, idx) in antreanDitahan" :key="o.id">
                                    <div class="card" style="padding:12px;display:flex;justify-content:space-between;align-items:center">
                                        <div>
                                            <div style="font-weight:600" x-text="o.nama"></div>
                                            <small class="muted" x-text="`${o.waktu} · ${o.items.length} menu · ${rupiah(o.total)}`"></small>
                                        </div>
                                        <div style="display:flex;gap:6px">
                                            <button type="button" class="btn btn-sm btn-primary" @click="pulihkanAntrean(idx)">Lanjutkan</button>
                                            <button type="button" class="btn btn-sm btn-danger" @click="hapusTahanAntrean(idx)">Batal</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn" @click="holdModal = false">Tutup</button>
                    </div>
                </div>
            </div>

            <!-- Modal Sukses Transaksi (Kirim Struk WA & Cetak) -->
            <div x-show="suksesModal" x-cloak class="backdrop" @keydown.escape.window="suksesModal = false" style="z-index:93">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Transaksi Sukses" style="max-width:440px;text-align:center">
                    <div class="modal-body" style="padding:24px 20px">
                        <div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;color:#16a34a;display:inline-flex;align-items:center;justify-content:center;margin:0 auto 12px">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <h2 style="margin:0 0 4px">Transaksi Berhasil!</h2>
                        <p class="muted" style="margin:0 0 16px" x-text="`${terakhirSukses?.no_nota || ''} · Antrean #${terakhirSukses?.nomor_antrean || '-'}`"></p>
                        
                        <div style="background:var(--bg-alt, #f8fafc);border-radius:8px;padding:12px;margin-bottom:16px;text-align:left">
                            <div class="kv"><span>Total Tagihan</span><span class="strong" x-text="rupiah(terakhirSukses?.total || 0)"></span></div>
                            <div class="kv" x-show="terakhirSukses?.metode === 'tunai'"><span>Kembalian</span><span class="pos strong" x-text="rupiah(terakhirSukses?.kembalian || 0)"></span></div>
                            <div class="kv" x-show="(terakhirSukses?.poin_didapat || 0) > 0"><span>Poin Didapat</span><span class="pos strong" x-text="`+${terakhirSukses?.poin_didapat} poin`"></span></div>
                        </div>

                        <div class="field" style="text-align:left">
                            <label for="wa-input-sukses">Nomor WhatsApp Pelanggan (opsional)</label>
                            <input id="wa-input-sukses" class="input" type="tel" x-model="waNomor" placeholder="Mis. 081234567890">
                        </div>
                    </div>
                    <div class="modal-foot" style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                        <button type="button" class="btn" style="background:#25d366;color:#fff;border-color:#25d366;flex:1" @click="kirimStrukWa(terakhirSukses, waNomor)">
                            <span x-html="icon('wa')"></span>Kirim WA
                        </button>
                        <button type="button" class="btn" style="flex:1" @click="nota(terakhirSukses?.id)">
                            <span x-html="icon('printer')"></span>Cetak Nota
                        </button>
                        <a :href="`/api/v1/penjualan/${terakhirSukses?.id}/escpos?format=text`" target="_blank" class="btn" style="flex:1">
                            Teks Thermal
                        </a>
                        <button type="button" class="btn btn-primary" @click="suksesModal = false">Selesai</button>
                    </div>
                </div>
            </div>
        </div>
        </template>
