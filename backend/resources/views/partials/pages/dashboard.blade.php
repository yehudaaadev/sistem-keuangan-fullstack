        <!-- ============ DASHBOARD ============ -->
        <template x-if="cfg().type === 'dashboard'">
        <div x-data="dashboard()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error"></div>
            <div class="loading" x-show="loading">Menyeduh data…</div>
            <template x-if="d">
            <div>
                <!-- Notifikasi stok otomatis -->
                <div class="banner bad" x-show="d.stok_menipis.habis > 0" role="alert">
                    <span x-html="icon('alert')"></span>
                    <div class="grow"><b x-text="`${d.stok_menipis.habis} menu stok habis`"></b> — menu ini tidak bisa dijual sampai direstok.</div>
                    <button class="btn btn-sm" @click="lihatStok('habis')">Lihat menu</button>
                </div>
                <div class="banner warn" x-show="menipisSaja() > 0" role="status">
                    <span x-html="icon('alert')"></span>
                    <div class="grow"><b x-text="`${menipisSaja()} menu stok menipis`"></b> (≤ <span x-text="d.stok_menipis.batas"></span>). Segera restok sebelum habis.</div>
                    <button class="btn btn-sm" @click="lihatStok('menipis')">Lihat menu</button>
                </div>

                <section class="hero2">
                    <div class="paper-wrap">
                        <article class="paper struk-hari">
                            <div class="sk-head"><b>Struk hari ini</b><span x-text="tglPendek()"></span></div>
                            <div class="sk-label">Penjualan hari ini</div>
                            <div class="sk-big" x-text="rupiah(d.hari_ini.total)"></div>
                            <div class="sk-delta" :class="delta().cls" x-text="delta().txt"></div>
                            <hr class="sk-dash">
                            <dl class="sk-meta">
                                <div><dt>Transaksi</dt><i></i><dd x-text="d.hari_ini.transaksi"></dd></div>
                                <div><dt>Rata-rata / nota</dt><i></i><dd x-text="rupiah(d.hari_ini.rata_rata)"></dd></div>
                                <div><dt>Item terjual</dt><i></i><dd x-text="angka(d.hari_ini.item_terjual)"></dd></div>
                                <div><dt>Nota terbesar</dt><i></i><dd x-text="rupiah(d.hari_ini.terbesar)"></dd></div>
                                <div><dt>Di tempat / bawa pulang</dt><i></i><dd x-text="`${d.hari_ini.dine_in} / ${d.hari_ini.bawa_pulang}`"></dd></div>
                            </dl>
                            <hr class="sk-dash">
                            <div class="sk-foot"><div class="sk-barcode"></div><p x-text="d.hari_ini.transaksi ? 'Terima kasih, terus semangat' : 'Belum ada nota · buka kasir untuk mulai'"></p></div>
                        </article>
                    </div>

                    <article class="card ritme">
                        <div class="card-head"><div><h3>Ritme hari ini</h3><small x-text="d.hari_ini.transaksi ? `Jam paling ramai ${jamRamai()}` : 'Transaksi per jam sepanjang hari'"></small></div>
                            <a href="#penjualan" class="btn btn-sm btn-primary"><span x-html="icon('cart')"></span>Buka kasir</a></div>
                        <div class="card-body ritme-body">
                            <div class="jam" role="img" aria-label="Transaksi per jam hari ini">
                                <template x-for="j in jamBars()" :key="j.jam"><div class="jb" :class="[j.now && 'now', j.transaksi === 0 && 'nol']" :title="`${j.jam}.00 · ${j.transaksi} transaksi`"><i :style="`height:${j.h}%`"></i><span x-text="(j.jam - jamBars()[0].jam) % 3 === 0 ? j.jam : ''"></span></div></template>
                            </div>
                            <div class="ritme-note" x-show="!d.hari_ini.transaksi"><span x-html="icon('clock')"></span><div><b>Menunggu nota pertama</b><small>Batang akan terisi tiap ada transaksi. Jam sekarang ditandai oranye.</small></div></div>
                            <div x-show="d.hari_ini.transaksi > 0">
                                <div class="mbar" role="img" aria-label="Komposisi metode bayar"><template x-for="m in metodeBar()" :key="m.metode"><i :style="`width:${m.pct}%`" :class="'m-' + m.metode" :title="`${metodeNama(m.metode)} ${m.pct}%`"></i></template></div>
                                <div class="mleg"><template x-for="m in metodeBar()" :key="m.metode"><span><i :class="'m-' + m.metode"></i><span x-text="`${metodeNama(m.metode)} · ${m.jumlah}x · ${rupiah(m.total)}`"></span></span></template></div>
                            </div>
                        </div>
                    </article>
                </section>

                <div class="kpi-grid">
                    <div class="card kpi tiket" x-show="$store.auth.role === 'owner'">
                        <div class="kpi-main"><div class="top"><span class="lbl">Penjualan bulan ini</span><span class="chip-ico info" x-html="icon('trendUp')"></span></div>
                            <div class="val" x-text="rupiah(d.bulan_ini?.penjualan)"></div>
                            <div class="sub" x-text="`Belanja stok ${rupiah(d.bulan_ini?.pembelian)}`"></div></div>
                        <div class="kpi-foot"><div class="mini"><template x-for="b in mini()" :key="b.tgl"><i :style="`height:${b.h}%`" :title="`${tanggal(b.tgl)}: ${rupiah(b.t)}`"></i></template></div><small>14 hari terakhir</small></div>
                    </div>
                    <div class="card kpi tiket" x-show="$store.auth.role === 'owner'" :class="(d.bulan_ini?.hasil ?? 0) >= 0 ? 'result-laba' : 'result-rugi'">
                        <div class="kpi-main"><div class="top"><span class="lbl" x-text="(d.bulan_ini?.hasil ?? 0) >= 0 ? 'Laba bulan ini' : 'Rugi bulan ini'"></span><span class="chip-ico" :class="(d.bulan_ini?.hasil ?? 0) >= 0 ? 'ok' : 'bad'" x-html="icon('wallet')"></span></div>
                            <div class="val" x-text="rupiah(Math.abs(d.bulan_ini?.hasil ?? 0))"></div><div class="sub">Penjualan dikurangi pembelian</div></div>
                        <div class="kpi-foot"><div class="meter" :class="(d.bulan_ini?.hasil ?? 0) < 0 && 'habis'"><i :style="`width:${Math.min(100, Math.max(3, Math.abs(margin())))}%`"></i></div><small x-text="`Margin ${margin()}% dari penjualan`"></small></div>
                    </div>
                    <div class="card kpi tiket">
                        <div class="kpi-main"><div class="top"><span class="lbl">Stok perlu perhatian</span><span class="chip-ico" :class="d.stok_menipis.jumlah ? 'gold' : 'ok'" x-html="icon(d.stok_menipis.jumlah ? 'alert' : 'check')"></span></div>
                            <div class="val" x-text="d.stok_menipis.jumlah"></div><div class="sub" x-text="d.stok_menipis.jumlah ? `${d.stok_menipis.habis} habis · ${menipisSaja()} menipis` : 'Semua menu di atas batas aman'"></div></div>
                        <div class="kpi-foot"><div class="meter" :class="d.stok_menipis.jumlah ? 'menipis' : ''"><i :style="`width:${aman()}%`"></i></div><small x-text="`${d.stok_menipis.total_menu} menu · ${d.stok_menipis.total_bahan} bahan dipantau (batas ≤ ${d.stok_menipis.batas})`"></small></div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-head">
                            <div><h3 x-text="`Tren penjualan ${hari} hari`"></h3><small x-text="`Total ${rupiah(totalGrafik())} · rata-rata ${rupiah(rataGrafik())} per hari`"></small></div>
                            <div class="seg" role="group" aria-label="Periode grafik">
                                <button type="button" :class="hari === 7 && 'on'" @click="ganti(7)" :aria-pressed="hari === 7">7 hari</button>
                                <button type="button" :class="hari === 30 && 'on'" @click="ganti(30)" :aria-pressed="hari === 30">30 hari</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart">
                                <svg viewBox="0 0 700 230" role="img" :aria-label="`Grafik penjualan ${hari} hari terakhir`">
                                    <defs><linearGradient id="ga" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f0b673" stop-opacity=".4"/><stop offset="1" stop-color="#f0b673" stop-opacity="0"/></linearGradient></defs>
                                    <g class="grid"><line x1="0" x2="700" y1="35" y2="35"/><line x1="0" x2="700" y1="90" y2="90"/><line x1="0" x2="700" y1="145" y2="145"/><line x1="0" x2="700" y1="200" y2="200"/></g>
                                    <path :d="area()" fill="url(#ga)"/>
                                    <path class="line" :d="garis()"/>
                                    <g x-html="dots()"></g>
                                </svg>
                                <div class="xl2"><template x-for="t in sumbu()" :key="t.kiri"><span :class="t.hariIni && 'today'" :style="`left:${t.kiri}%`" x-text="t.teks"></span></template></div>
                            </div>
                        </div>
                    </div>
                    <div class="card restok">
                        <div class="card-head head-pad"><div><h3 x-text="d.stok_menipis.jumlah ? 'Perlu restok' : 'Stok terendah'"></h3><small x-text="d.stok_menipis.jumlah ? 'Segera belanja sebelum habis' : 'Semua masih aman, ini yang paling tipis'"></small></div>
                            <button @click="sessionStorage.setItem('autoRestock', '1'); location.hash = '#pembelian'" class="btn btn-sm">Belanja Otomatis</button></div>
                        <div class="list">
                            <template x-for="p in restok()" :key="p.jenis + p.id">
                                <div class="list-item"><div class="dot-ico" :class="stokCls(p.stok, p.stok_minimum) === 'aman' ? 'ok' : 'bad'" x-html="icon(p.jenis === 'Menu' ? 'package' : 'flask')"></div>
                                    <div class="grow"><div class="t"><span x-text="p.nama"></span> <span class="tag" :class="p.jenis === 'Menu' ? 'menu' : 'bahan'" x-text="p.jenis"></span></div>
                                        <div class="meter" :class="stokCls(p.stok, p.stok_minimum)"><i :style="`width:${pctStok(p)}%`"></i></div></div>
                                    <span class="badge" :class="'b-' + stokCls(p.stok, p.stok_minimum)" x-text="`${angka(p.stok)} ${p.satuan}`"></span></div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="grid-eq">
                    <div class="card">
                        <div class="card-head head-pad"><h3>Transaksi terakhir</h3><small>Penjualan</small></div>
                        <div class="list">
                            <template x-for="t in d.transaksi_terakhir" :key="t.id">
                                <div class="list-item"><div class="dot-ico accent" x-html="icon('receipt')"></div>
                                    <div class="grow"><div class="t" x-text="t.no_nota"></div><div class="s" x-text="`${t.pembeli?.nama ?? 'Umum'} · ${waktu(t.tanggal)}`"></div></div>
                                    <span class="strong" x-text="rupiah(t.total)"></span></div>
                            </template>
                            <div class="empty sm" x-show="!d.transaksi_terakhir.length">Belum ada transaksi.</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-head head-pad"><h3>Pergerakan stok</h3><small>Terbaru</small></div>
                        <div class="list">
                            <template x-for="r in d.aktivitas_stok || []" :key="r.id">
                                <div class="list-item"><div class="dot-ico" :class="tipeCls(r.tipe)" x-html="icon(tipeIcon(r.tipe))"></div>
                                    <div class="grow"><div class="t" x-text="r.produk?.nama ?? r.bahan_baku?.nama"></div><div class="s" x-text="`${label(r.tipe)} · ${waktu(r.created_at)}`"></div></div>
                                    <span class="strong" :class="r.qty < 0 ? 'neg' : 'pos'" x-text="`${r.qty > 0 ? '+' : ''}${angka(r.qty)}`"></span></div>
                            </template>
                            <div class="empty sm" x-show="!(d.aktivitas_stok || []).length">Belum ada pergerakan.</div>
                        </div>
                    </div>
                </div>
            </div>
            </template>
        </div>
        </template>
