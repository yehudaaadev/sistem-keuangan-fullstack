        <!-- ============ STAF: ANTREAN PESANAN ONLINE ============ -->
        <template x-if="cfg().type === 'pesananstaff'">
        <div x-data="pesananstaff()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="tabs qtabs" role="tablist">
                <template x-for="t in tabs" :key="t[0]">
                    <button type="button" role="tab" class="cat" :class="tab === t[0] && 'on'" :aria-selected="tab === t[0]" @click="pilih(t[0])">
                        <span x-text="t[1]"></span><span class="count" x-show="hitung[t[0]] > 0" x-text="hitung[t[0]]"></span>
                    </button>
                </template>
            </div>
<div class="qlayout">
            <div>
            <div class="loading" x-show="loading">Memuat antrean…</div>
            <div class="paper-wrap slip" x-show="!loading && !rows.length">
                <article class="paper slip-kosong">
                    <div class="sk-head"><b>Antrean kosong</b><span x-text="tabs.find((t) => t[0] === tab)?.[1]"></span></div>
                    <p>Tidak ada pesanan di tahap ini.</p>
                    <p class="kecil">Pesanan online yang sudah dibayar muncul di sini otomatis, dicek tiap 15 detik.</p>
                    <div class="sk-barcode"></div>
                </article>
            </div>
            <div class="qgrid">
                <template x-for="p in rows" :key="p.id">
                    <div class="paper-wrap"><article class="paper ocard">
                        <header>
                            <div style="display:flex;gap:12px;align-items:center"><div class="antrean-no" x-text="p.nomor_antrean || '—'"></div><div><div class="strong" x-text="p.kode"></div><div class="muted" x-text="`${p.user?.name} · ${waktu(p.dibayar_pada || p.created_at)}`"></div></div></div>
                            <span class="badge" :class="'b-' + p.status" x-text="statusLabel(p.status)"></span>
                        </header>
                        <p class="muted" style="margin:0;font-size:13px" x-show="p.user?.telepon" x-text="'Telp ' + p.user?.telepon"></p>
                        <div><span class="badge" :class="p.tipe === 'dine_in' ? 'b-dibayar' : 'b-diproses'" x-text="p.tipe === 'dine_in' ? 'Makan di tempat' + (p.meja ? ' · Meja ' + p.meja : '') : 'Bawa pulang'"></span></div>
                        <ul class="oitems">
                            <template x-for="d in p.detail" :key="d.id"><li><span><span x-text="`${angka(d.qty)} × ${d.produk.nama}`"></span><small class="muted blk" x-show="opsiLabel(d)" x-text="opsiLabel(d)"></small></span></li></template>
                        </ul>
                        <div class="quote" x-show="p.catatan" x-text="'“' + p.catatan + '”'"></div>
                        <footer>
                            <strong class="serif" style="font-size:19px" x-text="rupiah(p.total)"></strong>
                            <button class="btn btn-primary btn-sm" x-show="aksi(p)" :disabled="busy === p.kode" @click="maju(p)" x-text="busy === p.kode ? 'Menyimpan…' : aksi(p)"></button>
                        </footer>
                    </article></div>
                </template>
            </div>
            </div>
            <aside class="card alur">
                <div class="card-head"><div><h3>Alur pesanan</h3><small>Dari dibayar sampai diambil</small></div></div>
                <ol class="alur-list">
                    <template x-for="(t, i) in tabs.slice(0, 4)" :key="t[0]">
                        <li :class="tab === t[0] && 'on'"><button type="button" @click="pilih(t[0])"><span class="no" x-text="i + 1"></span><span class="nm" x-text="t[1]"></span><span class="ct" x-text="hitung[t[0]] || 0"></span></button></li>
                    </template>
                </ol>
                <div class="alur-note"><span x-html="icon('bag')"></span><p><b>Perlu refund</b> <span x-text="`${hitung.perlu_refund || 0} pesanan`"></span><br><small>Pesanan yang sudah dibayar tapi dibatalkan atau stok habis.</small></p></div>
            </aside>
            </div>
        </div>
        </template>
