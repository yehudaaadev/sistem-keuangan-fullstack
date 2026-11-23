<!-- ================= LOGIN: struk buka shift ================= -->
<template x-if="ready && !$store.auth.user">
<main class="masuk" x-data="{
    now: new Date(), timer: null, lihat: false,
    init() { this.timer = setInterval(() => { this.now = new Date(); }, 1000); },
    destroy() { clearInterval(this.timer); },
    pad(n) { return String(n).padStart(2, '0'); },
    jam() { return this.pad(this.now.getHours()) + ':' + this.pad(this.now.getMinutes()); },
    detik() { return this.pad(this.now.getSeconds()); },
    tgl() { return this.pad(this.now.getDate()) + '/' + this.pad(this.now.getMonth() + 1) + '/' + this.now.getFullYear(); },
    hari() { return this.now.toLocaleDateString('id-ID', { weekday: 'long' }); },
    bulan() { return this.now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }); },
    shift() { const h = this.now.getHours(); return h < 11 ? 'Pagi' : h < 15 ? 'Siang' : h < 18 ? 'Sore' : 'Malam'; },
    no() { const n = this.now; return 'SH-' + String(n.getFullYear()).slice(2) + this.pad(n.getMonth() + 1) + this.pad(n.getDate()); }
}">
    <div class="poster" aria-hidden="true">KEDAI KOPI</div>

    <div class="struk-wrap">
        <form class="struk" @submit.prevent="mode === 'masuk' ? login() : (mode === 'daftar' ? daftar() : (mode === 'lupa' ? mintaOtp() : prosesReset()))" aria-labelledby="lg-judul">
            <header class="s-head">
                <h1 id="lg-judul">Kedai Kopi</h1>
                <p x-text="mode === 'masuk' ? 'Struk buka shift' : (mode === 'daftar' ? 'Struk pendaftaran' : (mode === 'lupa' ? 'Lupa kata sandi' : 'Verifikasi OTP sandi baru'))"></p>
            </header>

            <div class="roast" role="img" aria-label="Skala sangrai kopi dari terang sampai gelap">
                <svg style="color:#c9a26b;--crease:#6b4526;transform:rotate(-14deg)"><use href="#bean-s"/></svg>
                <svg style="color:#9a6b3a;--crease:#f2ead8;transform:rotate(9deg)"><use href="#bean-s"/></svg>
                <svg style="color:#6b4526;--crease:#f2ead8;transform:rotate(-6deg)"><use href="#bean-s"/></svg>
                <svg style="color:#3d2716;--crease:#f2ead8;transform:rotate(12deg)"><use href="#bean-s"/></svg>
                <svg style="color:#21150d;--crease:#f2ead8;transform:rotate(-10deg)"><use href="#bean-s"/></svg>
            </div>
            <div class="roast-l" aria-hidden="true"><span>Terang</span><span>Sedang</span><span>Gelap</span></div>

            <hr class="dash">
            <dl class="meta">
                <div><dt>Tanggal</dt><i></i><dd x-text="tgl()"></dd></div>
                <div><dt>Jam</dt><i></i><dd x-text="jam() + ':' + detik()"></dd></div>
                <div><dt>No. shift</dt><i></i><dd x-text="no()"></dd></div>
            </dl>
            <hr class="dash">

            <div class="alert-slot" role="alert" aria-live="assertive">
                <p class="hint pos" style="color:#059669;font-weight:700;margin:0 0 6px;font-size:13px;line-height:1.4" x-show="infoMsg" x-text="infoMsg"></p>
                <p class="err-msg" x-show="loginError" x-text="loginError"></p>
                <div class="cap" x-show="loginError" x-cloak aria-hidden="true">Ditolak</div>
            </div>

            <!-- Form Mode: Daftar -->
            <template x-if="mode === 'daftar'">
                <div>
                    <div class="f"><label for="lg-nama">Nama</label><div class="row"><input id="lg-nama" type="text" x-model="nama" required maxlength="100" autocomplete="name" placeholder="Nama lengkap"></div></div>
                    <div class="f"><label for="lg-telp">Telepon (opsional)</label><div class="row"><input id="lg-telp" type="tel" x-model="telepon" maxlength="30" autocomplete="tel" placeholder="08xxxxxxxxxx"></div></div>
                </div>
            </template>

            <!-- Form Mode: Masuk, Daftar, Lupa -->
            <template x-if="mode === 'masuk' || mode === 'daftar' || mode === 'lupa'">
                <div class="f">
                    <label for="lg-email" x-text="mode === 'lupa' ? 'Email / Nomor WhatsApp' : 'Email'"></label>
                    <div class="row"><input id="lg-email" type="text" x-model="email" required autocomplete="username" :placeholder="mode === 'lupa' ? 'Email atau nomor WA terdaftar' : 'nama@kedai.com'"></div>
                </div>
            </template>

            <!-- Form Mode: Masuk Sandi -->
            <template x-if="mode === 'masuk' || mode === 'daftar'">
                <div class="f">
                    <div style="display:flex;justify-content:space-between;align-items:baseline">
                        <label for="lg-pw">Sandi</label>
                    </div>
                    <div class="row">
                        <input id="lg-pw" :type="lihat ? 'text' : 'password'" x-model="password" required :minlength="mode === 'daftar' ? 8 : null" :autocomplete="mode === 'masuk' ? 'current-password' : 'new-password'" :placeholder="mode === 'masuk' ? '••••••••' : 'Minimal 8 karakter'">
                        <button type="button" class="eye" @click="lihat = !lihat" :aria-pressed="lihat" x-text="lihat ? 'Sembunyikan' : 'Lihat'"></button>
                    </div>
                </div>
            </template>

            <!-- Form Mode: Reset OTP & Sandi Baru -->
            <template x-if="mode === 'reset'">
                <div>
                    <div class="f">
                        <label for="lg-otp">Kode OTP (6 Digit)</label>
                        <div class="row"><input id="lg-otp" type="text" inputmode="numeric" maxlength="6" x-model="otp" required placeholder="123456" style="letter-spacing:0.25em;font-weight:bold"></div>
                    </div>
                    <div class="f">
                        <label for="lg-sandi-baru">Kata Sandi Baru</label>
                        <div class="row">
                            <input id="lg-sandi-baru" :type="lihat ? 'text' : 'password'" x-model="sandiBaru" required minlength="8" placeholder="Minimal 8 karakter">
                            <button type="button" class="eye" @click="lihat = !lihat" :aria-pressed="lihat" x-text="lihat ? 'Sembunyikan' : 'Lihat'"></button>
                        </div>
                    </div>
                </div>
            </template>

            <button class="go" :disabled="busy" :aria-busy="busy" x-text="busy ? 'Memproses…' : (mode === 'masuk' ? 'Buka shift' : (mode === 'daftar' ? 'Daftar sekarang' : (mode === 'lupa' ? 'Kirim OTP WA' : 'Simpan Sandi Baru')))"></button>

            <!-- Navigasi Mode -->
            <template x-if="mode === 'masuk'">
                <div>
                    <button type="button" class="tukar" @click="mode = 'daftar'; loginError = ''; infoMsg = ''">Pelanggan baru? Daftar di sini</button>
                    <button type="button" class="tukar" style="margin-top:2px;font-size:12px;opacity:0.85" @click="mode = 'lupa'; loginError = ''; infoMsg = ''">Lupa kata sandi? Reset via WhatsApp</button>
                </div>
            </template>
            <template x-if="mode === 'daftar'">
                <button type="button" class="tukar" @click="mode = 'masuk'; loginError = ''; infoMsg = ''">Sudah punya akun? Masuk</button>
            </template>
            <template x-if="mode === 'lupa' || mode === 'reset'">
                <button type="button" class="tukar" @click="mode = 'masuk'; loginError = ''; infoMsg = ''">← Batal, kembali ke halaman masuk</button>
            </template>

            <footer class="s-foot">
                <div class="barcode" aria-hidden="true"></div>
                <p>Terima kasih · Selamat bekerja</p>
            </footer>
        </form>
    </div>

    <div class="info" aria-hidden="true">
        <div class="shift">Shift <span x-text="shift()"></span></div>
        <div class="clock"><span x-text="jam()"></span><i x-text="detik()"></i></div>
        <div class="date"><span x-text="hari()"></span> · <span x-text="bulan()"></span></div>
        <p>Catat tiap seduhan, tiap stok, tiap rupiah.</p>
    </div>
</main>
</template>