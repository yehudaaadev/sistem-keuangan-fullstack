        <header class="topbar">
            <button class="icon-btn menu-btn" @click="menuOpen = true" aria-label="Menu" x-html="icon('menu')"></button>
            <div>
                <p class="eyebrow" x-text="page && eyebrow()"></p>
                <h1 x-text="page && judul()"></h1>
                <p x-text="page && META[page].desc"></p>
            </div>
            <span class="spacer"></span>
            <span class="chip"><span x-html="icon('user')"></span><span style="text-transform:capitalize" x-text="$store.auth.role"></span></span>
        </header>