        <!-- ============ CRUD generik (tabel / kartu) ============ -->
        <template x-if="cfg().type === 'crud'">
        <div x-data="crud(cfg())" x-init="init()">
            <div class="alert alert-info" x-show="cfg.note" x-text="cfg.note"></div>
            <div class="toolbar">
                <div class="search" x-show="cfg.search">
                    <span x-html="icon('search')"></span>
                    <input class="input" type="search" x-model="search" @input.debounce.400ms="load()" placeholder="Cari…" aria-label="Cari">
                </div>
                <template x-for="f in cfg.filters || []" :key="f.key">
                    <select class="select" x-model="filter[f.key]" @change="load()" :aria-label="f.label">
                        <option value="" x-text="f.label"></option>
                        <template x-for="c in f.choices" :key="c.v"><option :value="c.v" x-text="c.l"></option></template>
                    </select>
                </template>
                <button x-show="cfg.nonaktif" type="button" class="cat" :class="nonaktifMode() && 'on'" :aria-pressed="nonaktifMode()" @click="toggleNonaktif()">Tampilkan nonaktif</button>
                <span class="grow"></span>
                <button x-show="canCreate() && !nonaktifMode()" @click="openNew()" class="btn btn-primary"><span x-html="icon('plus')"></span>Tambah</button>
            </div>
            <div class="cats" x-show="cfg.chips" style="margin-top:-6px">
                <button type="button" class="cat" :class="chipAktif('') && 'on'" @click="pilihChip('')" x-text="cfg.chips?.all"></button>
                <template x-for="c in chips" :key="c.id"><button type="button" class="cat" :class="chipAktif(c.id) && 'on'" @click="pilihChip(c.id)" x-text="c.nama"></button></template>
            </div>
            <div class="alert alert-error" x-show="error" x-text="error"></div>

            <!-- Mode kartu (menu) -->
            <template x-if="cfg.cards">
            <div>
                <div class="pgrid">
                    <template x-for="row in rows" :key="row.id">
                        <article class="card pcard" :class="nonaktifMode() && 'off'">
                            <div class="art thumb" :class="'tone-' + art(row).tone">
                                <span class="fb" x-html="icon(art(row).icon)"></span>
                                <template x-if="row.image_url"><img :src="row.image_url" :alt="row.nama" loading="lazy" x-on:error="$el.remove()"></template>
                                <span class="st badge" :class="'b-' + state(row)" x-text="label(state(row))"></span>
                            </div>
                            <div class="pcard-body">
                                <small x-text="`${row.sku ? '[' + row.sku + '] ' : ''}${row.kategori?.nama || ''}`"></small>
                                <h4><span x-text="row.nama"></span> <span class="tag bahan" x-show="ringkasResep(row)">Resep</span></h4>
                                <div class="price"><span x-text="rupiah(row.harga_jual)"></span> <small x-text="'/ ' + row.satuan"></small></div>
                                <div class="meta"><span>Modal</span><span x-text="rupiah(row.harga_beli)"></span></div>
                                <div class="meta" x-show="row.modal_resep != null"><span>Modal dari resep</span><span x-text="rupiah(row.modal_resep)"></span></div>
                                <div class="meta"><span>Margin</span><span class="strong" :class="margin(row) < 0 ? 'neg' : 'pos'" x-text="margin(row) + '%'"></span></div>
                                <div class="meta"><span>Stok</span><span class="strong" x-text="`${angka(row.stok)} ${row.satuan} (min: ${angka(row.stok_minimum || 5)})`"></span></div>
                                <div class="meter" :class="state(row)"><i :style="`width:${pct(row)}%`"></i></div>
                            </div>
                            <div class="pcard-foot" x-show="canWrite()">
                                <template x-if="!nonaktifMode()">
                                    <div style="display:flex;gap:6px;width:100%">
                                        <button @click="edit(row)" class="btn btn-sm">Ubah</button>
                                        <button @click="remove(row)" class="btn btn-sm btn-danger" x-text="cfg.softLabel || 'Hapus'"></button>
                                    </div>
                                </template>
                                <template x-if="nonaktifMode()"><button @click="aktifkan(row)" class="btn btn-sm btn-primary">Aktifkan kembali</button></template>
                            </div>
                        </article>
                    </template>
                </div>
                <div class="loading" x-show="loading">Memuat data…</div>
                <div class="card empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('inbox')"></div><div x-text="nonaktifMode() ? 'Tidak ada menu nonaktif.' : 'Tidak ada menu yang cocok.'"></div></div>
                <div class="pager free" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>
            </template>

            <!-- Mode tabel -->
            <template x-if="!cfg.cards">
            <div class="card">
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr>
                            <template x-for="c in cfg.columns" :key="c.key"><th :class="isNum(c) && 'num'" x-text="c.label"></th></template>
                            <th x-show="canWrite() && !cfg.immutable"></th>
                        </tr></thead>
                        <tbody>
                            <template x-for="row in rows" :key="row.id">
                                <tr>
                                    <template x-for="c in cfg.columns" :key="c.key">
                                        <td :class="isNum(c) && 'num'">
                                            <template x-if="c.fmt === 'label'"><span class="badge" :class="'b-' + row[c.key]" x-text="fmt(row, c)"></span></template>
                                            <template x-if="c.fmt !== 'label'"><span :class="cls(row, c)" x-text="(c.signed && row[c.key] > 0 ? '+' : '') + fmt(row, c)"></span></template>
                                        </td>
                                    </template>
                                    <td x-show="canWrite() && !cfg.immutable" class="actions">
                                        <button @click="edit(row)" class="btn btn-sm btn-link">Ubah</button>
                                        <button x-show="!cfg.noDelete" @click="remove(row)" class="btn btn-sm btn-danger">Hapus</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="loading" x-show="loading">Memuat data…</div>
                <div class="empty" x-show="!loading && !rows.length"><div class="ico" x-html="icon('inbox')"></div><div>Tidak ada data yang cocok.</div></div>
                <div class="pager" x-show="meta.last_page > 1">
                    <button class="btn btn-sm" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><span x-html="icon('chevL')"></span>Sebelumnya</button>
                    <span x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                    <button class="btn btn-sm" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Berikutnya<span x-html="icon('chevR')"></span></button>
                </div>
            </div>
            </template>

            <!-- Modal form -->
            <div x-show="modal" x-cloak class="backdrop" @keydown.escape.window="modal = false">
                <form @submit.prevent="save()" class="modal" role="dialog" aria-modal="true" :aria-label="editId ? 'Ubah data' : 'Tambah data'">
                    <div class="modal-head"><h2 x-text="editId ? 'Ubah data' : 'Tambah data'"></h2>
                        <button type="button" class="icon-btn" @click="modal = false" aria-label="Tutup" x-html="icon('x')"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-error" style="margin:0" x-show="formError" x-text="formError"></div>
                        <template x-for="f in bidang()" :key="f.key">
                            <div class="field">
                                <label :for="'f-' + f.key" x-text="f.label + (f.optional ? ' (opsional)' : '')"></label>
                                <template x-if="f.type === 'select'">
                                    <select class="select" :id="'f-' + f.key" x-model="form[f.key]" required>
                                        <option value="">Pilih…</option>
                                        <template x-for="c in choices(f)" :key="c.v"><option :value="c.v" x-text="c.l"></option></template>
                                    </select>
                                </template>
                                <template x-if="f.type === 'textarea'"><textarea class="textarea" :id="'f-' + f.key" x-model="form[f.key]"></textarea></template>
                                <template x-if="f.type === 'file'">
                                    <div class="filebox">
                                        <div class="art thumb" :class="'tone-default'">
                                            <span class="fb" x-html="icon('image')"></span>
                                            <img x-show="preview[f.key]" :src="preview[f.key]" alt="Pratinjau foto">
                                        </div>
                                        <div><input type="file" :id="'f-' + f.key" accept="image/png,image/jpeg,image/webp" @change="pilihFile(f, $event)"><p class="hint" x-text="f.hint"></p></div>
                                    </div>
                                </template>
                                <template x-if="f.type === 'resep'">
                                    <div class="resep-box">
                                        <template x-for="(r, i) in (form.resep || [])" :key="i">
                                            <div class="resep-row">
                                                <select class="select" x-model="r.bahan_baku_id" required aria-label="Bahan baku">
                                                    <option value="">Pilih bahan…</option>
                                                    <template x-for="b in bahanOpsi" :key="b.id"><option :value="b.id" :selected="Number(r.bahan_baku_id) === b.id" x-text="b.nama"></option></template>
                                                </select>
                                                <input class="input" type="number" min="0.01" step="any" x-model.number="r.jumlah" required placeholder="Jumlah" aria-label="Jumlah per porsi">
                                                <span class="muted satuan" x-text="satuanBahan(r.bahan_baku_id)"></span>
                                                <button type="button" class="icon-btn" @click="hapusResep(i)" aria-label="Hapus bahan" x-html="icon('x')"></button>
                                            </div>
                                        </template>
                                        <button type="button" class="btn btn-sm" @click="tambahResep()"><span x-html="icon('plus')"></span>Tambah bahan</button>
                                        <div class="resep-info" x-show="(form.resep || []).length">
                                            <span>Perkiraan modal per porsi dari harga belanja terakhir: <b x-text="rupiah(modalDariResep())"></b></span>
                                            <button type="button" class="btn btn-sm btn-link" @click="pakaiModalResep()">Pakai sebagai harga modal</button>
                                        </div>
                                        <p class="hint" x-show="(form.resep || []).length">Stok menu ini kini dihitung dari stok bahan, dan bahan berkurang otomatis tiap terjual.</p>
                                    </div>
                                </template>
                                <template x-if="f.type === 'opsi'">
                                    <div class="opsi-pick">
                                        <template x-for="g in opsiOpsi" :key="g.id">
                                            <label class="chk"><input type="checkbox" :checked="opsiAda(g.id)" @change="toggleOpsi(g.id)">
                                                <span><b x-text="g.nama"></b> <small class="muted" x-text="g.pilihan.map((x) => x.nama).join(', ')"></small></span></label>
                                        </template>
                                        <p class="hint" x-show="!opsiOpsi.length">Belum ada kelompok. Buat dulu di menu <b>Kustomisasi Menu</b>.</p>
                                    </div>
                                </template>
                                <template x-if="['text','number','date'].includes(f.type)">
                                    <div>
                                        <input class="input" :id="'f-' + f.key" :type="f.type" x-model="form[f.key]" :step="f.type === 'number' ? 'any' : null" :min="f.type === 'number' ? 0 : null" :required="!f.optional" :disabled="editId && $store.auth.role !== 'owner' && f.key === 'harga_jual'">
                                        <p class="hint" style="margin:4px 0 0;color:var(--text-muted)" x-show="editId && $store.auth.role !== 'owner' && f.key === 'harga_jual'">Hanya Owner yang berwenang mengubah harga jual produk.</p>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="alert" style="margin:0" :class="kurangUntung() ? 'alert-error' : 'alert-info'" x-show="peringatan()" x-text="peringatan()" role="status"></div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" @click="modal = false" class="btn">Batal</button>
                        <button :disabled="saving" class="btn btn-primary" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button>
                    </div>
                </form>
            </div>
        </div>
        </template>
