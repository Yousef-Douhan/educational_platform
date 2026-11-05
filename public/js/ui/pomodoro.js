(function () {
    var d = document, T = JSON.parse(d.getElementById('pm-config').textContent), KEY = 'pomo', MODES = { focus: 25, short: 5, long: 15 };
    var st = { mode: 'focus', total: 25 * 60, endAt: null, remain: 25 * 60 }, tick = null, modal, timeEl, barEl, msgEl, startBtn, pill, pillT, titleBase = d.title;

    function load() { try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (s && MODES[s.mode] && s.total > 0 && s.total <= 3600) { st = { mode: s.mode, total: +s.total, endAt: s.endAt ? +s.endAt : null, remain: +s.remain || s.total }; } } catch (e) {} }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
    function left() { return st.endAt ? Math.max(0, Math.ceil((st.endAt - Date.now()) / 1000)) : st.remain; }
    function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r; }

    function beep() {
        try {
            var C = window.AudioContext || window.webkitAudioContext, c = new C(), t = c.currentTime;
            [0, .25, .5].forEach(function (o) { var os = c.createOscillator(), g = c.createGain(); os.frequency.value = 880; os.connect(g); g.connect(c.destination); g.gain.setValueAtTime(.18, t + o); g.gain.exponentialRampToValueAtTime(.001, t + o + .2); os.start(t + o); os.stop(t + o + .22); });
        } catch (e) {}
        try { if (navigator.vibrate) { navigator.vibrate([200, 100, 200]); } } catch (e) {}
    }

    function finish() {
        st.endAt = null; st.remain = 0; save(); stopTick(); beep(); paint();
    }
    function stopTick() { if (tick) { clearInterval(tick); tick = null; } }
    function startTick() { stopTick(); tick = setInterval(function () { if (left() <= 0 && st.endAt) { finish(); } else { paint(); } }, 250); }

    function paint() {
        var s = left(), running = !!st.endAt, over = !running && st.remain === 0;
        if (timeEl) {
            timeEl.textContent = fmt(s); timeEl.classList.toggle('done', over);
            barEl.style.width = (100 - (s / st.total) * 100) + '%';
            msgEl.textContent = over ? T.done : '';
            startBtn.textContent = running ? T.pause : T.start;
            modal.querySelectorAll('.pm-mode').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-m') === st.mode ? 'true' : 'false'); });
        }
        pillT.textContent = fmt(s);
        pill.classList.toggle('show', (running || over) && !modal.classList.contains('open'));
        d.title = running ? fmt(s) + ' · ' + titleBase : (over ? '⏰ ' + titleBase : titleBase);
    }

    function setMode(m) { st = { mode: m, total: MODES[m] * 60, endAt: null, remain: MODES[m] * 60 }; save(); stopTick(); paint(); }
    function toggle() {
        if (st.endAt) { st.remain = left(); st.endAt = null; save(); stopTick(); }
        else { if (st.remain <= 0) { st.remain = st.total; } st.endAt = Date.now() + st.remain * 1000; save(); startTick(); }
        paint();
    }
    function reset() { st.endAt = null; st.remain = st.total; save(); stopTick(); paint(); }
    function open() { modal.classList.add('open'); paint(); startBtn.focus(); }
    function close() { modal.classList.remove('open'); paint(); }

    function el(tag, cls, txt) { var e = d.createElement(tag); if (cls) { e.className = cls; } if (txt) { e.textContent = txt; } return e; }

    function build() {
        modal = el('div', 'pm-modal'); modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-label', T.title);
        var dlg = el('div', 'pm-dlg'), head = el('div', 'pm-head'), h = el('h2', '', '⏱ ' + T.title), x = el('button', 'pm-x', '×');
        x.type = 'button'; x.setAttribute('aria-label', T.close); x.addEventListener('click', close);
        head.appendChild(h); head.appendChild(x);
        var body = el('div', 'pm-body'), modes = el('div', 'pm-modes');
        [['focus', T.focus], ['short', T.short], ['long', T.long]].forEach(function (m) { var b = el('button', 'pm-mode', m[1]); b.type = 'button'; b.setAttribute('data-m', m[0]); b.addEventListener('click', function () { setMode(m[0]); }); modes.appendChild(b); });
        timeEl = el('div', 'pm-time'); var bar = el('div', 'pm-bar'); barEl = el('i'); bar.appendChild(barEl); msgEl = el('div', 'pm-msg'); msgEl.setAttribute('role', 'status');
        var acts = el('div', 'pm-acts'); startBtn = el('button', 'pm-btn primary'); startBtn.type = 'button'; startBtn.addEventListener('click', toggle);
        var rs = el('button', 'pm-btn', '↻ ' + T.reset); rs.type = 'button'; rs.addEventListener('click', reset);
        acts.appendChild(startBtn); acts.appendChild(rs);
        [modes, timeEl, bar, msgEl, acts].forEach(function (n) { body.appendChild(n); });
        dlg.appendChild(head); dlg.appendChild(body); modal.appendChild(dlg); d.body.appendChild(modal);
        modal.addEventListener('mousedown', function (e) { if (e.target === modal) { close(); } });
        pill = el('button', 'pm-pill'); pill.type = 'button'; pill.setAttribute('aria-label', T.open); pill.appendChild(el('span', '', '⏱')); pillT = el('b'); pill.appendChild(pillT);
        pill.addEventListener('click', open); d.body.appendChild(pill);
    }

    function addButtons() {
        d.querySelectorAll('[data-theme-toggle]').forEach(function (t) {
            var anchor = t.nextElementSibling && t.nextElementSibling.hasAttribute('data-palette-open') ? t.nextElementSibling : t;
            if (anchor.nextElementSibling && anchor.nextElementSibling.hasAttribute('data-pomo-open')) { return; }
            var b = d.createElement('button'); b.type = 'button'; b.className = t.className; b.setAttribute('data-pomo-open', '');
            b.setAttribute('title', T.open); b.setAttribute('aria-label', T.open); b.innerHTML = '<i class="fa-solid fa-stopwatch"></i>';
            b.addEventListener('click', open);
            anchor.insertAdjacentElement('afterend', b);
        });
    }

    function init() {
        load(); build(); addButtons();
        if (st.endAt) { if (left() <= 0) { finish(); } else { startTick(); } }
        paint();
        d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) { close(); } });
        window.addEventListener('storage', function (e) { if (e.key === KEY) { load(); st.endAt ? startTick() : stopTick(); paint(); } });
    }
    d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', init) : init();
})();
