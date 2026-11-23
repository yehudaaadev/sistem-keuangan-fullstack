        <!-- ============ ADMIN: WHATSAPP BLAST ============ -->
        <template x-if="cfg().type === 'wablast'">
        <div x-data="wablast()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>

            <div class="alert alert-info" x-show="!fonnteKonfigurasi" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <div>
                    <b>Fonnte API Key Belum Dikonfigurasi</b>
                    <div>Untuk mengirim pesan secara otomatis ke WhatsApp pelanggan, masukkan API Key Fonnte di menu <b>Pengaturan Toko</b> atau <code>.env</code>.</div>
                </div>
                <a href="#pengaturan" class="btn btn-sm">Buka Pengaturan</a>
            </div>

            <div class="grid-2" style="align-items:start;margin-bottom:20px">
                <!-- Form Buat Blast -->
                <form class="card" @submit.prevent="kirim()" style="padding:22px;display:grid;gap:16px">
                    <div class="card-head" style="padding:0;border:none">
                        <div>
                            <h3 style="margin:0 0 4px">Kirim WhatsApp Blast</h3>
                            <small class="muted">Kirim pesan promo atau pengumuman massal ke pelanggan</small>
                        </div>
                    </div>

                    <div class="field">
                        <label for="wb-judul">Judul Kampanye / Promo</label>
                        <input id="wb-judul" class="input" x-model="f.judul" required maxlength="150" placeholder="Mis. Promo Gajian Kopi 20%">
                    </div>

                    <div class="field">
                        <label>Target Penerima</label>
                        <div class="tipe" role="group" aria-label="Target Penerima">
                            <button type="button" :class="f.target_tipe === 'semua' && 'on'" @click="f.target_tipe = 'semua'">Semua Kontak</button>
                            <button type="button" :class="f.target_tipe === 'loyal' && 'on'" @click="f.target_tipe = 'loyal'">Member Loyal</button>
                            <button type="button" :class="f.target_tipe === 'manual' && 'on'" @click="f.target_tipe = 'manual'">Nomor Manual</button>
                        </div>
                        <div class="hint" style="margin-top:6px">
                            Estimasi target: <b x-text="targetEstimasi()"></b>
                        </div>
                    </div>

                    <div class="field" x-show="f.target_tipe === 'manual'">
                        <label for="wb-manual">Nomor WhatsApp Manual</label>
                        <textarea id="wb-manual" class="textarea" x-model="f.nomor_manual" placeholder="Contoh: 08123456789, 08987654321 (pisahkan dengan koma atau baris baru)" rows="2"></textarea>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600">Template Cepat</label>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <template x-for="t in templateList" :key="t.judul">
                                <button type="button" class="btn btn-sm" @click="pakaiTemplate(t)" x-text="t.judul"></button>
                            </template>
                        </div>
                    </div>

                    <div class="field">
                        <label for="wb-pesan">Isi Pesan WhatsApp</label>
                        <textarea id="wb-pesan" class="textarea" x-model="f.pesan" required rows="5" maxlength="2000" placeholder="Tuliskan pesan promo atau pengumuman di sini..."></textarea>
                        <small class="muted" style="text-align:right" x-text="`${f.pesan.length} / 2000 karakter`"></small>
                    </div>

                    <button class="btn btn-primary btn-lg" :disabled="sending" style="background:#25d366;border-color:#25d366;color:#fff">
                        <span x-html="icon('wa')"></span>
                        <span x-text="sending ? 'Mengirim Blast via Fonnte…' : 'Kirim WhatsApp Blast'"></span>
                    </button>
                </form>

                <!-- Ringkasan Info Kontak & Fonnte Gateway -->
                <div style="display:grid;gap:16px">
                    <div class="card" style="padding:22px">
                        <h3 style="margin:0 0 12px">Statistik Kontak Pelanggan</h3>
                        <div class="kv">
                            <span>Pelanggan dengan nomor WA</span>
                            <span class="strong" x-text="`${totalWa} kontak`"></span>
                        </div>
                        <div class="kv">
                            <span>Pelanggan punya Poin Member</span>
                            <span class="pos strong" x-text="`${totalLoyal} kontak`"></span>
                        </div>
                        <div class="kv">
                            <span>Gateway WhatsApp</span>
                            <span class="strong" x-text="fonnteKonfigurasi ? 'Fonnte (Aktif)' : 'Belum Terhubung'"></span>
                        </div>
                    </div>

                    <div class="card" style="padding:22px">
                        <h3 style="margin:0 0 8px">Tips Efektif WA Blast</h3>
                        <ul style="margin:0;padding-left:18px;line-height:1.6;font-size:13px;color:var(--text-muted)">
                            <li>Gunakan sapaan ramah dan nama kedai di awal pesan.</li>
                            <li>Sertakan masa berlaku promo agar pelanggan segera berkunjung.</li>
                            <li>Targetkan member loyal untuk apresiasi poin dan meningkatkan repeat order.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Riwayat Pengiriman WA Blast -->
            <div class="card">
                <div class="card-head head-pad">
                    <div>
                        <h3>Riwayat WhatsApp Blast</h3>
                        <small>Catatan pengiriman pesan siaran melalui Fonnte</small>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Waktu</th><th>Judul Kampanye</th><th>Target</th><th class="num">Jumlah Nomor</th><th>Status</th><th>Petugas</th></tr></thead>
                        <tbody>
                            <template x-for="r in rows" :key="r.id">
                                <tr>
                                    <td class="muted" x-text="waktu(r.created_at)"></td>
                                    <td class="strong" x-text="r.judul"></td>
                                    <td><span class="badge" x-text="label(r.target_tipe)"></span></td>
                                    <td class="num strong" x-text="`${angka(r.total_target)} nomor`"></td>
                                    <td><span class="badge" :class="r.status === 'sukses' ? 'b-aman' : 'b-habis'" x-text="label(r.status)"></span></td>
                                    <td class="muted" x-text="r.user?.name || '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat riwayat blast…</div>
                <div class="empty" x-show="!loading && !rows.length">
                    <div class="ico" x-html="icon('wa')"></div>Belum pernah mengirim WhatsApp blast.
                </div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>
        </div>
        </template>
