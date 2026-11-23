        <!-- ============ OWNER: PENGATURAN ============ -->
        <template x-if="cfg().type === 'pengaturan'">
        <div x-data="pengaturan()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="grid-2" style="align-items:start">
                <form class="card" @submit.prevent="simpan()" style="padding:22px;display:grid;gap:16px">
                    <div class="field"><label for="p-nama">Nama kedai (tercetak di nota)</label><input id="p-nama" class="input" x-model="f.nama_toko" required maxlength="100"></div>
                    <div class="field"><label for="p-alamat">Alamat (opsional)</label><input id="p-alamat" class="input" x-model="f.alamat" maxlength="200"></div>
                    <div class="field"><label for="p-footer">Pesan penutup nota</label><input id="p-footer" class="input" x-model="f.footer_nota" maxlength="200" placeholder="Terima kasih, sampai jumpa!"></div>
                    <div class="grid-eq">
                        <div class="field"><label for="p-pajak">Pajak (%)</label><input id="p-pajak" class="input" type="number" min="0" max="100" step="any" x-model="f.pajak_persen" required></div>
                        <div class="field"><label for="p-service">Service charge (%)</label><input id="p-service" class="input" type="number" min="0" max="100" step="any" x-model="f.service_persen" required></div>
                    </div>
                    <div class="field">
                        <label for="p-fonnte">Fonnte API Key (WhatsApp Gateway)</label>
                        <input id="p-fonnte" class="input" type="password" x-model="f.fonnte_token" placeholder="Masukkan token Fonnte (mis. d4k92m...)" autocomplete="off">
                        <p class="hint" style="margin:4px 0 0">Digunakan untuk WhatsApp Blast & kirim struk digital otomatis. Dapatkan token di <a href="https://fonnte.com" target="_blank" rel="noopener" style="color:var(--accent-2);text-decoration:underline">fonnte.com</a>.</p>
                    </div>

                    <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:12px">
                        <h4 style="margin:0 0 10px">Legalitas &amp; Pajak Korporat</h4>
                        <div class="grid-eq" style="margin-bottom:12px">
                            <div class="field"><label for="p-badan">Badan Usaha (PT/CV)</label><input id="p-badan" class="input" x-model="f.badan_usaha" placeholder="Mis. PT Kopi Nusantara Jaya" maxlength="100"></div>
                            <div class="field"><label for="p-npwp">NPWP Badan</label><input id="p-npwp" class="input" x-model="f.npwp" placeholder="00.000.000.0-000.000" maxlength="50"></div>
                        </div>
                        <div class="grid-eq">
                            <div class="field"><label for="p-nib">NIB (Nomor Induk Berusaha)</label><input id="p-nib" class="input" x-model="f.nib" placeholder="Mis. 1234567890123" maxlength="50"></div>
                            <div class="field"><label for="p-bank">Rekening Bank Resmi</label><input id="p-bank" class="input" x-model="f.bank_rekening" placeholder="BCA 123456789 a.n PT Kopi" maxlength="100"></div>
                        </div>
                    </div>

                    <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:12px">
                        <h4 style="margin:0 0 4px">Periode Tutup Buku (Fiscal Lock)</h4>
                        <small class="muted">Transaksi penjualan, belanja, beban, atau opname pada atau sebelum tanggal ini dikunci dan ditolak sistem untuk menjaga integritas pembukuan resmi.</small>
                        <div class="field" style="margin-top:10px;max-width:240px">
                            <label for="p-tutup">Kunci transaksi sampai tanggal</label>
                            <input id="p-tutup" class="input" type="date" x-model="f.tutup_buku_sampai">
                        </div>
                    </div>

                    <p class="hint" style="margin:0">Pajak dan service dikenakan pada penjualan baru. Nota lama tidak berubah. Pajak tidak dihitung sebagai omzet di laporan.</p>
                    <div><button class="btn btn-primary" :disabled="saving || loading" x-text="saving ? 'Menyimpan…' : 'Simpan pengaturan'"></button></div>
                </form>
                <div class="card" style="padding:22px">
                    <h3 style="margin:0 0 4px">Contoh perhitungan</h3><small class="muted">Belanja Rp 50.000</small>
                    <div class="kv"><span class="muted">Subtotal</span><span x-text="rupiah(contoh().sub)"></span></div>
                    <div class="kv"><span class="muted" x-text="`Service ${angka(f.service_persen)}%`"></span><span x-text="rupiah(contoh().sv)"></span></div>
                    <div class="kv"><span class="muted" x-text="`Pajak ${angka(f.pajak_persen)}%`"></span><span x-text="rupiah(contoh().pj)"></span></div>
                    <div class="kv strong"><span>Total dibayar</span><span x-text="rupiah(contoh().total)"></span></div>
                </div>
            </div>
        </div>
        </template>
