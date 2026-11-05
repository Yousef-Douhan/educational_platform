{{-- حركة وانيميشن الموقع (الطالب + لوحة التحكم): CSS وJS خفيفان بلا مكتبات، ويحترمان prefers-reduced-motion --}}
<style>
@media (prefers-reduced-motion: no-preference) {
    /* ---------- دخول الصفحة ---------- */
    main { animation: mo-page .5s ease backwards; }
    @keyframes mo-page { from { opacity: 0; translate: 0 10px; } }

    /* ---------- شريط تقدّم التمرير ---------- */
    #mo-progress { position: fixed; inset-block-start: 0; inset-inline: 0; height: 3px; z-index: 9999; pointer-events: none;
        transform: scaleX(0); transform-origin: left; background: linear-gradient(90deg, #4f46e5, #7c3aed, #fbbf24); }
    html[dir="rtl"] #mo-progress { transform-origin: right; }

    /* ---------- الكشف عند التمرير (يُفعَّل بالـJS فقط) ---------- */
    html.mo [data-mo][data-mo] { transition: opacity .6s ease var(--mo-d, 0ms), translate .6s cubic-bezier(.2, .7, .2, 1) var(--mo-d, 0ms); }
    html.mo [data-mo][data-mo]:not(.mo-in) { opacity: 0; translate: 0 22px; }

    /* ---------- بطاقات الدورات واللوحات: رفع + توهّج + تكبير الصورة ---------- */
    a.group.rounded-2xl, .db-card { transition: transform .25s ease, box-shadow .25s ease, opacity .6s ease, translate .6s ease; }
    a.group.rounded-2xl:hover, a.db-card:hover { transform: translateY(-6px); box-shadow: 0 20px 44px -20px rgb(79 70 229 / .45); }
    a.group.rounded-2xl img { transition: transform .7s cubic-bezier(.2, .7, .2, 1); }
    a.group.rounded-2xl:hover img { transform: scale(1.07); }

    /* ---------- الأزرار: ضغطة خفيفة ---------- */
    main button:not(:disabled):active, main a[class*="bg-sky-"]:active, main a[class*="bg-indigo-"]:active { scale: .97; }

    /* ---------- الشريط العلوي ---------- */
    header nav > a { position: relative; }
    header nav > a::after { content: ""; position: absolute; inset-inline: 12px; bottom: 2px; height: 2px; border-radius: 2px;
        background: currentColor; transform: scaleX(0); transition: transform .25s ease; opacity: .7; }
    header nav > a:hover::after { transform: scaleX(1); }
    header a[href$="/cart"]:hover i { animation: mo-wiggle .6s ease; }
    header a[href*="/notifications"]:hover i { animation: mo-ring .8s ease; transform-origin: 50% 0; }
    header a[href$="/cart"] > span, header a[href*="/notifications"] > span { animation: mo-pop .65s cubic-bezier(.3, 1.7, .5, 1) .25s backwards; }
    header a.mo-cart-hit { animation: mo-hit .5s ease; }
    @keyframes mo-wiggle { 20% { rotate: -14deg; } 45% { rotate: 12deg; } 70% { rotate: -7deg; } 100% { rotate: 0deg; } }
    @keyframes mo-ring { 15% { rotate: 18deg; } 35% { rotate: -16deg; } 55% { rotate: 10deg; } 75% { rotate: -6deg; } 100% { rotate: 0deg; } }
    @keyframes mo-pop { from { scale: 0; opacity: 0; } }
    @keyframes mo-hit { 30% { scale: 1.28; background-color: #c7d2fe; } 100% { scale: 1; } }

    /* ---------- القوائم المنسدلة ---------- */
    details[open] > :not(summary) { animation: mo-drop .22s ease backwards; }
    @keyframes mo-drop { from { opacity: 0; translate: 0 -8px; } }

    /* ---------- السلة ---------- */
    .mo-removing { transition: opacity .3s ease, translate .38s ease, height .38s ease .06s, padding .38s ease .06s,
        margin .38s ease .06s, border-width .38s ease .06s !important;
        opacity: 0 !important; translate: 48px 0; height: 0 !important; padding-block: 0 !important;
        margin-block: 0 !important; border-width: 0 !important; overflow: hidden; }
    html[dir="rtl"] .mo-removing { translate: -48px 0; }
    #mo-fly { position: fixed; left: 0; top: 0; z-index: 10000; pointer-events: none; width: 38px; height: 38px; border-radius: 9999px; display: grid;
        place-items: center; color: #fff; font-size: 15px; background: linear-gradient(135deg, #4f46e5, #7c3aed);
        box-shadow: 0 10px 24px -6px rgb(79 70 229 / .6); }

    /* ---------- حالة التحميل للأزرار ---------- */
    button.mo-busy { pointer-events: none; opacity: .85; position: relative; }
    button.mo-busy::after { content: ""; display: inline-block; width: 1em; height: 1em; margin-inline-start: .6em; vertical-align: -.15em;
        border: 2px solid currentColor; border-inline-end-color: transparent; border-radius: 50%; animation: mo-spin .7s linear infinite; }
    @keyframes mo-spin { to { rotate: 360deg; } }

    /* ---------- زر العودة للأعلى ---------- */
    #mo-top { position: fixed; inset-block-end: 20px; inset-inline-end: 20px; z-index: 60; width: 44px; height: 44px; border-radius: 9999px;
        border: 0; color: #fff; cursor: pointer; background: linear-gradient(135deg, #4f46e5, #7c3aed);
        box-shadow: 0 12px 28px -10px rgb(79 70 229 / .7); opacity: 0; translate: 0 16px; pointer-events: none;
        transition: opacity .3s ease, translate .3s ease, scale .15s ease; }
    #mo-top.show { opacity: 1; translate: 0 0; pointer-events: auto; }
    #mo-top:hover { scale: 1.1; }

    /* ---------- لوحة التحكم ---------- */
    #cms-sidebar nav a { transition: background-color .2s ease, color .2s ease, translate .2s ease; }
    #cms-sidebar nav a:hover { translate: 4px 0; }
    html[dir="rtl"] #cms-sidebar nav a:hover { translate: -4px 0; }
    .crud-table tbody tr { animation: mo-row .4s ease backwards; }
    @for ($i = 1; $i <= 12; $i++)
    .crud-table tbody tr:nth-child({{ $i }}) { animation-delay: {{ $i * 35 }}ms; }
    @endfor
    @keyframes mo-row { from { opacity: 0; translate: 0 10px; } }
    .crud-modal[aria-hidden="false"] .crud-modal__dialog, .crud-modal.is-open .crud-modal__dialog, .crud-modal.open .crud-modal__dialog {
        animation: mo-modal .32s cubic-bezier(.2, .9, .3, 1.15) backwards; }
    @keyframes mo-modal { from { opacity: 0; scale: .94; translate: 0 14px; } }
}
</style>
<script>
(function () {
    var d = document, root = d.documentElement;
    if (!window.matchMedia || matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    root.classList.add('mo');
    var isCms = !!d.getElementById('cms-sidebar');
    var rtl = root.getAttribute('dir') === 'rtl';

    function ready(fn) { d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', fn) : fn(); }

    ready(function () {
        /* شريط التقدّم + زر الأعلى */
        var bar = d.createElement('div'); bar.id = 'mo-progress'; d.body.appendChild(bar);
        var top = d.createElement('button'); top.id = 'mo-top'; top.type = 'button';
        top.setAttribute('aria-label', rtl ? 'العودة للأعلى' : 'Back to top');
        top.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
        top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        d.body.appendChild(top);
        var ticking = false;
        function onScroll() {
            if (ticking) { return; } ticking = true;
            requestAnimationFrame(function () {
                var h = root.scrollHeight - root.clientHeight, y = window.scrollY || root.scrollTop;
                bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0) + ')';
                top.classList.toggle('show', y > 600);
                ticking = false;
            });
        }
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

        /* الكشف التدريجي عند التمرير */
        var SEL = 'main h1, main h2, main .rounded-xl, main .rounded-2xl, main .rounded-3xl, main .db-card';
        var TAGS = { DIV: 1, SECTION: 1, ARTICLE: 1, ASIDE: 1, LI: 1, FORM: 1, H1: 1, H2: 1, TABLE: 1, A: 1 };
        var counters = new WeakMap(), items = [];
        d.querySelectorAll(SEL).forEach(function (el) {
            if (!TAGS[el.tagName]) { return; }
            if (el.tagName === 'A' && !el.classList.contains('group') && !el.classList.contains('db-card')) { return; }
            if (el.closest('.fixed, .sticky, .crud-modal, [hidden], [class*="translate-"]') || el.parentElement.closest('[data-mo]')) { return; }
            var i = counters.get(el.parentElement) || 0; counters.set(el.parentElement, i + 1);
            el.setAttribute('data-mo', ''); el.style.setProperty('--mo-d', Math.min(i, 8) * 70 + 'ms');
            items.push(el);
        });
        function show(el) {
            el.classList.add('mo-in');
            setTimeout(function () { el.removeAttribute('data-mo'); el.classList.remove('mo-in'); el.style.removeProperty('--mo-d'); }, 1300);
        }
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (es) {
                es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); show(e.target); } });
            }, { threshold: 0.05, rootMargin: '0px 0px -5% 0px' });
            items.forEach(function (el) { io.observe(el); });
        } else { items.forEach(show); }
        setTimeout(function () { d.querySelectorAll('[data-mo]:not(.mo-in)').forEach(show); }, 4000);

        /* عدّاد الأرقام (لوحات المؤشرات و[data-countup]) */
        d.querySelectorAll('.db-kpi-value, [data-countup]').forEach(function (el) {
            var n = el.firstChild;
            if (!n || n.nodeType !== 3) { return; }
            var m = /^\s*([\d,]+)(\.\d+)?/.exec(n.nodeValue);
            if (!m) { return; }
            var target = parseFloat(m[1].replace(/,/g, '') + (m[2] || '')), dec = m[2] ? m[2].length - 1 : 0, rest = n.nodeValue.slice(m[0].length);
            if (!isFinite(target) || target === 0) { return; }
            var start = null, dur = 1100;
            function fmt(v) { return v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); }
            var orig = n.nodeValue;
            n.nodeValue = fmt(0) + rest;
            requestAnimationFrame(function step(t) {
                if (start === null) { start = t; }
                var p = Math.min(1, (t - start) / dur), e = 1 - Math.pow(1 - p, 3);
                if (p < 1) { n.nodeValue = fmt(target * e) + rest; requestAnimationFrame(step); } else { n.nodeValue = orig; }
            });
        });
    });

    /* ---------- السلة والنماذج (طلاب فقط) ---------- */
    if (isCms) { return; }

    function busy(f) {
        var b = f.querySelector('button:not([type="button"])');
        if (b) { b.classList.add('mo-busy'); }
    }
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { d.querySelectorAll('.mo-busy').forEach(function (b) { b.classList.remove('mo-busy'); }); d.querySelectorAll('form[data-mo-go]').forEach(function (f) { f.removeAttribute('data-mo-go'); }); }
    });

    d.addEventListener('submit', function (e) {
        var f = e.target;
        if (!(f instanceof HTMLFormElement) || e.defaultPrevented) { return; }
        if (f.hasAttribute('data-mo-go')) { e.preventDefault(); return; }   // منع الإرسال المزدوج
        var act = f.getAttribute('action') || '', m = f.querySelector('input[name="_method"]');

        /* حذف من السلة: انزلاق + طيّ الصف ثم الإرسال */
        if (m && m.value.toUpperCase() === 'DELETE' && /\/cart\/items\//.test(act)) {
            var row = f.closest('.rounded-xl');
            e.preventDefault(); f.setAttribute('data-mo-go', '');
            if (row) {
                row.removeAttribute('data-mo'); row.style.height = row.offsetHeight + 'px'; void row.offsetHeight;
                row.classList.add('mo-removing');
                setTimeout(function () { f.removeAttribute('data-mo-go'); f.setAttribute('data-mo-go', ''); f.submit(); }, 420);
            } else { f.submit(); }
            return;
        }

        /* إضافة للسلة: كرة تطير إلى أيقونة السلة */
        if (/\/cart\/items$/.test(act) && !m) {
            var btn = f.querySelector('button'), cart = d.querySelector('header a[href$="/cart"]');
            f.setAttribute('data-mo-go', '');
            if (btn && cart && f.animate) {
                e.preventDefault();
                var a = btn.getBoundingClientRect(), c = cart.getBoundingClientRect();
                var fly = d.createElement('div'); fly.id = 'mo-fly'; fly.innerHTML = '<i class="fa-solid fa-cart-shopping"></i>';
                d.body.appendChild(fly); busy(f);
                var x0 = a.left + a.width / 2 - 19, y0 = a.top + a.height / 2 - 19, x1 = c.left + c.width / 2 - 19, y1 = c.top + c.height / 2 - 19;
                var anim = fly.animate([
                    { transform: 'translate(' + x0 + 'px,' + y0 + 'px) scale(1)', opacity: 1, offset: 0 },
                    { transform: 'translate(' + ((x0 + x1) / 2) + 'px,' + (Math.min(y0, y1) - 90) + 'px) scale(1.15)', opacity: 1, offset: .45 },
                    { transform: 'translate(' + x1 + 'px,' + y1 + 'px) scale(.35)', opacity: .9, offset: 1 }
                ], { duration: 720, easing: 'cubic-bezier(.45,.05,.3,1)', fill: 'forwards' });
                var done = function () { fly.remove(); cart.classList.add('mo-cart-hit'); setTimeout(function () { f.submit(); }, 260); };
                anim.onfinish = done; setTimeout(function () { if (fly.isConnected) { done(); } }, 1200);
            } else { busy(f); }
            return;
        }

        /* الدفع وتسجيل الدخول/الحساب: حالة تحميل + منع التكرار */
        if (/\/(checkout|login|register)$/.test(act)) { f.setAttribute('data-mo-go', ''); busy(f); }
    }, false);
})();
</script>
