        <!-- ============ ADMIN/OWNER: RIWAYAT SHIFT ============ -->
        <template x-if="cfg().type === 'shiftlog'">
        <div x-data="shiftlog()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="toolbar">
                <input class="input" style="width:auto" type="month" x-model="bulan" @change="load()" aria-label="Filter bulan">
                <button class="btn btn-sm btn-ghost" x-show="bulan" @click="bulan = ''; load()">Semua bulan</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Kasir</th><th>Dibuka</th><th>Ditutup</th><th class="num">Modal</th><th class="num">Transaksi</th><th class="num">Penjualan</th><th class="num">Kas seharusnya</th><th class="num">Dihitung</th><th>Selisih</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="r in rows" :key="r.id">
                                <tr>
                                    <td class="strong" x-text="r.user?.name"></td>
                                    <td class="muted" x-text="waktu(r.dibuka_pada)"></td>
                                    <td class="muted" x-text="r.ditutup_pada ? waktu(r.ditutup_pada) : '-'"></td>
                                    <td class="num" x-text="rupiah(r.modal_awal)"></td>
                                    <td class="num" x-text="r.jumlah_transaksi ?? '-'"></td>
                                    <td class="num" x-text="r.ditutup_pada ? rupiah(r.total_penjualan) : '-'"></td>
                                    <td class="num" x-text="r.ditutup_pada ? rupiah(r.kas_seharusnya) : '-'"></td>
                                    <td class="num" x-text="r.ditutup_pada ? rupiah(r.uang_dihitung) : '-'"></td>
                                    <td><span class="badge" :class="selisihCls(r)" x-text="selisihTeks(r)"></span></td>
                                    <td class="actions"><button class="btn btn-sm btn-ghost" @click="cetak(r)"><span x-html="icon('printer')"></span>Rekap</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat…</div>
                <div class="empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('clock')"></div>Belum ada shift.</div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>
        </div>
        </template>
