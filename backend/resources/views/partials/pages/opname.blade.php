        <!-- ============ ADMIN: STOK OPNAME ============ -->
        <template x-if="cfg().type === 'opname'">
        <div x-data="opname()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="toolbar"><span class="grow"></span>
                <button class="btn btn-primary" @click="baru()"><span x-html="icon('clipboard')"></span>Opname baru</button></div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Tanggal</th><th>Petugas</th><th>Catatan</th><th class="num">Item dihitung</th><th class="num">Item selisih</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="o in rows" :key="o.id">
                                <tr><td class="strong" x-text="waktu(o.created_at)"></td><td x-text="o.user?.name"></td><td class="muted" x-text="o.catatan || '-'"></td>
                                    <td class="num" x-text="o.detail_count"></td>
                                    <td class="num"><span class="badge" :class="o.selisih_item > 0 ? 'b-menipis' : 'b-aman'" x-text="o.selisih_item > 0 ? o.selisih_item + ' berbeda' : 'Cocok'"></span></td>
                                    <td><span class="badge" :class="o.status === 'disetujui' ? 'b-aman' : (o.status === 'menunggu_persetujuan' ? 'b-menipis' : 'b-rugi')" x-text="o.status === 'menunggu_persetujuan' ? 'Menunggu Approval' : (o.status === 'disetujui' ? 'Disetujui' : 'Ditolak')"></span></td>
                                    <td class="actions"><button class="btn btn-sm btn-link" @click="lihat(o)">Detail</button></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat…</div>
                <div class="empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('clipboard')"></div>Belum pernah opname. Lakukan berkala agar stok sistem tetap cocok dengan rak.</div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>

            <div x-show="form" x-cloak class="backdrop" @keydown.escape.window="form = false">
                <form @submit.prevent="simpan()" class="modal lg" role="dialog" aria-modal="true" aria-label="Stok opname">
                    <div class="modal-head"><h2>Stok opname<small>Isi hanya item yang sudah dihitung. Yang dikosongkan tidak diubah.</small></h2>
                        <button type="button" class="icon-btn" @click="form = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError" role="alert"></div>
                        <div class="tipe" role="group" aria-label="Jenis item">
                            <button type="button" :class="jenis === 'bahan' && 'on'" @click="jenis = 'bahan'">Bahan baku</button>
                            <button type="button" :class="jenis === 'produk' && 'on'" @click="jenis = 'produk'">Menu tanpa resep</button>
                        </div>
                        <input class="input" type="search" x-model="cari" placeholder="Cari item…" aria-label="Cari item">
                        <div class="opname-list">
                            <template x-for="x in daftar()" :key="kunci(x)">
                                <div class="opname-row">
                                    <div class="grow"><b x-text="x.nama"></b><small class="muted blk" x-text="`Sistem: ${angka(x.stok)} ${x.satuan}`"></small></div>
                                    <select class="select sm" x-show="selisih(x) !== null && selisih(x) !== 0" x-model="alasanMap[kunci(x)]" style="width:140px;font-size:12px" aria-label="Alasan selisih">
                                        <option value="selisih_hitung">Selisih Hitung</option>
                                        <option value="susut_wajar">Susut Wajar</option>
                                        <option value="rusak_kadaluwarsa">Rusak/Basi</option>
                                        <option value="hilang">Hilang</option>
                                    </select>
                                    <input class="input right" type="number" min="0" step="any" x-model="fisik[kunci(x)]" placeholder="Hitungan fisik" :aria-label="'Stok fisik ' + x.nama">
                                    <span class="sel" :class="selisih(x) === null ? '' : (selisih(x) === 0 ? 'pos' : 'neg')" x-text="selisih(x) === null ? '' : (selisih(x) > 0 ? '+' : '') + angka(selisih(x))"></span>
                                </div>
                            </template>
                            <div class="empty sm" x-show="!daftar().length">Tidak ada item.</div>
                        </div>
                        <div class="field"><label for="op-cat">Catatan (opsional)</label><input id="op-cat" class="input" x-model="catatan" maxlength="300" placeholder="Mis. opname akhir bulan"></div>
                    </div>
                    <div class="modal-foot" style="justify-content:space-between;align-items:center"><span class="muted" x-text="`${jumlahBeda()} item berselisih`"></span>
                        <span><button type="button" class="btn" @click="form = false">Batal</button> <button class="btn btn-primary" :disabled="saving" x-text="saving ? 'Menyimpan…' : 'Simpan & sesuaikan stok'"></button></span></div>
                </form>
            </div>

            <div x-show="detail" x-cloak class="backdrop" @click.self="detail = null" @keydown.escape.window="detail = null">
                <div class="modal" role="dialog" aria-modal="true" aria-label="Detail opname">
                    <div class="modal-head"><h2>Opname <span x-text="waktu(detail?.created_at)"></span><small x-text="`${detail?.user?.name ?? ''}${detail?.catatan ? ' · ' + detail.catatan : ''}`"></small></h2>
                        <button type="button" class="icon-btn" @click="detail = null" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body" style="gap:0">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding:10px 14px;background:rgba(255,255,255,.04);border-radius:6px">
                            <div>
                                <span class="muted" style="font-size:12px">Status Pengajuan: </span>
                                <b :class="detail?.status === 'disetujui' ? 'pos' : (detail?.status === 'menunggu_persetujuan' ? 'warn' : 'neg')" x-text="detail?.status === 'menunggu_persetujuan' ? 'Menunggu Approval Owner' : (detail?.status === 'disetujui' ? 'Disetujui' : 'Ditolak')"></b>
                                <template x-if="detail?.approved_by">
                                    <small class="muted blk" x-text="`Diverifikasi oleh ${detail.approved_by.name} pada ${waktu(detail.approved_at)}`"></small>
                                </template>
                            </div>
                            <div x-show="detail?.status === 'menunggu_persetujuan' && $store.auth.role === 'owner'" style="display:flex;gap:8px">
                                <button class="btn btn-sm" @click="tolak(detail)">Tolak</button>
                                <button class="btn btn-sm btn-primary" @click="setujui(detail)">Setujui &amp; Sesuaikan</button>
                            </div>
                        </div>
                        <template x-for="d in detail?.detail || []" :key="d.id">
                            <div class="kv">
                                <span>
                                    <span x-text="d.produk?.nama ?? d.bahan_baku?.nama"></span>
                                    <small class="muted blk" x-text="`Sistem ${angka(d.stok_sistem)} → fisik ${angka(d.stok_fisik)}${d.alasan ? ' (' + d.alasan + ')' : ''}`"></small>
                                </span>
                                <span class="strong" :class="Number(d.selisih) === 0 ? 'muted' : (Number(d.selisih) > 0 ? 'pos' : 'neg')" x-text="(Number(d.selisih) > 0 ? '+' : '') + angka(d.selisih)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="modal-foot"><button @click="detail = null" class="btn btn-primary">Tutup</button></div>
                </div>
            </div>
        </div>
        </template>
