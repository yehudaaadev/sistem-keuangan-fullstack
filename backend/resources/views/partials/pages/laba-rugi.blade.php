        <!-- ============ LAPORAN LABA/RUGI ============ -->
        <template x-if="cfg().type === 'laporan'">
        <div x-data="laporan()">
            <div class="toolbar">
                <select class="select" x-model.number="bulan" @change="load()" aria-label="Bulan">
                    <option value="0">Semua bulan</option>
                    <template x-for="(n, i) in bulanNama" :key="i"><option :value="i + 1" x-text="n"></option></template>
                </select>
                <input class="input" style="width:112px" type="number" x-model.number="tahun" @change="load()" min="2000" max="2100" aria-label="Tahun">
                <span class="grow"></span>
                <div class="btn-group">
                    <button class="btn" @click="ekspor('pdf')"><span x-html="icon('download')"></span>PDF</button>
                    <button class="btn" @click="ekspor('csv')"><span x-html="icon('download')"></span>Excel</button>
                </div>
            </div>

            <!-- TAB NAVIGASI STANDAR KORPORAT -->
            <div style="display:flex;gap:8px;margin-bottom:18px;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:10px;overflow-x:auto">
                <button class="btn btn-sm" :class="subtab === 'labarugi' ? 'btn-primary' : ''" @click="subtab = 'labarugi'"><span x-html="icon('chart')"></span> Laba/Rugi (P&amp;L)</button>
                <button class="btn btn-sm" :class="subtab === 'aruskas' ? 'btn-primary' : ''" @click="subtab = 'aruskas'"><span x-html="icon('wallet')"></span> Arus Kas (Cash Flow)</button>
                <button class="btn btn-sm" :class="subtab === 'neraca' ? 'btn-primary' : ''" @click="subtab = 'neraca'"><span x-html="icon('banknote')"></span> Neraca (Balance Sheet)</button>
                <button class="btn btn-sm" :class="subtab === 'stok' ? 'btn-primary' : ''" @click="subtab = 'stok'"><span x-html="icon('package')"></span> Valuasi Persediaan</button>
            </div>
            <div class="alert alert-error" x-show="error" x-text="error"></div>
            <div class="loading" x-show="loading && !data">Memuat laporan…</div>
            <template x-if="data">
            <div>
                <!-- ================= TAB 1: LABA/RUGI (P&L) ================= -->
                <div x-show="subtab === 'labarugi'">
                    <!-- Kartu Rasio Finansial Korporat -->
                    <div class="card" style="margin-bottom:18px" x-show="data.rasio_finansial">
                        <div class="card-head head-pad">
                            <div>
                                <h3>Rasio Profitabilitas &amp; Efisiensi Korporat</h3>
                                <small>Benchmark efisiensi biaya bahan (COGS), margin kotor/bersih, dan kesehatan finansial kedai.</small>
                            </div>
                            <span class="badge" :class="data.rasio_finansial?.net_margin >= 15 ? 'b-laba' : 'b-rugi'" x-text="data.rasio_finansial?.kesehatan || 'Evaluasi'"></span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;padding:14px 18px">
                            <div><div class="lbl" style="font-size:12px;color:var(--text-muted)">Gross Profit Margin</div><div class="val strong" style="font-size:18px;color:#38bdf8" x-text="(data.rasio_finansial?.gross_margin || 0) + '%'"></div></div>
                            <div><div class="lbl" style="font-size:12px;color:var(--text-muted)">Net Profit Margin</div><div class="val strong" style="font-size:18px" :class="(data.rasio_finansial?.net_margin || 0) >= 0 ? 'pos' : 'neg'" x-text="(data.rasio_finansial?.net_margin || 0) + '%'"></div></div>
                            <div><div class="lbl" style="font-size:12px;color:var(--text-muted)">Food &amp; Bev Cost (HPP)</div><div class="val strong" style="font-size:18px;color:#fbbf24" x-text="(data.rasio_finansial?.cogs_ratio || 0) + '%'"></div></div>
                            <div><div class="lbl" style="font-size:12px;color:var(--text-muted)">Opex Ratio</div><div class="val strong" style="font-size:18px;color:#f87171" x-text="(data.rasio_finansial?.opex_ratio || 0) + '%'"></div></div>
                        </div>
                    </div>

                    <div class="kpi-grid">
                        <div class="card kpi"><div class="top"><span class="lbl">Total Penjualan</span><span class="chip-ico info" x-html="icon('trendUp')"></span></div>
                            <div class="val" x-text="rupiah(data.total_penjualan)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Total Pembelian</span><span class="chip-ico accent" x-html="icon('truck')"></span></div>
                            <div class="val" x-text="rupiah(data.total_pembelian)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Laba Kotor</span><span class="chip-ico" :class="data.laba_kotor >= 0 ? 'ok' : 'bad'" x-html="icon('wallet')"></span></div>
                            <div class="val" :class="data.laba_kotor < 0 ? 'neg' : 'pos'" x-text="rupiah(data.laba_kotor)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Beban Operasional</span><span class="chip-ico bad" x-html="icon('banknote')"></span></div>
                            <div class="val neg" x-text="rupiah(data.total_beban)"></div></div>
                        <div class="card kpi" :class="'result-' + data.status"><div class="top"><span class="lbl" x-text="data.status === 'laba' ? 'Laba Bersih' : 'Rugi Bersih'"></span>
                            <span class="chip-ico" :class="data.status === 'laba' ? 'ok' : 'bad'" x-html="icon('wallet')"></span></div>
                            <div class="val" x-text="rupiah(Math.abs(data.laba_bersih ?? data.hasil))"></div></div>
                    </div>

                    <div class="card" style="margin-bottom:18px" x-show="data.per_beban && data.per_beban.length">
                        <div class="card-head head-pad"><div><h3>Rincian Beban Operasional</h3><small>Biaya operasional kedai (listrik, air, sewa, gaji barista, internet, dll).</small></div></div>
                        <div class="table-wrap">
                            <table class="data">
                                <thead><tr><th>Kategori Beban</th><th class="num">Jumlah Catatan</th><th class="num">Total Biaya</th></tr></thead>
                                <tbody>
                                    <template x-for="b in data.per_beban || []" :key="b.kategori">
                                        <tr>
                                            <td class="strong" x-text="label(b.kategori)"></td>
                                            <td class="num" x-text="angka(b.jumlah_transaksi) + ' catatan'"></td>
                                            <td class="num strong neg" x-text="rupiah(b.total)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom:18px">
                        <div class="card-head head-pad"><div><h3>Laba kotor per kategori</h3><small>Omzet dikurangi modal barang yang terjual. Menunjukkan kategori mana yang paling banyak menyumbang laba.</small></div></div>
                        <div class="table-wrap">
                            <table class="data">
                                <thead><tr><th>Kategori</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Modal</th><th class="num">Laba kotor</th><th style="min-width:190px">Kontribusi</th></tr></thead>
                                <tbody>
                                    <template x-for="k in data.per_kategori" :key="k.id">
                                        <tr>
                                            <td><div style="display:flex;align-items:center;gap:10px"><span class="dot-ico" :class="'tone-' + art(k.nama).tone" style="color:var(--tone)" x-html="icon(art(k.nama).icon)"></span><span class="strong" x-text="k.nama"></span></div></td>
                                            <td class="num" x-text="angka(k.qty)"></td>
                                            <td class="num" x-text="rupiah(k.omzet)"></td>
                                            <td class="num muted" x-text="rupiah(k.modal)"></td>
                                            <td class="num strong" :class="k.laba < 0 ? 'neg' : 'pos'" x-text="rupiah(k.laba)"></td>
                                            <td><div class="contrib"><div class="meter"><i :style="`width:${Math.max(0, Math.min(100, k.kontribusi))}%`"></i></div><span class="strong" x-text="k.kontribusi + '%'"></span></div></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="empty sm" x-show="!data.per_kategori.length">Belum ada penjualan pada periode ini.</div>
                    </div>

                    <div class="card" x-show="data.per_bulan">
                        <div class="card-head head-pad"><h3>Rincian per bulan</h3></div>
                        <div class="table-wrap">
                            <table class="data">
                                <thead><tr><th>Bulan</th><th class="num">Penjualan</th><th class="num">Pembelian</th><th class="num">Beban</th><th class="num">Laba Bersih</th><th>Status</th></tr></thead>
                                <tbody>
                                    <template x-for="m in data.per_bulan || []" :key="m.bulan">
                                        <tr>
                                            <td class="strong" x-text="bulanNama[m.bulan - 1]"></td>
                                            <td class="num" x-text="rupiah(m.total_penjualan)"></td>
                                            <td class="num" x-text="rupiah(m.total_pembelian)"></td>
                                            <td class="num muted" x-text="rupiah(m.total_beban || 0)"></td>
                                            <td class="num strong" :class="(m.laba_bersih ?? m.hasil) < 0 ? 'neg' : 'pos'" x-text="rupiah(m.laba_bersih ?? m.hasil)"></td>
                                            <td><span class="badge" :class="'b-' + m.status" x-text="m.status === 'laba' ? 'Laba' : 'Rugi'"></span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: ARUS KAS (CASH FLOW) ================= -->
                <div x-show="subtab === 'aruskas'" x-cloak>
                    <div class="kpi-grid" style="margin-bottom:18px">
                        <div class="card kpi"><div class="top"><span class="lbl">Arus Kas Operasi</span><span class="chip-ico info" x-html="icon('wallet')"></span></div>
                            <div class="val" :class="data.arus_kas?.ringkasan.arus_kas_operasi >= 0 ? 'pos' : 'neg'" x-text="rupiah(data.arus_kas?.ringkasan.arus_kas_operasi || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Arus Kas Investasi</span><span class="chip-ico accent" x-html="icon('package')"></span></div>
                            <div class="val" :class="data.arus_kas?.ringkasan.arus_kas_investasi >= 0 ? 'pos' : 'neg'" x-text="rupiah(data.arus_kas?.ringkasan.arus_kas_investasi || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Arus Kas Pendanaan</span><span class="chip-ico" x-html="icon('banknote')"></span></div>
                            <div class="val pos" x-text="rupiah(data.arus_kas?.ringkasan.arus_kas_pendanaan || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Perubahan Kas Bersih</span><span class="chip-ico ok" x-html="icon('trendUp')"></span></div>
                            <div class="val strong" :class="data.arus_kas?.ringkasan.perubahan_bersih >= 0 ? 'pos' : 'neg'" x-text="rupiah(data.arus_kas?.ringkasan.perubahan_bersih || 0)"></div></div>
                    </div>

                    <div class="card" style="margin-bottom:18px">
                        <div class="card-head head-pad">
                            <div><h3>Rincian Laporan Arus Kas 3 Pilar</h3><small>Standar akuntansi korporasi: Aktivitas Operasi, Investasi, dan Pendanaan.</small></div>
                        </div>
                        <div class="table-wrap">
                            <table class="data">
                                <thead><tr><th>Aktivitas Arus Kas</th><th class="num">Penerimaan (Masuk)</th><th class="num">Pengeluaran (Keluar)</th><th class="num">Arus Kas Bersih</th></tr></thead>
                                <tbody>
                                    <tr style="background:rgba(255,255,255,.03)"><td class="strong" colspan="4">1. AKTIVITAS OPERASIONAL</td></tr>
                                    <tr><td style="padding-left:24px">Penjualan Tunai di Kasir</td><td class="num pos" x-text="rupiah(data.arus_kas?.operasi.penjualan_tunai || 0)"></td><td class="num muted">-</td><td></td></tr>
                                    <tr><td style="padding-left:24px">Penerimaan Kas Masuk Shift</td><td class="num pos" x-text="rupiah(data.arus_kas?.operasi.kas_masuk_shift || 0)"></td><td class="num muted">-</td><td></td></tr>
                                    <tr><td style="padding-left:24px">Pembelian Bahan Baku / Belanja Restock</td><td class="num muted">-</td><td class="num neg" x-text="'-' + rupiah(data.arus_kas?.operasi.pembayaran_pemasok || 0)"></td><td></td></tr>
                                    <tr><td style="padding-left:24px">Beban Operasional Kedai (Listrik/Sewa/Gaji)</td><td class="num muted">-</td><td class="num neg" x-text="'-' + rupiah(data.arus_kas?.operasi.beban_operasional || 0)"></td><td></td></tr>
                                    <tr><td style="padding-left:24px">Pengeluaran Kas Keluar Shift (Petty Cash)</td><td class="num muted">-</td><td class="num neg" x-text="'-' + rupiah(data.arus_kas?.operasi.kas_keluar_shift || 0)"></td><td></td></tr>
                                    <tr style="border-top:1px dashed rgba(255,255,255,.15)"><td class="strong" style="padding-left:24px">Kas Bersih Aktivitas Operasi</td><td></td><td></td><td class="num strong" :class="data.arus_kas?.operasi.kas_bersih >= 0 ? 'pos' : 'neg'" x-text="rupiah(data.arus_kas?.operasi.kas_bersih || 0)"></td></tr>

                                    <tr style="background:rgba(255,255,255,.03)"><td class="strong" colspan="4">2. AKTIVITAS INVESTASI</td></tr>
                                    <tr><td style="padding-left:24px">Pembelian Peralatan Mesin &amp; Aset Modal</td><td class="num muted">-</td><td class="num neg" x-text="'-' + rupiah(data.arus_kas?.investasi.belanja_peralatan || 0)"></td><td></td></tr>
                                    <tr style="border-top:1px dashed rgba(255,255,255,.15)"><td class="strong" style="padding-left:24px">Kas Bersih Aktivitas Investasi</td><td></td><td></td><td class="num strong" :class="data.arus_kas?.investasi.kas_bersih >= 0 ? 'pos' : 'neg'" x-text="rupiah(data.arus_kas?.investasi.kas_bersih || 0)"></td></tr>

                                    <tr style="background:rgba(255,255,255,.03)"><td class="strong" colspan="4">3. AKTIVITAS PENDANAAN</td></tr>
                                    <tr><td style="padding-left:24px">Penjualan Non-Tunai (Rekening Bank &amp; Settlement QRIS)</td><td class="num pos" x-text="rupiah(data.arus_kas?.pendanaan.penjualan_digital_qris || 0)"></td><td class="num muted">-</td><td></td></tr>
                                    <tr><td style="padding-left:24px">Setoran Modal Awal Shift Kasir</td><td class="num pos" x-text="rupiah(data.arus_kas?.pendanaan.setoran_modal_shift || 0)"></td><td class="num muted">-</td><td></td></tr>
                                    <tr style="border-top:1px dashed rgba(255,255,255,.15)"><td class="strong" style="padding-left:24px">Kas Bersih Aktivitas Pendanaan</td><td></td><td></td><td class="num strong pos" x-text="rupiah(data.arus_kas?.pendanaan.kas_bersih || 0)"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 3: NERACA (BALANCE SHEET) ================= -->
                <div x-show="subtab === 'neraca'" x-cloak>
                    <div class="card" style="margin-bottom:18px">
                        <div class="card-head head-pad">
                            <div><h3>Neraca Posisi Keuangan (Balance Sheet)</h3><small>Persamaan Dasar Akuntansi: <strong>Aset = Kewajiban + Ekuitas</strong></small></div>
                            <span class="badge b-laba" x-text="data.neraca?.is_balance ? 'NERACA SEIMBANG (BALANCE)' : 'TIDAK BALANCE'"></span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:16px;padding:16px">
                            <!-- SISI ASET -->
                            <div class="card" style="background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06)">
                                <div class="card-head head-pad"><h4 style="color:#38bdf8">ASET (AKTIVA)</h4></div>
                                <div class="table-wrap">
                                    <table class="data">
                                        <tbody>
                                            <tr style="background:rgba(255,255,255,.02)"><td class="strong" colspan="2">Aset Lancar</td></tr>
                                            <tr><td style="padding-left:18px">Kas di Tangan (Kasir / Fisik)</td><td class="num" x-text="rupiah(data.neraca?.aset.lancar.kas_di_tangan || 0)"></td></tr>
                                            <tr><td style="padding-left:18px">Kas Bank &amp; Settlement QRIS</td><td class="num" x-text="rupiah(data.neraca?.aset.lancar.kas_bank_qris || 0)"></td></tr>
                                            <tr><td style="padding-left:18px">Persediaan Bahan Baku Kedai</td><td class="num" x-text="rupiah(data.neraca?.aset.lancar.persediaan_bahan_baku || 0)"></td></tr>
                                            <tr><td style="padding-left:18px">Persediaan Barang Dagang &amp; Menu</td><td class="num" x-text="rupiah(data.neraca?.aset.lancar.persediaan_produk || 0)"></td></tr>
                                            <tr style="border-top:1px dashed rgba(255,255,255,.1)"><td class="strong" style="padding-left:18px">Total Aset Lancar</td><td class="num strong" x-text="rupiah(data.neraca?.aset.lancar.total_aset_lancar || 0)"></td></tr>

                                            <tr style="background:rgba(255,255,255,.02)"><td class="strong" colspan="2">Aset Tetap (Peralatan)</td></tr>
                                            <tr><td style="padding-left:18px">Mesin Espresso &amp; Peralatan Usaha</td><td class="num" x-text="rupiah(data.neraca?.aset.tetap.peralatan_inventaris || 0)"></td></tr>
                                            <tr style="border-top:1px dashed rgba(255,255,255,.1)"><td class="strong" style="padding-left:18px">Total Aset Tetap</td><td class="num strong" x-text="rupiah(data.neraca?.aset.tetap.total_aset_tetap || 0)"></td></tr>

                                            <tr style="background:rgba(56,189,248,.1);border-top:2px solid #38bdf8"><td class="strong" style="font-size:14px">TOTAL ASET</td><td class="num strong" style="font-size:14px;color:#38bdf8" x-text="rupiah(data.neraca?.aset.total_aset || 0)"></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- SISI KEWAJIBAN & EKUITAS -->
                            <div class="card" style="background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06)">
                                <div class="card-head head-pad"><h4 style="color:#fbbf24">KEWAJIBAN &amp; EKUITAS (PASIVA)</h4></div>
                                <div class="table-wrap">
                                    <table class="data">
                                        <tbody>
                                            <tr style="background:rgba(255,255,255,.02)"><td class="strong" colspan="2">Kewajiban Lancar (Liabilitas)</td></tr>
                                            <tr><td style="padding-left:18px">Titipan Pajak Resto (PB1) Belum Disetor</td><td class="num neg" x-text="rupiah(data.neraca?.kewajiban.hutang_pajak_pb1 || 0)"></td></tr>
                                            <tr><td style="padding-left:18px">Titipan Service Charge Staf</td><td class="num neg" x-text="rupiah(data.neraca?.kewajiban.titipan_service_charge || 0)"></td></tr>
                                            <tr style="border-top:1px dashed rgba(255,255,255,.1)"><td class="strong" style="padding-left:18px">Total Kewajiban</td><td class="num strong neg" x-text="rupiah(data.neraca?.kewajiban.total_kewajiban || 0)"></td></tr>

                                            <tr style="background:rgba(255,255,255,.02)"><td class="strong" colspan="2">Ekuitas (Modal)</td></tr>
                                            <tr><td style="padding-left:18px">Modal Usaha &amp; Laba Ditahan Kumulatif</td><td class="num pos" x-text="rupiah(data.neraca?.ekuitas.modal_dan_laba_ditahan || 0)"></td></tr>
                                            <tr style="border-top:1px dashed rgba(255,255,255,.1)"><td class="strong" style="padding-left:18px">Total Ekuitas</td><td class="num strong pos" x-text="rupiah(data.neraca?.ekuitas.total_ekuitas || 0)"></td></tr>

                                            <tr style="background:rgba(251,191,36,.1);border-top:2px solid #fbbf24"><td class="strong" style="font-size:14px">TOTAL KEWAJIBAN &amp; EKUITAS</td><td class="num strong" style="font-size:14px;color:#fbbf24" x-text="rupiah(data.neraca?.total_kewajiban_dan_ekuitas || 0)"></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 4: VALUASI PERSEDIAAN ================= -->
                <div x-show="subtab === 'stok'" x-cloak>
                    <div class="kpi-grid" style="margin-bottom:18px">
                        <div class="card kpi"><div class="top"><span class="lbl">Total Nilai Persediaan</span><span class="chip-ico ok" x-html="icon('package')"></span></div>
                            <div class="val pos" x-text="rupiah(data.valuasi_stok?.ringkasan.total_nilai_persediaan || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Aset Bahan Baku</span><span class="chip-ico accent" x-html="icon('package')"></span></div>
                            <div class="val" x-text="rupiah(data.valuasi_stok?.ringkasan.nilai_bahan_baku || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Aset Barang Jadi</span><span class="chip-ico info" x-html="icon('package')"></span></div>
                            <div class="val" x-text="rupiah(data.valuasi_stok?.ringkasan.nilai_produk_jadi || 0)"></div></div>
                        <div class="card kpi"><div class="top"><span class="lbl">Total SKU Terdaftar</span><span class="chip-ico" x-html="icon('package')"></span></div>
                            <div class="val" x-text="((data.valuasi_stok?.ringkasan.total_sku_bahan || 0) + (data.valuasi_stok?.ringkasan.total_sku_produk || 0)) + ' SKU'"></div></div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:16px">
                        <div class="card">
                            <div class="card-head head-pad"><div><h3>Top 10 Valuasi Bahan Baku</h3><small>Bahan baku dengan nilai modal persediaan terbesar di gudang.</small></div></div>
                            <div class="table-wrap">
                                <table class="data">
                                    <thead><tr><th>Nama Bahan</th><th class="num">Stok Fisik</th><th class="num">Harga Satuan</th><th class="num">Total Nilai</th></tr></thead>
                                    <tbody>
                                        <template x-for="b in data.valuasi_stok?.top_bahan || []" :key="b.id">
                                            <tr>
                                                <td class="strong" x-text="b.nama"></td>
                                                <td class="num" x-text="angka(b.stok) + ' ' + b.satuan"></td>
                                                <td class="num muted" x-text="rupiah(b.harga)"></td>
                                                <td class="num strong pos" x-text="rupiah(b.total)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-head head-pad"><div><h3>Top 10 Valuasi Menu / Produk</h3><small>Barang dagang dengan nilai modal persediaan terbesar.</small></div></div>
                            <div class="table-wrap">
                                <table class="data">
                                    <thead><tr><th>Nama Menu</th><th class="num">Stok Fisik</th><th class="num">Harga Beli</th><th class="num">Total Nilai</th></tr></thead>
                                    <tbody>
                                        <template x-for="p in data.valuasi_stok?.top_produk || []" :key="p.id">
                                            <tr>
                                                <td class="strong" x-text="p.nama"></td>
                                                <td class="num" x-text="angka(p.stok) + ' ' + p.satuan"></td>
                                                <td class="num muted" x-text="rupiah(p.harga)"></td>
                                                <td class="num strong pos" x-text="rupiah(p.total)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </template>
        </div>
        </template>