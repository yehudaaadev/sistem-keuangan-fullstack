<!-- Modal pembayaran (pelanggan) -->
<div x-show="$store.bayar.pesanan" x-cloak class="backdrop" @keydown.escape.window="$store.bayar.tutup()" style="z-index:90">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bayar-judul">
        <div class="modal-head">
            <h2 id="bayar-judul">Pembayaran<small x-text="$store.bayar.pesanan?.kode"></small></h2>
            <button type="button" class="icon-btn" @click="$store.bayar.tutup()" aria-label="Tutup" x-html="icon('x')"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-error" style="margin:0" x-show="$store.bayar.err" x-text="$store.bayar.err" role="alert"></div>
            <div class="tagihan">
                <div class="muted">Total tagihan</div>
                <div class="big serif" x-text="rupiah($store.bayar.pesanan?.total)"></div>
                <div class="timer" :class="$store.bayar.habis() && 'neg'"><span x-html="icon('clock')"></span>
                    <span x-text="$store.bayar.habis() ? 'Waktu pembayaran habis' : 'Bayar dalam ' + $store.bayar.sisa()"></span></div>
            </div>
            <ul class="oitems" style="margin:0">
                <template x-for="d in $store.bayar.pesanan?.detail || []" :key="d.id"><li><span><span x-text="`${angka(d.qty)} × ${d.produk.nama}`"></span><small class="muted blk" x-show="opsiLabel(d)" x-text="opsiLabel(d)"></small></span><span class="muted" x-text="rupiah(d.subtotal)"></span></li></template>
            </ul>

            <template x-if="!$store.bayar.simulator()">
                <div>
                    <a class="btn btn-primary btn-lg btn-block" :href="$store.bayar.pesanan?.pembayaran_url" target="_blank" rel="noopener"><span x-html="icon('card')"></span>Buka halaman pembayaran</a>
                    <p class="hint" style="text-align:center">Pilih QRIS, e-wallet, atau transfer bank. Halaman ini otomatis berubah setelah pembayaran diterima.</p>
                </div>
            </template>
            <template x-if="$store.bayar.simulator()">
                <div>
                    <div class="alert alert-info" style="margin:0 0 12px"><span>Mode <b>simulasi</b>: tidak ada uang sungguhan. Aktifkan Midtrans di <code>.env</code> untuk pembayaran nyata.</span></div>
                    <button type="button" class="btn btn-primary btn-lg btn-block" :disabled="$store.bayar.busy || $store.bayar.habis()" @click="$store.bayar.simulasi()"><span x-html="icon('check')"></span>Simulasikan pembayaran berhasil</button>
                </div>
            </template>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-danger" :disabled="$store.bayar.busy" @click="$store.bayar.batalkan()">Batalkan pesanan</button>
            <button type="button" class="btn" @click="$store.bayar.tutup()">Bayar nanti</button>
        </div>
    </div>
</div>

<!-- Lembar kustomisasi menu (kasir & pelanggan) -->
<div x-show="$store.opsi.produk" x-cloak class="backdrop" style="z-index:95" @keydown.escape.window="$store.opsi.tutup()" @click.self="$store.opsi.tutup()">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="opsi-judul" x-show="$store.opsi.produk">
        <div class="modal-head">
            <h2 id="opsi-judul"><span x-text="$store.opsi.produk?.nama"></span><small>Atur sesuai seleramu</small></h2>
            <button type="button" class="icon-btn" @click="$store.opsi.tutup()" aria-label="Tutup" x-html="icon('x')"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-error" style="margin:0" x-show="$store.opsi.err" x-text="$store.opsi.err" role="alert"></div>
            <template x-for="g in $store.opsi.produk?.opsi_grup || []" :key="g.id">
                <fieldset class="opsi-grup">
                    <legend><span x-text="g.nama"></span> <small x-text="g.wajib ? 'wajib' : (g.banyak ? 'boleh lebih dari satu' : 'opsional')"></small></legend>
                    <div class="opsi-pilihan">
                        <template x-for="x in g.pilihan" :key="x.id">
                            <button type="button" class="opsi-chip" :class="$store.opsi.ada(g, x) && 'on'" :aria-pressed="$store.opsi.ada(g, x)" @click="$store.opsi.toggle(g, x)">
                                <span x-text="x.nama"></span><small x-show="Number(x.tambahan_harga) > 0" x-text="'+' + rupiah(x.tambahan_harga)"></small>
                            </button>
                        </template>
                    </div>
                </fieldset>
            </template>
        </div>
        <div class="modal-foot" style="justify-content:space-between;align-items:center">
            <strong class="serif" style="font-size:22px;color:var(--accent-2)" x-text="rupiah($store.opsi.produk ? $store.opsi.harga() : 0)"></strong>
            <button type="button" class="btn btn-primary btn-lg" @click="$store.opsi.simpan()"><span x-html="icon('plus')"></span>Tambahkan</button>
        </div>
    </div>
</div>

<!-- Ganti sandi -->
<div x-show="sandi.modal" x-cloak class="backdrop" @keydown.escape.window="sandi.modal = false" style="z-index:90">
    <form @submit.prevent="ubahSandi()" class="modal" role="dialog" aria-modal="true" aria-label="Ganti sandi">
        <div class="modal-head"><h2>Ganti sandi<small>Perangkat lain akan otomatis keluar</small></h2>
            <button type="button" class="icon-btn" @click="sandi.modal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
        <div class="modal-body">
            <div class="alert alert-error" style="margin:0" x-show="sandi.err" x-text="sandi.err" role="alert"></div>
            <div class="field"><label for="sd-lama">Sandi saat ini</label><input id="sd-lama" class="input" type="password" x-model="sandi.lama" required autocomplete="current-password"></div>
            <div class="field"><label for="sd-baru">Sandi baru (min. 8 karakter)</label><input id="sd-baru" class="input" type="password" x-model="sandi.baru" required minlength="8" autocomplete="new-password"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" @click="sandi.modal = false">Batal</button>
            <button class="btn btn-primary" :disabled="sandi.busy" x-text="sandi.busy ? 'Menyimpan…' : 'Ganti sandi'"></button></div>
    </form>
</div>

<!-- Toast -->
<div class="toasts" aria-live="polite">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div class="toast"><div class="dot-ico" :class="t.type === 'ok' ? 'ok' : 'bad'" x-html="icon(t.type === 'ok' ? 'check' : 'alert')"></div><span x-text="t.msg"></span></div>
    </template>
</div>
</body>
@endverbatim
</html>