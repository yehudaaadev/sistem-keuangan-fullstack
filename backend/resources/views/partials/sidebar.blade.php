    <aside class="sidebar" :class="menuOpen && 'open'">
        <div class="brand">
            <div class="logo" x-html="icon('bean')"></div>
            <div><b>Kedai Kopi</b><small>Sistem Keuangan</small></div>
        </div>
        <nav class="nav">
            <template x-for="m in menu()" :key="m.key">
                <div style="display:contents">
                    <div class="nav-label" x-show="m.first" x-text="m.group"></div>
                    <a :href="'#' + m.key" :class="page === m.key && 'active'">
                        <span style="display:contents" x-html="icon(m.icon)"></span><span x-text="m.title"></span><span class="nav-badge" x-show="m.badge > 0" x-text="m.badge" aria-label="pesanan baru"></span>
                    </a>
                </div>
            </template>
        </nav>
        <div class="side-card">
            <svg class="bg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" x-html="ICONS.coffee"></svg>
            <p>Kopi yang enak dimulai dari stok yang tercatat rapi.</p>
            <small>Catat setiap transaksi, jangan ada yang terlewat.</small>
        </div>
        <div class="user-box">
            <div class="avatar" x-text="initial()"></div>
            <div class="who"><b x-text="$store.auth.user?.name"></b><small x-text="$store.auth.role"></small></div>
            <button class="icon-btn" @click="bukaSandi()" title="Ganti sandi" aria-label="Ganti sandi" x-html="icon('key')"></button>
            <button class="icon-btn" @click="logout()" title="Keluar" aria-label="Keluar" x-html="icon('logout')"></button>
        </div>
    </aside>