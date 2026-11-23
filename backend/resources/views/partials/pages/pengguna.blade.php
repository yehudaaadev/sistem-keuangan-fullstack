        <!-- ============ OWNER: PENGGUNA ============ -->
        <template x-if="cfg().type === 'pengguna'">
        <div x-data="pengguna()" x-init="init()">
            <div class="alert alert-error" x-show="error" x-text="error" role="alert"></div>
            <div class="toolbar">
                <div class="search"><span x-html="icon('search')"></span><input class="input" type="search" x-model="search" @input.debounce.400ms="load()" placeholder="Cari nama atau email…" aria-label="Cari pengguna"></div>
                <span class="grow"></span>
                <button class="btn btn-primary" @click="baru()"><span x-html="icon('plus')"></span>Akun Baru</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="u in rows" :key="u.id">
                                <tr :style="!u.aktif && 'opacity:.55'">
                                    <td class="strong" x-text="u.name"></td>
                                    <td class="muted" x-text="u.email"></td>
                                    <td><span class="badge" :class="u.role === 'owner' ? 'b-menipis' : 'b-selesai'" style="text-transform:capitalize" x-text="u.role"></span></td>
                                    <td><span class="badge" :class="u.aktif ? 'b-aman' : 'b-habis'" x-text="u.aktif ? 'Aktif' : 'Nonaktif'"></span></td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-link" @click="ubah(u)">Ubah</button>
                                        <button class="btn btn-sm" :class="u.aktif ? 'btn-danger' : 'btn-primary'" :disabled="u.id === saya()" @click="toggleAktif(u)" x-text="u.aktif ? 'Nonaktifkan' : 'Aktifkan'"></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat…</div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>

            <div x-show="modal" x-cloak class="backdrop" @keydown.escape.window="modal = false">
                <form @submit.prevent="simpan()" class="modal" role="dialog" aria-modal="true" aria-label="Akun staf">
                    <div class="modal-head"><h2 x-text="editId ? 'Ubah akun' : 'Akun baru'"></h2><button type="button" class="icon-btn" @click="modal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError" role="alert"></div>
                        <div class="field"><label for="u-nama">Nama</label><input id="u-nama" class="input" x-model="form.name" required maxlength="100"></div>
                        <div class="field"><label for="u-email">Email</label><input id="u-email" class="input" type="email" x-model="form.email" required autocomplete="off"></div>
                        <div class="field"><label for="u-telp">Telepon (opsional)</label><input id="u-telp" class="input" type="tel" x-model="form.telepon" maxlength="30"></div>
                        <div class="field"><label for="u-role">Peran</label>
                            <select id="u-role" class="select" x-model="form.role" required><template x-for="r in peran" :key="r[0]"><option :value="r[0]" x-text="r[1]"></option></template></select>
                            <p class="hint">Kasir: hanya halaman kasir dan pesanan online. Admin: menu, stok, belanja. Owner: akses penuh dan laporan.</p></div>
                        <div class="field"><label for="u-pw" x-text="editId ? 'Sandi baru (kosongkan bila tidak diganti)' : 'Sandi (min. 8 karakter)'"></label>
                            <input id="u-pw" class="input" type="password" x-model="form.password" :required="!editId" minlength="8" autocomplete="new-password"></div>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn" @click="modal = false">Batal</button><button class="btn btn-primary" :disabled="saving" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button></div>
                </form>
            </div>
        </div>
        </template>
