    <style>
    /* ===== Login: struk buka shift ===== */
    .masuk {
        --ink: #2a1f16; --paper: #f2ead8; --kraft: #c9a56f; --kraft-dim: #a58a63; --stamp: #b3402a;
        --display: "Big Shoulders Display", "Arial Narrow", Impact, sans-serif;
        --mono: "Courier Prime", "Courier New", ui-monospace, monospace;
        position: relative; z-index: 1; min-height: 100vh; min-height: 100dvh; color: var(--paper); font-family: var(--mono);
        display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, 400px) minmax(0, 1fr); align-items: center;
        gap: clamp(16px, 3vw, 48px); padding: clamp(20px, 4vh, 48px) clamp(20px, 4vw, 56px); overflow: hidden;
        /* kain goni: serat silang halus di atas dark roast */
        background:
            repeating-linear-gradient(0deg, rgba(255, 235, 200, .035) 0 1px, transparent 1px 4px),
            repeating-linear-gradient(90deg, rgba(255, 235, 200, .03) 0 1px, transparent 1px 5px),
            radial-gradient(1100px 700px at 34% 42%, #2b1c11, #1a110b 72%);
    }
    .masuk .poster {
        justify-self: start; align-self: stretch; display: flex; align-items: center;
        writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap;
        font: 800 min(20vh, 15vw)/.85 var(--display); letter-spacing: .03em; color: transparent;
        -webkit-text-stroke: 2px rgba(201, 165, 111, .5); user-select: none;
    }
    .struk-wrap { filter: drop-shadow(0 22px 26px rgba(0, 0, 0, .6)); }
    .struk {
        position: relative; color: var(--ink); padding: 44px 30px 46px; font-size: 15px; line-height: 1.45;
        background: linear-gradient(180deg, #f6efdf, var(--paper) 30%, #efe5cf);
        /* tepi atas & bawah bergerigi seperti kertas robek dari printer */
        -webkit-mask: conic-gradient(from -45deg at bottom, #0000, #000 1deg 89deg, #0000 90deg) bottom / 20px 51% repeat-x,
                      conic-gradient(from 135deg at top, #0000, #000 1deg 89deg, #0000 90deg) top / 20px 51% repeat-x;
                mask: conic-gradient(from -45deg at bottom, #0000, #000 1deg 89deg, #0000 90deg) bottom / 20px 51% repeat-x,
                      conic-gradient(from 135deg at top, #0000, #000 1deg 89deg, #0000 90deg) top / 20px 51% repeat-x;
        animation: cetak 1s cubic-bezier(.3, .7, .2, 1) both;
    }
    .struk::before { /* serat kertas */
        content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .18; mix-blend-mode: multiply;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }
    @keyframes cetak { from { clip-path: inset(0 0 100% 0); } to { clip-path: inset(0 0 0 0); } }
    .s-head { text-align: center; }
    .s-head h1 { font: 800 50px/1 var(--display); letter-spacing: .12em; text-transform: uppercase; margin: 0; }
    .s-head p { margin: 4px 0 0; font-size: 12px; font-weight: 700; letter-spacing: .28em; text-transform: uppercase; color: #5c4b3a; }
    .roast { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
    .roast svg { width: 30px; height: 38px; }
    .roast-l { display: flex; justify-content: space-between; margin: 6px 22px 0; font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: #5c4b3a; font-weight: 700; }
    .dash { border: 0; border-top: 2px dashed rgba(42, 31, 22, .55); margin: 18px 0; }
    .masuk .meta { margin: 0; display: flex; flex-direction: column; gap: 6px; }
    .masuk .meta > div { display: flex; align-items: baseline; font-size: 14px; }
    .masuk .meta dt { text-transform: uppercase; letter-spacing: .06em; }
    .masuk .meta i { flex: 1; border-bottom: 2px dotted rgba(42, 31, 22, .4); margin: 0 8px; transform: translateY(-3px); }
    .masuk .meta dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
    .alert-slot { position: relative; }
    .err-msg { margin: 0 0 6px; padding: 0 152px 0 0; min-height: 46px; display: flex; align-items: center; color: var(--stamp); font-weight: 700; font-size: 14px; }
    .f { margin-top: 16px; }
    .f label { display: block; font-size: 12px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #5c4b3a; }
    .f .row { margin-top: 6px; display: flex; align-items: flex-end; border-bottom: 2px solid var(--ink); }
    .f .row:focus-within { outline: 3px solid var(--stamp); outline-offset: 3px; }
    .f input { flex: 1; min-width: 0; background: transparent; border: 0; outline: 0; color: var(--ink); font: 400 18px/1.4 var(--mono); padding: 8px 0 6px; }
    .f input::placeholder { color: #7a6a58; opacity: 1; }
    .eye { background: none; border: 0; color: var(--ink); font: 700 12px var(--mono); text-decoration: underline; text-underline-offset: 3px; padding: 8px 4px; cursor: pointer; }
    .go {
        width: 100%; margin-top: 26px; padding: 14px 10px 12px; border: 0; cursor: pointer; background: var(--ink); color: var(--paper);
        font: 800 28px/1 var(--display); letter-spacing: .18em; text-transform: uppercase;
        box-shadow: inset 0 0 0 3px var(--ink), inset 0 0 0 5px var(--paper), inset 0 0 0 6px var(--ink);
        transition: background .15s;
    }
    .go:hover:not(:disabled) { background: var(--stamp); }
    .go:disabled { opacity: .7; cursor: progress; }
    .masuk :focus-visible { outline: 3px solid var(--stamp); outline-offset: 3px; }
    .cap {
        position: absolute; right: 0; top: 4px; padding: 2px 12px 0; border: 3px double var(--stamp); color: var(--stamp);
        font: 800 32px/1.15 var(--display); letter-spacing: .12em; text-transform: uppercase; mix-blend-mode: multiply;
        transform: rotate(-9deg); opacity: .92; animation: cap .35s cubic-bezier(.2, 1.4, .4, 1) both; pointer-events: none;
    }
    @keyframes cap { 0% { opacity: 0; transform: rotate(-9deg) scale(1.9); } 60% { opacity: 1; } 100% { opacity: .92; transform: rotate(-9deg) scale(1); } }
    .s-foot { margin-top: 26px; text-align: center; }
    .barcode { height: 42px; margin-bottom: 8px; background: repeating-linear-gradient(90deg, var(--ink) 0 2px, transparent 2px 4px, var(--ink) 4px 5px, transparent 5px 8px, var(--ink) 8px 11px, transparent 11px 12px, var(--ink) 12px 13px, transparent 13px 16px); }
    .s-foot p { margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: #5c4b3a; }

    .masuk .info { justify-self: start; align-self: center; }
    .masuk .info .shift { font: 700 18px var(--display); letter-spacing: .3em; text-transform: uppercase; color: var(--kraft-dim); }
    .masuk .info .clock { font: 800 clamp(84px, 10vw, 152px)/.9 var(--display); color: var(--kraft); letter-spacing: .01em; font-variant-numeric: tabular-nums; margin-top: 6px; }
    .masuk .info .clock i { font-style: normal; font-size: .28em; margin-left: 8px; color: var(--kraft-dim); vertical-align: top; }
    .masuk .info .date { margin-top: 10px; font-size: 15px; color: var(--kraft); text-transform: capitalize; }
    .info p { margin: 18px 0 0; max-width: 260px; font-size: 14px; color: var(--kraft-dim); }

    @media (max-width: 1100px) {
        .masuk { grid-template-columns: minmax(0, .7fr) minmax(320px, 400px); }
        .masuk .info { display: none; }
    }
    @media (max-width: 760px) {
        .masuk { grid-template-columns: 1fr; align-content: start; gap: 8px; padding: 22px 16px 40px; }
        .masuk .poster { writing-mode: horizontal-tb; transform: none; align-self: auto; justify-self: center; font-size: clamp(56px, 22vw, 104px); -webkit-text-stroke-width: 1.5px; margin-bottom: 16px; }
        .struk-wrap { width: 100%; max-width: 420px; justify-self: center; }
        .struk { padding: 40px 22px 42px; }
        .s-head h1 { font-size: 44px; }
        .cap { font-size: 26px; }
    }
    .masuk .tukar { display: block; width: 100%; margin-top: 10px; padding: 10px 0; background: none; border: 0; cursor: pointer; color: var(--ink); font: 700 13px var(--mono); text-decoration: underline; text-underline-offset: 3px; }
    @media (prefers-reduced-motion: reduce) { .struk, .cap { animation: none; } }
    </style>
</head>
@verbatim
<body x-data="shell()" x-cloak>

<!-- Aset SVG bersama: ilustrasi cangkir & pola biji kopi -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <pattern id="beanp" width="70" height="70" patternUnits="userSpaceOnUse" patternTransform="rotate(18)">
            <g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <ellipse cx="18" cy="18" rx="8" ry="12" transform="rotate(25 18 18)"/><path d="M14 8c5 4 5 8 1 12s-2 7 3 10" transform="translate(-1 -2)"/>
                <ellipse cx="52" cy="52" rx="8" ry="12" transform="rotate(-30 52 52)"/><path d="M48 42c5 4 5 8 1 12s-2 7 3 10" transform="translate(-1 -2)"/>
            </g>