{{-- تخصيص لون السمة: 10 ألوان جاهزة (CSS وJS داخليان بلا بناء)، تُحفَظ في المتصفح وتعمل للطالب ولوحة التحكم --}}
@php
    // id => [mode, ar, en, swatch(3), vars]
    $palettes = [
        'dark'     => ['dark',  'داكن',   'Dark',     ['#2dd4bf', '#0e7490', '#0b1120'], null],
        'light'    => ['light', 'فاتح',   'Light',    ['#14b8a6', '#0369a1', '#f1f5f9'], null],
        'aurora'   => ['dark',  'الشفق',  'Aurora',   ['#34d3b0', '#a78bfa', '#0d1424'], [
            'bg' => '#0d1424', 'surface' => '#141c33', 's2' => '#1b2542', 's3' => '#252f52', 'border' => '#2b3760', 'input' => '#0d1424',
            'text' => '#eef0ff', 'text2' => '#c7cdf0', 'muted' => '#8e98c4', 'accent' => '#8b7cf6', 'hover' => '#a193fa',
            'soft' => 'rgb(139 124 246 / .18)', 'atext' => '#c4bbff', 'on' => '#ffffff', 'nav' => '#0a0f1d']],
        'pearl'    => ['light', 'اللؤلؤ', 'Pearl',    ['#c9a27e', '#b0566b', '#faf6f3'], [
            'bg' => '#faf6f3', 'surface' => '#ffffff', 's2' => '#f3ece7', 's3' => '#e9dfd8', 'border' => '#e4d8d0', 'input' => '#ffffff',
            'text' => '#3b2a2a', 'text2' => '#5b4747', 'muted' => '#8c7a76', 'accent' => '#b0566b', 'hover' => '#98445a',
            'soft' => '#f6e3e7', 'atext' => '#98445a', 'on' => '#ffffff', 'nav' => '#3b2a2e']],
        'nebula'   => ['dark',  'السديم', 'Nebula',   ['#f472b6', '#a855f7', '#150d20'], [
            'bg' => '#150d20', 'surface' => '#1e1330', 's2' => '#281a40', 's3' => '#33224f', 'border' => '#3d2a5c', 'input' => '#150d20',
            'text' => '#f6eefc', 'text2' => '#d9c7ee', 'muted' => '#a58cc4', 'accent' => '#ec4899', 'hover' => '#f472b6',
            'soft' => 'rgb(236 72 153 / .18)', 'atext' => '#f9a8d4', 'on' => '#ffffff', 'nav' => '#0f0818']],
        'olive'    => ['dark',  'الزيتون', 'Olive',    ['#a3b84a', '#d9c35a', '#11160c'], [
            'bg' => '#11160c', 'surface' => '#1a2113', 's2' => '#232c1a', 's3' => '#2d3822', 'border' => '#35422a', 'input' => '#11160c',
            'text' => '#eef3e2', 'text2' => '#cdd8b8', 'muted' => '#94a37a', 'accent' => '#a3b84a', 'hover' => '#b9cf5c',
            'soft' => 'rgb(163 184 74 / .18)', 'atext' => '#cfe27f', 'on' => '#11160c', 'nav' => '#0b0f08']],
        'silver'   => ['light', 'الفضة',  'Silver',   ['#94a3b8', '#0ea5e9', '#eef1f5'], [
            'bg' => '#eef1f5', 'surface' => '#ffffff', 's2' => '#e4e9f0', 's3' => '#d6dde7', 'border' => '#d3dae4', 'input' => '#ffffff',
            'text' => '#1e293b', 'text2' => '#475569', 'muted' => '#75839a', 'accent' => '#0284c7', 'hover' => '#0369a1',
            'soft' => '#dbeefb', 'atext' => '#075985', 'on' => '#ffffff', 'nav' => '#1e293b']],
        'sunset'   => ['dark',  'الغروب', 'Sunset',   ['#fbbf24', '#f97316', '#1a1210'], [
            'bg' => '#1a1210', 'surface' => '#241a17', 's2' => '#2f221e', 's3' => '#3b2b26', 'border' => '#46322b', 'input' => '#1a1210',
            'text' => '#fdf1e8', 'text2' => '#ecd4c4', 'muted' => '#b39483', 'accent' => '#f97316', 'hover' => '#fb923c',
            'soft' => 'rgb(249 115 22 / .18)', 'atext' => '#fdba74', 'on' => '#1a1210', 'nav' => '#110b09']],
        'lavender' => ['light', 'الخزامى', 'Lavender', ['#ec7eb4', '#7c3aed', '#f5f2ff'], [
            'bg' => '#f5f2ff', 'surface' => '#ffffff', 's2' => '#ece7fb', 's3' => '#ddd5f6', 'border' => '#dcd3f4', 'input' => '#ffffff',
            'text' => '#2b2150', 'text2' => '#4c4180', 'muted' => '#8277b0', 'accent' => '#7c3aed', 'hover' => '#6d28d9',
            'soft' => '#ebe3fd', 'atext' => '#5b21b6', 'on' => '#ffffff', 'nav' => '#2b2150']],
        'ocean'    => ['dark',  'المحيط', 'Ocean',    ['#22d3ee', '#0e7490', '#07181d'], [
            'bg' => '#07181d', 'surface' => '#0d242b', 's2' => '#133039', 's3' => '#1a3d48', 'border' => '#1f4651', 'input' => '#07181d',
            'text' => '#e6fafd', 'text2' => '#b6dde4', 'muted' => '#7fb0ba', 'accent' => '#22d3ee', 'hover' => '#67e8f9',
            'soft' => 'rgb(34 211 238 / .16)', 'atext' => '#67e8f9', 'on' => '#07181d', 'nav' => '#041014']],
    ];
    $custom = array_filter($palettes, fn ($p) => $p[4] !== null);
    $js = [];
    foreach ($palettes as $id => $p) {
        $js[] = ['id' => $id, 'mode' => $p[0], 'ar' => $p[1], 'en' => $p[2], 'sw' => $p[3], 'custom' => $p[4] !== null];
    }
@endphp
<script>
/* يُطبَّق قبل الرسم لتفادي الوميض */
(function () {
    try {
        var P = @json(array_map(fn ($p) => $p[0], $custom));
        var id = localStorage.getItem('palette');
        if (id && Object.prototype.hasOwnProperty.call(P, id)) {
            var r = document.documentElement;
            r.setAttribute('data-palette', id);
            r.classList.toggle('dark', P[id] === 'dark');
        }
    } catch (e) {}
})();
</script>
<style>
@foreach ($custom as $id => $p)
html[data-palette="{{ $id }}"] {
    --p-bg: {{ $p[4]['bg'] }}; --p-surface: {{ $p[4]['surface'] }}; --p-s2: {{ $p[4]['s2'] }}; --p-s3: {{ $p[4]['s3'] }};
    --p-border: {{ $p[4]['border'] }}; --p-input: {{ $p[4]['input'] }}; --p-text: {{ $p[4]['text'] }}; --p-text2: {{ $p[4]['text2'] }};
    --p-muted: {{ $p[4]['muted'] }}; --p-accent: {{ $p[4]['accent'] }}; --p-hover: {{ $p[4]['hover'] }}; --p-soft: {{ $p[4]['soft'] }};
    --p-atext: {{ $p[4]['atext'] }}; --p-on: {{ $p[4]['on'] }}; --p-nav: {{ $p[4]['nav'] }};
}
@endforeach

/* ---- ربط ألوان الواجهة بمتغيرات السمة ---- */
html[data-palette][data-palette][data-palette] body { background-color: var(--p-bg); color: var(--p-text); }
html[data-palette][data-palette][data-palette] .bg-slate-50 { background-color: var(--p-bg); }
html[data-palette][data-palette][data-palette] .bg-white { background-color: var(--p-surface); }
html[data-palette][data-palette][data-palette] [class~="bg-white/80"] { background-color: color-mix(in srgb, var(--p-surface) 82%, transparent); }
html[data-palette][data-palette][data-palette] [class~="bg-white/95"] { background-color: color-mix(in srgb, var(--p-surface) 95%, transparent); }
html[data-palette][data-palette][data-palette] .bg-slate-100 { background-color: var(--p-s2); }
html[data-palette][data-palette][data-palette] .bg-slate-200 { background-color: var(--p-s3); }
html[data-palette][data-palette][data-palette] .bg-slate-900,
html[data-palette][data-palette][data-palette] .bg-slate-950 { background-color: var(--p-nav); }
html[data-palette][data-palette][data-palette] .hover\:bg-slate-50:hover,
html[data-palette][data-palette][data-palette] .hover\:bg-slate-100:hover,
html[data-palette][data-palette][data-palette] .hover\:bg-slate-200:hover { background-color: var(--p-s2); }

html[data-palette][data-palette][data-palette] .border-slate-100,
html[data-palette][data-palette][data-palette] .border-slate-200,
html[data-palette][data-palette][data-palette] .border-slate-300,
html[data-palette][data-palette][data-palette] .divide-slate-100 > :not(:last-child),
html[data-palette][data-palette][data-palette] .divide-slate-200 > :not(:last-child) { border-color: var(--p-border); }
html[data-palette][data-palette][data-palette] .ring-slate-200 { --tw-ring-color: var(--p-border); }

html[data-palette][data-palette][data-palette] .text-slate-900,
html[data-palette][data-palette][data-palette] .text-slate-800 { color: var(--p-text); }
html[data-palette][data-palette][data-palette] .text-slate-700,
html[data-palette][data-palette][data-palette] .text-slate-600 { color: var(--p-text2); }
html[data-palette][data-palette][data-palette] .text-slate-500 { color: var(--p-muted); }

/* اللون الأساسي: أزرار صلبة */
html[data-palette][data-palette][data-palette] .bg-sky-600, html[data-palette][data-palette][data-palette] .bg-sky-500,
html[data-palette][data-palette][data-palette] .bg-indigo-600, html[data-palette][data-palette][data-palette] .bg-indigo-500,
html[data-palette][data-palette][data-palette] .bg-violet-600 { background-color: var(--p-accent); color: var(--p-on); }
html[data-palette][data-palette][data-palette] .hover\:bg-sky-700:hover, html[data-palette][data-palette][data-palette] .hover\:bg-sky-600:hover,
html[data-palette][data-palette][data-palette] .hover\:bg-indigo-700:hover, html[data-palette][data-palette][data-palette] .hover\:bg-indigo-500:hover { background-color: var(--p-hover); }
/* خلفيات خفيفة بلون السمة */
html[data-palette][data-palette][data-palette] .bg-indigo-50, html[data-palette][data-palette][data-palette] .bg-indigo-100,
html[data-palette][data-palette][data-palette] .bg-sky-50, html[data-palette][data-palette][data-palette] .bg-sky-100,
html[data-palette][data-palette][data-palette] .bg-violet-100,
html[data-palette][data-palette][data-palette] .hover\:bg-indigo-50:hover, html[data-palette][data-palette][data-palette] .hover\:bg-indigo-100:hover,
html[data-palette][data-palette][data-palette] .hover\:bg-sky-50:hover { background-color: var(--p-soft); }
/* نصوص بلون السمة */
html[data-palette][data-palette][data-palette] .text-indigo-950, html[data-palette][data-palette][data-palette] .text-indigo-800,
html[data-palette][data-palette][data-palette] .text-indigo-700, html[data-palette][data-palette][data-palette] .text-indigo-600,
html[data-palette][data-palette][data-palette] .text-sky-900, html[data-palette][data-palette][data-palette] .text-sky-800,
html[data-palette][data-palette][data-palette] .text-sky-700, html[data-palette][data-palette][data-palette] .text-sky-600,
html[data-palette][data-palette][data-palette] .text-violet-700,
html[data-palette][data-palette][data-palette] .hover\:text-indigo-700:hover, html[data-palette][data-palette][data-palette] .hover\:text-sky-700:hover,
html[data-palette][data-palette][data-palette] .group:hover .group-hover\:text-indigo-700,
html[data-palette][data-palette][data-palette] .group:hover .group-hover\:text-sky-700 { color: var(--p-atext); }
html[data-palette][data-palette][data-palette] .border-indigo-200, html[data-palette][data-palette][data-palette] .hover\:border-indigo-300:hover,
html[data-palette][data-palette][data-palette] .hover\:border-sky-200:hover { border-color: var(--p-accent); }
html[data-palette][data-palette][data-palette] .from-indigo-100, html[data-palette][data-palette][data-palette] .from-sky-100 { --tw-gradient-from: var(--p-soft); }
html[data-palette][data-palette][data-palette] .to-sky-100, html[data-palette][data-palette][data-palette] .to-indigo-100 { --tw-gradient-to: var(--p-s2); }

/* الحقول */
html[data-palette][data-palette][data-palette] input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),
html[data-palette][data-palette][data-palette] select, html[data-palette][data-palette][data-palette] textarea {
    background-color: var(--p-input); color: var(--p-text); border-color: var(--p-border); }
html[data-palette][data-palette][data-palette] input::placeholder, html[data-palette][data-palette][data-palette] textarea::placeholder { color: var(--p-muted); }
html[data-palette][data-palette][data-palette] input:focus, html[data-palette][data-palette][data-palette] select:focus,
html[data-palette][data-palette][data-palette] textarea:focus { border-color: var(--p-accent); --tw-ring-color: var(--p-soft); }
html[data-palette][data-palette][data-palette] .pagination .page-link { background-color: var(--p-surface); border-color: var(--p-border); color: var(--p-text2); }
html[data-palette][data-palette][data-palette] linearGradient stop:first-child { stop-color: var(--p-accent); }
html[data-palette][data-palette][data-palette] linearGradient stop:last-child { stop-color: var(--p-hover); }

/* لوحة التحكم: متغيرات CSS الخاصة بها */
html[data-palette][data-palette][data-palette] {
    --background: var(--p-bg); --surface: var(--p-surface); --text-primary: var(--p-text); --text-secondary: var(--p-muted);
    --muted-foreground: var(--p-muted); --border: var(--p-border); --primary: var(--p-accent); --primary-dark: var(--p-hover); --primary-light: var(--p-soft);
    --uu-ink: var(--p-text); --uu-ink-2: var(--p-text2); --uu-ink-3: var(--p-muted); --uu-line: var(--p-border); --uu-line-2: var(--p-s2);
    --uu-wash: var(--p-s2); --uu-surface: var(--p-surface); --uu-brand: var(--p-accent); --uu-brand-hover: var(--p-hover); --uu-brand-soft: var(--p-soft);
}
html[data-palette][data-palette][data-palette] #cms-sidebar {
    --sb-bg: var(--p-surface); --sb-border: var(--p-border); --sb-text: var(--p-text2); --sb-strong: var(--p-text); --sb-muted: var(--p-muted);
    --sb-hover: var(--p-s2); --sb-active-bg: var(--p-soft); --sb-active-text: var(--p-atext); --sb-icon: var(--p-accent); --sb-card: var(--p-s2);
    --sb-badge-bg: var(--p-soft); --sb-badge-text: var(--p-atext);
}
html[data-palette] #mo-progress, html[data-palette] #mo-top, html[data-palette] #mo-fly { background: linear-gradient(135deg, var(--p-accent), var(--p-hover)); color: var(--p-on); }

/* ---- زر ولوحة الاختيار ---- */
html.pal-switching, html.pal-switching *, html.pal-switching *::before, html.pal-switching *::after {
    transition: background-color .35s ease, color .35s ease, border-color .35s ease, fill .35s ease !important; }
.pal-panel {
    --pp-bg: #ffffff; --pp-card: #eef2f7; --pp-border: #d9e2ee; --pp-text: #1e293b; --pp-muted: #64748b; --pp-accent: #2563eb;
    position: fixed; z-index: 10001; width: min(94vw, 440px); padding: 16px; border-radius: 18px; border: 1px solid var(--pp-border);
    background: var(--pp-bg); color: var(--pp-text); box-shadow: 0 24px 60px -16px rgb(15 23 42 / .35);
    font-family: inherit; line-height: 1.4;
}
html.dark .pal-panel { --pp-bg: #111a2e; --pp-card: #1a2540; --pp-border: #28365a; --pp-text: #e8edf8; --pp-muted: #94a3b8; --pp-accent: #38bdf8; }
html[data-palette] .pal-panel { --pp-bg: var(--p-surface); --pp-card: var(--p-s2); --pp-border: var(--p-border); --pp-text: var(--p-text); --pp-muted: var(--p-muted); --pp-accent: var(--p-accent); }
.pal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.pal-head h3 { margin: 0; font-size: 15px; font-weight: 800; }
.pal-reset { border: 0; background: none; padding: 4px 6px; font: inherit; font-size: 13px; font-weight: 700; color: var(--pp-accent); cursor: pointer; border-radius: 8px; }
.pal-reset:hover { text-decoration: underline; }
.pal-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
@media (max-width: 420px) { .pal-grid { grid-template-columns: repeat(3, 1fr); } }
.pal-card { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 12px 6px 10px; border-radius: 14px; cursor: pointer;
    border: 1.5px solid var(--pp-border); background: var(--pp-card); color: var(--pp-text); font: inherit; font-size: 12px; font-weight: 700;
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
.pal-card:hover { transform: translateY(-3px); box-shadow: 0 10px 22px -12px rgb(15 23 42 / .45); }
.pal-card[aria-pressed="true"] { border-color: var(--pp-accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--pp-accent) 25%, transparent); }
.pal-sw { display: flex; width: 54px; height: 24px; border-radius: 8px; overflow: hidden; border: 1px solid rgb(0 0 0 / .1); }
.pal-sw i { flex: 1; display: block; }
.pal-pop { animation: pal-pop .22s cubic-bezier(.2, .9, .3, 1.15) backwards; }
@keyframes pal-pop { from { opacity: 0; scale: .95; translate: 0 -8px; } }
@media (prefers-reduced-motion: reduce) { .pal-pop { animation: none; } .pal-card { transition: none; } }
</style>
<script>
(function () {
    var d = document, root = d.documentElement;
    var LIST = @json($js);
    var ar = (root.getAttribute('lang') || 'ar').indexOf('ar') === 0;
    var T = ar ? { title: 'تخصيص لون السمة', reset: 'الافتراضي', open: 'ألوان السمة' } : { title: 'Customize theme color', reset: 'Default', open: 'Theme colors' };
    var byId = {}; LIST.forEach(function (p) { byId[p.id] = p; });

    function store(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }
    function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function systemDark() { return !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches); }
    function current() {
        var id = root.getAttribute('data-palette');
        if (id) { return id; }
        return root.classList.contains('dark') ? 'dark' : 'light';
    }
    function flash() {
        root.classList.add('pal-switching');
        setTimeout(function () { root.classList.remove('pal-switching'); }, 450);
    }
    function apply(id) {
        var p = byId[id]; if (!p) { return; }
        flash();
        if (p.custom) {
            root.setAttribute('data-palette', id);
            root.classList.toggle('dark', p.mode === 'dark');
            store('palette', id);
        } else {
            root.removeAttribute('data-palette');
            root.classList.toggle('dark', p.mode === 'dark');
            store('palette', null); store('theme', p.mode);
        }
        sync();
    }
    function reset() {
        flash();
        root.removeAttribute('data-palette'); store('palette', null);
        var t = read('theme');
        root.classList.toggle('dark', t === 'dark' || (!t && systemDark()));
        sync();
    }

    var panel = null, opener = null;
    function sync() {
        if (!panel) { return; }
        var cur = current();
        panel.querySelectorAll('.pal-card').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-id') === cur ? 'true' : 'false'); });
    }
    function close() {
        if (!panel) { return; }
        panel.remove(); panel = null;
        d.removeEventListener('keydown', onKey, true);
        if (opener) { opener.setAttribute('aria-expanded', 'false'); opener.focus(); opener = null; }
    }
    function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
    function open(btn) {
        if (panel) { close(); return; }
        opener = btn; btn.setAttribute('aria-expanded', 'true');
        panel = d.createElement('div'); panel.className = 'pal-panel pal-pop'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', T.title);
        var head = d.createElement('div'); head.className = 'pal-head';
        var h = d.createElement('h3'); h.textContent = T.title;
        var rs = d.createElement('button'); rs.type = 'button'; rs.className = 'pal-reset'; rs.textContent = T.reset;
        rs.addEventListener('click', reset);
        head.appendChild(h); head.appendChild(rs); panel.appendChild(head);
        var grid = d.createElement('div'); grid.className = 'pal-grid';
        LIST.forEach(function (p) {
            var c = d.createElement('button'); c.type = 'button'; c.className = 'pal-card'; c.setAttribute('data-id', p.id);
            var sw = d.createElement('span'); sw.className = 'pal-sw';
            p.sw.forEach(function (col) { var i = d.createElement('i'); i.style.background = col; sw.appendChild(i); });
            var n = d.createElement('span'); n.textContent = ar ? p.ar : p.en;
            c.appendChild(sw); c.appendChild(n);
            c.addEventListener('click', function () { apply(p.id); });
            grid.appendChild(c);
        });
        panel.appendChild(grid); d.body.appendChild(panel); sync();
        // الموضع تحت الزر مع بقائه داخل الشاشة
        var r = btn.getBoundingClientRect(), w = panel.offsetWidth, vw = root.clientWidth;
        var left = Math.max(8, Math.min(vw - w - 8, r.left + r.width / 2 - w / 2));
        var top = r.bottom + 10;
        if (top + panel.offsetHeight > window.innerHeight - 8) { top = Math.max(8, r.top - panel.offsetHeight - 10); }
        panel.style.left = left + 'px'; panel.style.top = top + 'px';
        d.addEventListener('keydown', onKey, true);
        var sel = panel.querySelector('.pal-card[aria-pressed="true"]') || panel.querySelector('.pal-card'); if (sel) { sel.focus({ preventScroll: true }); }
    }
    d.addEventListener('click', function (e) {
        var o = e.target.closest('[data-palette-open]');
        if (o) { e.preventDefault(); open(o); return; }
        if (panel && !panel.contains(e.target)) { close(); }
    });
    // زر القمر/الشمس يعود للوضع الافتراضي (الفاتح/الداكن) عند وجود سمة مخصّصة
    d.addEventListener('click', function (e) {
        if (e.target.closest('[data-theme-toggle]') && root.hasAttribute('data-palette')) { root.removeAttribute('data-palette'); store('palette', null); }
    }, true);

    function addButtons() {
        d.querySelectorAll('[data-theme-toggle]').forEach(function (t) {
            if (t.nextElementSibling && t.nextElementSibling.hasAttribute('data-palette-open')) { return; }
            var b = d.createElement('button'); b.type = 'button'; b.className = t.className; b.setAttribute('data-palette-open', '');
            b.setAttribute('title', T.open); b.setAttribute('aria-label', T.open); b.setAttribute('aria-haspopup', 'dialog'); b.setAttribute('aria-expanded', 'false');
            b.innerHTML = '<i class="fa-solid fa-palette"></i>';
            t.insertAdjacentElement('afterend', b);
        });
    }
    d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', addButtons) : addButtons();
})();
</script>
