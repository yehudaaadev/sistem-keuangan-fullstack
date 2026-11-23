        <!-- ============ OWNER: LAPORAN PENJUALAN ============ -->
        <template x-if="cfg().type === 'laporanjual'">
        <div x-data="laporanjual()" x-init="init()">
            <div class="toolbar">
                <input class="input" style="width:auto" type="date" x-model="dari" @change="load()" aria-label="Dari tanggal">
                <span class="muted">s.d.</span>
                <input class="input" style="width:auto" type="date" x-model="sampai" @change="load()" aria-label="Sampai tanggal">
                <button class="cat" @click="preset(7)">7 hari</button><button class="cat" @click="preset(30)">30 hari</button><button class="cat" @click="bulanIni()">Bulan ini</button>
            </div>
            <div class="alert alert-error" x-show="error" x-text="error"></div>
            <div class="loading" x-show="loading && !d">Memuat laporan…</div>
            <template x-if="d">
            <div>
                <div class="kpi-grid">
                    <div class="card kpi"><div class="top"><span class="lbl">Omzet (di luar pajak)</span><span class="chip-ico info" x-html="icon('trendUp')"></span></div><div class="val" x-text="rupiah(d.ringkasan.omzet)"></div><div class="sub" x-text="`${d.ringkasan.transaksi} transaksi · rata-rata ${rupiah(d.ringkasan.rata_rata)}`"></div></div>
                    <div class="card kpi"><div class="top"><span class="lbl">Jam paling ramai</span><span class="chip-ico accent" x-html="icon('clock')"></span></div><div class="val" x-text="jamRamai()"></div><div class="sub">Berdasarkan jumlah transaksi</div></div>
                    <div class="card kpi"><div class="top"><span class="lbl">Diskon diberikan</span><span class="chip-ico ok" x-html="icon('ticket')"></span></div><div class="val" x-text="rupiah(d.ringkasan.diskon)"></div><div class="sub" x-text="`Pajak ${rupiah(d.ringkasan.pajak)} · service ${rupiah(d.ringkasan.service)}`"></div></div>
                </div>
                <div class="alert alert-info" x-show="d.ringkasan.void > 0" x-text="`${d.ringkasan.void} nota di-void senilai ${rupiah(d.ringkasan.nilai_void)}. Nota void tidak dihitung dalam laporan ini.`"></div>

                <div class="card" style="margin-bottom:18px">
                    <div class="card-head head-pad"><div><h3>Omzet harian</h3><small>Tiap batang satu hari</small></div></div>
                    <div class="vbars" role="img" aria-label="Grafik omzet harian">
                        <template x-for="h in d.per_hari" :key="h.tanggal"><div class="b" :style="`height:${pct(h, d.per_hari, 'omzet')}%`" :title="`${tglPendek(h.tanggal)}: ${rupiah(h.omzet)} (${h.transaksi} transaksi)`"></div></template>
                    </div>
                    <div class="vlabels" x-show="d.per_hari.length"><span x-text="tglPendek(d.per_hari[0]?.tanggal)"></span><span x-text="tglPendek(d.per_hari[d.per_hari.length - 1]?.tanggal)"></span></div>
                    <div class="empty sm" x-show="!d.per_hari.length">Belum ada penjualan pada periode ini.</div>
                </div>

                <div class="grid-eq" style="margin-bottom:18px">
                    <div class="card">
                        <div class="card-head head-pad"><div><h3>Jam ramai</h3><small>Jumlah transaksi per jam</small></div></div>
                        <div class="vbars" role="img" aria-label="Transaksi per jam">
                            <template x-for="j in d.per_jam" :key="j.jam"><div class="b" :style="`height:${pct(j, d.per_jam, 'transaksi')}%`" :title="`Jam ${j.jam}.00: ${j.transaksi} transaksi`"></div></template>
                        </div>
                        <div class="vlabels"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
                    </div>
                    <div class="card">
                        <div class="card-head head-pad"><h3>Metode bayar</h3></div>
                        <div style="padding:0 20px 16px">
                            <template x-for="m in d.per_metode" :key="m.metode"><div class="kv"><span x-text="`${metodeNama(m.metode)} · ${m.transaksi}x`"></span><span class="strong" x-text="rupiah(m.total)"></span></div></template>
                            <div class="empty sm" x-show="!d.per_metode.length">Belum ada data.</div>
                        </div>
                        <div class="card-head head-pad"><h3>Kinerja kasir</h3></div>
                        <div style="padding:0 20px 16px">
                            <template x-for="k in d.per_kasir" :key="k.nama"><div class="kv"><span x-text="`${k.nama} · ${k.transaksi}x`"></span><span class="strong" x-text="rupiah(k.omzet)"></span></div></template>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head head-pad"><div><h3>Menu terlaris</h3><small>15 teratas menurut jumlah terjual, lengkap dengan laba kotornya</small></div></div>
                    <div class="table-wrap"><table class="data">
                        <thead><tr><th>#</th><th>Menu</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Laba kotor</th></tr></thead>
                        <tbody><template x-for="(m, i) in d.per_menu" :key="m.id"><tr><td class="muted" x-text="i + 1"></td><td class="strong" x-text="m.nama"></td><td class="num" x-text="angka(m.qty)"></td><td class="num" x-text="rupiah(m.omzet)"></td><td class="num strong" :class="m.laba < 0 ? 'neg' : 'pos'" x-text="rupiah(m.laba)"></td></tr></template></tbody>
                    </table></div>
                    <div class="empty sm" x-show="!d.per_menu.length">Belum ada penjualan pada periode ini.</div>
                </div>
            </div>
            </template>
        </div>
        </template>
