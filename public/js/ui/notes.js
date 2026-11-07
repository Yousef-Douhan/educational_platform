(function () {
    'use strict';
    var D = document, $ = function (id) { return D.getElementById(id); };
    var CFG = JSON.parse(D.getElementById('st-config').textContent);
    var T = CFG.labels, DATA = CFG.data, URLS = CFG.urls, CSRF = CFG.csrf;
    var LANG = D.documentElement.getAttribute('lang') || 'ar';

    function withId(base, id) { return base.replace(/\/0$/, '/' + id); }
    function api(method, url, body) {
        var o = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
        if (body !== undefined) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
        return fetch(url, o).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(j.message || 'error'); e.status = r.status; throw e; } return j; });
        });
    }
    function el(tag, cls, text) { var e = D.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
    function debounce(fn, ms) { var t; var f = function () { clearTimeout(t); t = setTimeout(fn, ms); }; f.cancel = function () { clearTimeout(t); }; return f; }
    function timeNow() { try { return new Date().toLocaleTimeString(LANG, { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; } }

    /* ---------------- التبويبات ---------------- */
    var tabs = { notes: $('tab-notes'), board: $('tab-board') }, panes = { notes: $('pane-notes'), board: $('pane-board') }, boardStarted = false;
    function showTab(k) {
        Object.keys(tabs).forEach(function (x) { tabs[x].setAttribute('aria-selected', x === k ? 'true' : 'false'); panes[x].hidden = x !== k; });
        try { history.replaceState(null, '', k === 'board' ? '#board' : location.pathname + location.search); } catch (e) {}
        if (k === 'board' && !boardStarted) { boardStarted = true; Board.init(); }
    }
    tabs.notes.addEventListener('click', function () { showTab('notes'); });
    tabs.board.addEventListener('click', function () { showTab('board'); });

    /* ---------------- الملاحظات ---------------- */
    var Notes = (function () {
        var notes = DATA.notes.slice(), cur = null, dirty = false, saving = false, again = false, open = {}, preview = false;
        var title = $('n-title'), body = $('n-body'), status = $('n-status'), prev = $('n-prev');

        function folders() {
            return [{ id: null, name: T.general }].concat(DATA.courses.map(function (c) { return { id: c.id, name: c.title }; }));
        }
        function renderFolders() {
            var root = $('folders'); root.textContent = '';
            folders().forEach(function (f) {
                var list = notes.filter(function (n) { return n.course_id === f.id; });
                var key = f.id === null ? 'g' : String(f.id);
                var box = el('div', 'st-folder' + (open[key] ? ' open' : ''));
                var h = el('button', 'st-fh'); h.type = 'button'; h.setAttribute('aria-expanded', open[key] ? 'true' : 'false');
                h.appendChild(el('i', 'fa-solid fa-play st-chev')); h.appendChild(el('span', '', f.name)); h.appendChild(el('span', 'st-count', String(list.length)));
                h.addEventListener('click', function () { open[key] = !open[key]; renderFolders(); });
                box.appendChild(h);
                var ul = el('div', 'st-list');
                if (!list.length) { ul.appendChild(el('div', 'st-empty', T.empty_folder)); }
                list.forEach(function (n) {
                    var b = el('button', 'st-note', n.title); b.type = 'button'; b.title = n.title;
                    if (cur && cur.id === n.id) { b.setAttribute('aria-current', 'true'); }
                    b.addEventListener('click', function () { select(n.id); });
                    ul.appendChild(b);
                });
                var add = el('button', 'st-add', '+ ' + T.new_note); add.type = 'button';
                add.addEventListener('click', function () { create(f.id); });
                ul.appendChild(add); box.appendChild(ul); root.appendChild(box);
            });
        }
        function setStatus(t) { status.textContent = t || ''; }

        function flush() {
            if (!cur || !dirty) { return Promise.resolve(); }
            if (saving) { again = true; return Promise.resolve(); }
            saving = true; dirty = false; setStatus(T.saving);
            var id = cur.id, payload = { title: (title.value.trim() || T.untitled), body: body.value };
            return api('PUT', withId(URLS.note, id), payload).then(function (r) {
                var n = notes.filter(function (x) { return x.id === id; })[0];
                if (n) { n.title = r.title; n.updated_at = r.updated_at; }
                if (cur && cur.id === id) { setStatus(T.saved_at.replace(':time', timeNow())); }
                renderFolders();
            }).catch(function () { dirty = true; setStatus(T.save_failed); }).then(function () {
                saving = false; if (again) { again = false; return flush(); }
            });
        }
        var auto = debounce(flush, 1500);
        function touch() { dirty = true; setStatus('…'); auto(); if (preview) { renderPreview(); } }

        function select(id) {
            return flush().then(function () {
                return api('GET', withId(URLS.note, id));
            }).then(function (n) {
                auto.cancel(); cur = { id: n.id, course_id: n.course_id }; dirty = false;
                title.value = n.title; body.value = n.body || ''; setStatus('');
                open[n.course_id === null ? 'g' : String(n.course_id)] = true;
                $('ph').hidden = true; $('edit').hidden = false; setPreview(false); renderFolders();
            }).catch(function () { setStatus(T.save_failed); });
        }
        function create(courseId) {
            flush().then(function () { return api('POST', URLS.notes, { title: T.new_note, course_id: courseId }); }).then(function (n) {
                notes.unshift(n); return select(n.id);
            }).then(function () { title.focus(); title.select(); }).catch(function (e) { alert(e.message === 'error' ? T.save_failed : e.message); });
        }
        function remove() {
            if (!cur || !confirm(T.confirm_delete)) { return; }
            var id = cur.id; auto.cancel(); dirty = false;
            api('DELETE', withId(URLS.note, id)).then(function () {
                notes = notes.filter(function (n) { return n.id !== id; }); cur = null;
                $('edit').hidden = true; $('ph').hidden = false; renderFolders();
            }).catch(function () { setStatus(T.save_failed); });
        }

        /* شريط التنسيق: يلفّ التحديد أو يضيف بادئة للأسطر */
        function wrap(a, b) {
            var s = body.selectionStart, e = body.selectionEnd, v = body.value, sel = v.slice(s, e) || '';
            body.value = v.slice(0, s) + a + sel + b + v.slice(e);
            body.focus(); body.setSelectionRange(s + a.length, s + a.length + sel.length); touch();
        }
        function prefix(fn) {
            var s = body.selectionStart, e = body.selectionEnd, v = body.value;
            var ls = v.lastIndexOf('\n', s - 1) + 1, le = v.indexOf('\n', e); if (le < 0) { le = v.length; }
            var lines = v.slice(ls, le).split('\n').map(fn).join('\n');
            body.value = v.slice(0, ls) + lines + v.slice(le); body.focus(); body.setSelectionRange(ls, ls + lines.length); touch();
        }
        var TB = [
            ['B', T.tb.bold, function () { wrap('**', '**'); }], ['I', T.tb.italic, function () { wrap('*', '*'); }],
            ['H', T.tb.heading, function () { prefix(function (l) { return '## ' + l.replace(/^#+\s*/, ''); }); }],
            ['•', T.tb.bullet, function () { prefix(function (l) { return '- ' + l.replace(/^[-*]\s+/, ''); }); }],
            ['1.', T.tb.number, function () { var i = 0; prefix(function (l) { i++; return i + '. ' + l.replace(/^\d+\.\s+/, ''); }); }],
            ['❝', T.tb.quote, function () { prefix(function (l) { return '> ' + l.replace(/^>\s?/, ''); }); }],
            ['{ }', T.tb.code, function () { wrap('`', '`'); }],
            ['—', T.tb.rule, function () { wrap('\n\n---\n\n', ''); }]
        ];
        TB.forEach(function (t) {
            var b = el('button', 'st-tb', t[0]); b.type = 'button'; b.title = t[1]; b.setAttribute('aria-label', t[1]);
            b.addEventListener('click', function () { if (!preview) { t[2](); } }); $('n-tb').appendChild(b);
        });

        /* معاينة آمنة: نُهرِّب كل HTML أولًا ثم نطبّق مجموعة صغيرة من قواعد الماركداون */
        function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
        function inline(s) { return s.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\*([^*\n]+)\*/g, '<em>$1</em>'); }
        function md(src) {
            var out = [], list = null, lines = esc(src).split('\n');
            function closeList() { if (list) { out.push('</' + list + '>'); list = null; } }
            lines.forEach(function (l) {
                var m;
                if ((m = /^(#{1,3})\s+(.*)$/.exec(l))) { closeList(); out.push('<h' + (m[1].length + 1) + '>' + inline(m[2]) + '</h' + (m[1].length + 1) + '>'); }
                else if ((m = /^[-*]\s+(.*)$/.exec(l))) { if (list !== 'ul') { closeList(); out.push('<ul>'); list = 'ul'; } out.push('<li>' + inline(m[1]) + '</li>'); }
                else if ((m = /^\d+\.\s+(.*)$/.exec(l))) { if (list !== 'ol') { closeList(); out.push('<ol>'); list = 'ol'; } out.push('<li>' + inline(m[1]) + '</li>'); }
                else if ((m = /^&gt;\s?(.*)$/.exec(l))) { closeList(); out.push('<blockquote>' + inline(m[1]) + '</blockquote>'); }
                else if (/^---+$/.test(l.trim())) { closeList(); out.push('<hr>'); }
                else if (l.trim() === '') { closeList(); out.push('<br>'); }
                else { closeList(); out.push('<p style="margin:.2em 0">' + inline(l) + '</p>'); }
            });
            closeList(); return out.join('');
        }
        function renderPreview() { prev.innerHTML = md(body.value); }
        function setPreview(on) {
            preview = on; $('n-preview').setAttribute('aria-pressed', on ? 'true' : 'false');
            $('n-preview').textContent = on ? T.edit : T.preview; body.hidden = on; prev.hidden = !on; if (on) { renderPreview(); }
        }

        title.addEventListener('input', touch); body.addEventListener('input', touch);
        $('n-save').addEventListener('click', function () { auto.cancel(); flush(); });
        $('n-del').addEventListener('click', remove);
        $('n-clear').addEventListener('click', function () { if (cur && confirm(T.confirm_clear_note)) { body.value = ''; touch(); body.focus(); } });
        $('n-preview').addEventListener('click', function () { setPreview(!preview); });
        D.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S') && !$('pane-notes').hidden && cur) { e.preventDefault(); auto.cancel(); flush(); }
        });
        window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });
        D.addEventListener('visibilitychange', function () { if (D.hidden) { auto.cancel(); flush(); } });

        renderFolders();
        return { flush: flush };
    })();

    /* ---------------- السبورة ---------------- */
    var Board = (function () {
        var W = 1600, H = 900, cv = $('b-canvas'), ctx = cv.getContext('2d');
        var pages = DATA.pages.slice(), cur = null, objs = [], tool = 'p', color = '#22d3ee', size = 6, alpha = 1, dash = 0, smooth = true;
        var drawing = null, imgs = {}, dirty = false, saving = false, again = false;
        var hist = [], redoStack = [], HIST_MAX = 60, MAX_OBJS = 3000;     // سجل التراجع: نسخ سطحية من المصفوفة (العناصر لا تُعدَّل في مكانها)
        var si = -1, drag = null, lastEdit = { key: '', at: 0 }, preHl = null;
        var MIN_IMG = 24, MAX_IMG = 4000;
        var COLORS = ['#ffffff', '#ef4444', '#22c55e', '#f59e0b', '#a78bfa', '#f472b6', '#000000'];
        var status = $('b-status');
        function setStatus(t) { status.textContent = t || ''; }
        function r1(n) { return Math.round(n * 10) / 10; }
        function clamp(n, a, b) { return Math.min(b, Math.max(a, n)); }
        function assign(o, props) {   // نسخة جديدة؛ القيم الافتراضية تُحذف كي يبقى العنصر صغيرًا
            var n = {}, k;
            for (k in o) { n[k] = o[k]; }
            for (k in props) { n[k] = props[k]; }
            if (!n.rot) { delete n.rot; }
            if (n.a === undefined || n.a >= 1) { delete n.a; }
            if (!n.fx) { delete n.fx; }
            if (!n.fy) { delete n.fy; }
            return n;
        }

        /* ---------- الرسم ---------- */
        function dashOf(o) { return o.d === 1 ? [o.w * 2.6, o.w * 2] : o.d === 2 ? [0.1, o.w * 2.2] : []; }
        function rad(o) { return (o.rot || 0) * Math.PI / 180; }
        function draw(o, c) {
            c.save(); c.lineCap = o.k === 'h' ? 'butt' : 'round'; c.lineJoin = o.k === 'h' ? 'bevel' : 'round'; c.lineWidth = o.w; c.strokeStyle = o.c || '#000';
            if (o.a && o.t !== 'e') { c.globalAlpha = o.a; }
            if (o.d) { c.setLineDash(dashOf(o)); }
            if (o.t === 'e') { c.globalCompositeOperation = 'destination-out'; }
            if (o.t === 'p' || o.t === 'e') {
                var P = o.p; c.beginPath();
                if (P.length === 1) { c.setLineDash([]); c.arc(P[0][0], P[0][1], o.w / 2, 0, 6.2832); c.fillStyle = o.t === 'e' ? '#000' : o.c; c.fill(); }
                else {
                    c.moveTo(P[0][0], P[0][1]);
                    if (o.sm && P.length > 2) {   // تنعيم: منحنيات تمرّ بمنتصف كل قطعتين
                        for (var i = 1; i < P.length - 1; i++) { c.quadraticCurveTo(P[i][0], P[i][1], (P[i][0] + P[i + 1][0]) / 2, (P[i][1] + P[i + 1][1]) / 2); }
                        c.lineTo(P[P.length - 1][0], P[P.length - 1][1]);
                    } else { for (var j = 1; j < P.length; j++) { c.lineTo(P[j][0], P[j][1]); } }
                    c.stroke();
                }
            } else if (o.t === 'l') { c.beginPath(); c.moveTo(o.x1, o.y1); c.lineTo(o.x2, o.y2); c.stroke(); }
            else if (o.t === 'r') { c.strokeRect(o.x, o.y, o.bw, o.bh); }
            else if (o.t === 'o') { c.beginPath(); c.ellipse(o.x + o.bw / 2, o.y + o.bh / 2, Math.abs(o.bw / 2), Math.abs(o.bh / 2), 0, 0, 6.2832); c.stroke(); }
            else if (o.t === 'i') {
                var im = imgs[o.src];
                if (!im) { im = new Image(); im.onload = function () { render(); }; im.src = o.src; imgs[o.src] = im; }
                if (im.complete && im.naturalWidth) {
                    c.translate(o.x + o.bw / 2, o.y + o.bh / 2); c.rotate(rad(o)); c.scale(o.fx ? -1 : 1, o.fy ? -1 : 1);
                    c.drawImage(im, -o.bw / 2, -o.bh / 2, o.bw, o.bh);
                }
            }
            c.restore();
        }
        function kScale() { var w = cv.getBoundingClientRect().width; return w > 0 ? W / w : 1; }   // وحدات اللوحة لكل بكسل شاشة
        function ctr(o) { return [o.x + o.bw / 2, o.y + o.bh / 2]; }
        function toWorld(o, lx, ly) { var c = ctr(o), a = rad(o), ca = Math.cos(a), sa = Math.sin(a); return [c[0] + lx * ca - ly * sa, c[1] + lx * sa + ly * ca]; }
        function toLocal(o, px, py) { var c = ctr(o), a = rad(o), ca = Math.cos(a), sa = Math.sin(a), dx = px - c[0], dy = py - c[1]; return [dx * ca + dy * sa, -dx * sa + dy * ca]; }
        var CORNERS = [[-1, -1], [1, -1], [1, 1], [-1, 1]];
        function drawSel(o) {
            var k = kScale(), hs = 6 * k;
            ctx.save(); ctx.translate(o.x + o.bw / 2, o.y + o.bh / 2); ctx.rotate(rad(o));
            ctx.lineWidth = 2 * k; ctx.strokeStyle = '#38bdf8'; ctx.setLineDash([8 * k, 5 * k]); ctx.strokeRect(-o.bw / 2, -o.bh / 2, o.bw, o.bh); ctx.setLineDash([]);
            ctx.beginPath(); ctx.moveTo(0, -o.bh / 2); ctx.lineTo(0, -o.bh / 2 - 28 * k); ctx.stroke();
            ctx.fillStyle = '#fff';
            CORNERS.forEach(function (q) { ctx.fillRect(q[0] * o.bw / 2 - hs, q[1] * o.bh / 2 - hs, hs * 2, hs * 2); ctx.strokeRect(q[0] * o.bw / 2 - hs, q[1] * o.bh / 2 - hs, hs * 2, hs * 2); });
            ctx.beginPath(); ctx.arc(0, -o.bh / 2 - 28 * k, 7 * k, 0, 6.2832); ctx.fill(); ctx.stroke();
            ctx.restore();
        }
        function render(noOverlay) {
            ctx.clearRect(0, 0, W, H);
            objs.forEach(function (o) { draw(o, ctx); });
            if (drawing) { draw(drawing, ctx); }
            if (!noOverlay && tool === 's' && objs[si] && objs[si].t === 'i') { drawSel(objs[si]); }
        }

        /* ---------- الحفظ التلقائي ---------- */
        function flush() {
            if (!cur || !dirty) { return Promise.resolve(); }
            if (saving) { again = true; return Promise.resolve(); }
            saving = true; dirty = false; setStatus(T.saving);
            var id = cur.id;
            return api('PUT', withId(URLS.page, id), { objects: objs }).then(function () {
                if (cur && cur.id === id) { setStatus(T.saved_at.replace(':time', timeNow())); }
            }).catch(function () { dirty = true; setStatus(T.save_failed); }).then(function () { saving = false; if (again) { again = false; return flush(); } });
        }
        var auto = debounce(flush, 1200);
        function changed() { dirty = true; setStatus('…'); auto(); }

        /* ---------- السجل (تراجع/إعادة) ---------- */
        function syncHist() { $('b-undo').disabled = !hist.length; $('b-redo').disabled = !redoStack.length; }
        function pushHist(snap) { hist.push(snap); if (hist.length > HIST_MAX) { hist.shift(); } redoStack = []; syncHist(); }
        function resetHist() { hist = []; redoStack = []; syncHist(); }
        function add(o) { if (objs.length >= MAX_OBJS) { return false; } pushHist(objs.slice()); objs.push(o); changed(); return true; }
        function fixSel() { if (!(objs[si] && objs[si].t === 'i')) { si = -1; } syncPanel(); }
        function undo() { if (!hist.length) { return; } redoStack.push(objs.slice()); objs = hist.pop(); fixSel(); syncHist(); changed(); render(); }
        function redo() { if (!redoStack.length) { return; } hist.push(objs.slice()); objs = redoStack.pop(); fixSel(); syncHist(); changed(); render(); }

        /* ---------- الصفحات ---------- */
        function renderPages() {
            var root = $('b-pages'); root.textContent = '';
            pages.forEach(function (p) {
                var b = el('button', 'st-ptab'); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', cur && cur.id === p.id ? 'true' : 'false');
                b.appendChild(el('span', '', p.name));
                if (cur && cur.id === p.id) {
                    var x = el('span', 'x', '×'); x.title = T.board.delete_page; x.setAttribute('role', 'button'); x.setAttribute('aria-label', T.board.delete_page);
                    x.addEventListener('click', function (e) { e.stopPropagation(); removePage(p.id); }); b.appendChild(x);
                }
                b.addEventListener('click', function () { open(p.id); }); root.appendChild(b);
            });
            var add2 = el('button', 'st-ptab', '+'); add2.type = 'button'; add2.title = T.board.add_page; add2.setAttribute('aria-label', T.board.add_page);
            add2.addEventListener('click', addPage); root.appendChild(add2);
        }
        function open(id) {
            return flush().then(function () { return api('GET', withId(URLS.page, id)); }).then(function (p) {
                auto.cancel(); cur = { id: p.id }; objs = Array.isArray(p.objects) ? p.objects : []; dirty = false; si = -1; drag = null; resetHist(); syncPanel(); setStatus(''); renderPages(); render();
            }).catch(function () { setStatus(T.save_failed); });
        }
        function addPage() {
            flush().then(function () { return api('POST', URLS.pages, {}); }).then(function (p) { pages.push({ id: p.id, name: p.name }); return open(p.id); })
                .catch(function (e) { alert(e.message === 'error' ? T.save_failed : e.message); });
        }
        function removePage(id) {
            if (!confirm(T.board.confirm_page)) { return; }
            auto.cancel(); dirty = false;
            api('DELETE', withId(URLS.page, id)).then(function () {
                pages = pages.filter(function (p) { return p.id !== id; }); cur = null;
                if (pages.length) { return open(pages[0].id); }
                return api('POST', URLS.pages, {}).then(function (p) { pages.push({ id: p.id, name: p.name }); return open(p.id); });
            }).catch(function () { setStatus(T.save_failed); });
        }

        /* ---------- التحديد ---------- */
        function pt(e) {
            var r = cv.getBoundingClientRect();
            return [r1((e.clientX - r.left) * (W / r.width)), r1((e.clientY - r.top) * (H / r.height))];
        }
        function hitHandle(o, p) {
            var k = kScale(), l = toLocal(o, p[0], p[1]), r = 13 * k, i;
            if (Math.hypot(l[0], l[1] + o.bh / 2 + 28 * k) <= r) { return { type: 'rot' }; }
            for (i = 0; i < 4; i++) { if (Math.hypot(l[0] - CORNERS[i][0] * o.bw / 2, l[1] - CORNERS[i][1] * o.bh / 2) <= r) { return { type: 'size', sx: CORNERS[i][0], sy: CORNERS[i][1] }; } }
            return null;
        }
        function hitImage(p) {
            for (var i = objs.length - 1; i >= 0; i--) {
                var o = objs[i]; if (o.t !== 'i') { continue; }
                var l = toLocal(o, p[0], p[1]);
                if (Math.abs(l[0]) <= o.bw / 2 && Math.abs(l[1]) <= o.bh / 2) { return i; }
            }
            return -1;
        }
        function setSel(i) { si = i; syncPanel(); }
        function sel() { return si >= 0 && objs[si] && objs[si].t === 'i' ? objs[si] : null; }
        function syncPanel() {
            var o = sel(), bar = $('b-imgbar');
            var wasHidden = bar.hidden; bar.hidden = tool !== 's'; if (wasHidden !== bar.hidden && typeof fit === 'function') { fit(); }   // تظهر مع أداة التحديد فقط وتبقى ثابتة، كي لا يتحرك اللوح تحت المؤشر عند النقر على صورة
            bar.querySelectorAll('input,button').forEach(function (x) { x.disabled = !o; });
            if (!o) { return; }
            var rot = Math.round(o.rot || 0), a = Math.round((o.a || 1) * 100);
            $('b-i-rot').value = rot; $('b-i-rotv').textContent = rot + '°';
            $('b-i-alpha').value = a; $('b-i-alphav').textContent = a + '%';
        }
        function edit(coalesceKey, fn) {   // تعديل على الصورة المحدّدة؛ المنزلقات تُدمج في خطوة تراجع واحدة
            var o = sel(); if (!o) { return; }
            var now = Date.now();
            if (!coalesceKey || coalesceKey !== lastEdit.key || now - lastEdit.at > 900) { pushHist(objs.slice()); }
            lastEdit = { key: coalesceKey || '', at: now };
            objs[si] = fn(o); changed(); render(); syncPanel();
        }
        function scaleBy(f) {
            edit(null, function (o) {
                var s = clamp(f, MIN_IMG / Math.min(o.bw, o.bh), MAX_IMG / Math.max(o.bw, o.bh)), c = ctr(o), nw = o.bw * s, nh = o.bh * s;
                return assign(o, { x: r1(c[0] - nw / 2), y: r1(c[1] - nh / 2), bw: r1(nw), bh: r1(nh) });
            });
        }
        function removeSel() { if (!sel()) { return; } pushHist(objs.slice()); objs.splice(si, 1); si = -1; changed(); render(); syncPanel(); }
        function duplicateSel() {
            var o = sel(); if (!o || objs.length >= MAX_OBJS) { return; }
            pushHist(objs.slice()); objs.push(assign(o, { x: r1(clamp(o.x + 30, -o.bw / 2, W - o.bw / 2)), y: r1(clamp(o.y + 30, -o.bh / 2, H - o.bh / 2)) }));
            si = objs.length - 1; changed(); render(); syncPanel();
        }
        function reorderSel(toFront) {
            var o = sel(); if (!o) { return; }
            if ((toFront && si === objs.length - 1) || (!toFront && si === 0)) { return; }
            pushHist(objs.slice()); objs.splice(si, 1); if (toFront) { objs.push(o); si = objs.length - 1; } else { objs.unshift(o); si = 0; }
            changed(); render();
        }

        function startDrag(mode, p, h) { drag = { mode: mode, p0: p, h: h, base: objs[si], snap: objs.slice(), moved: false }; }
        function dragMove(p, e) {
            var b = drag.base, n = b;
            if (drag.mode === 'move') {
                n = assign(b, { x: r1(clamp(b.x + p[0] - drag.p0[0], -b.bw / 2, W - b.bw / 2)), y: r1(clamp(b.y + p[1] - drag.p0[1], -b.bh / 2, H - b.bh / 2)) });
            } else if (drag.mode === 'rot') {
                var c = ctr(b), ang = Math.atan2(p[1] - c[1], p[0] - c[0]) * 180 / Math.PI + 90;
                while (ang > 180) { ang -= 360; } while (ang <= -180) { ang += 360; }
                if (e.shiftKey) { ang = Math.round(ang / 15) * 15; }
                n = assign(b, { rot: r1(ang) });
            } else {
                var sx = drag.h.sx, sy = drag.h.sy, a = rad(b), ca = Math.cos(a), sa = Math.sin(a), anchor = toWorld(b, -sx * b.bw / 2, -sy * b.bh / 2);
                var dx = p[0] - anchor[0], dy = p[1] - anchor[1], lx = dx * ca + dy * sa, ly = -dx * sa + dy * ca;
                var nw = Math.max(MIN_IMG, lx * sx), nh = Math.max(MIN_IMG, ly * sy);
                if ($('b-i-lock').checked !== !!e.shiftKey) {   // Shift يعكس قفل النسبة مؤقتًا
                    var s = clamp(Math.max(nw / b.bw, nh / b.bh), MIN_IMG / Math.min(b.bw, b.bh), MAX_IMG / Math.max(b.bw, b.bh)); nw = b.bw * s; nh = b.bh * s;
                } else { nw = Math.min(nw, MAX_IMG); nh = Math.min(nh, MAX_IMG); }
                var cx = anchor[0] + sx * nw / 2 * ca - sy * nh / 2 * sa, cy = anchor[1] + sx * nw / 2 * sa + sy * nh / 2 * ca;
                n = assign(b, { x: r1(cx - nw / 2), y: r1(cy - nh / 2), bw: r1(nw), bh: r1(nh) });
            }
            objs[si] = n; drag.moved = true; syncPanel(); render();
        }
        function endDrag() {
            if (!drag) { return; }
            var d = drag; drag = null;
            if (d.moved) { pushHist(d.snap); changed(); }
            render();
        }
        function hoverCursor(p) {
            var o = sel(), h = o && hitHandle(o, p);
            cv.style.cursor = h ? (h.type === 'rot' ? 'grab' : (h.sx * h.sy > 0 ? 'nwse-resize' : 'nesw-resize')) : hitImage(p) >= 0 ? 'move' : 'default';
        }

        /* ---------- الرسم بالمؤشر (ماوس/لمس/قلم) ---------- */
        cv.addEventListener('pointerdown', function (e) {
            if (!cur || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
            e.preventDefault(); cv.setPointerCapture(e.pointerId);
            var p = pt(e);
            if (tool === 's') {
                var o = sel(), h = o && hitHandle(o, p);
                if (h) { startDrag(h.type, p, h); return; }
                var i = hitImage(p); setSel(i);
                if (i >= 0) { startDrag('move', p); }
                render(); return;
            }
            var w = tool === 'e' ? Math.max(size * 2, 10) : tool === 'h' ? clamp(size * 2.5, 14, 120) : size, base = { c: color, w: w };
            if (alpha < 1 && tool !== 'e') { base.a = alpha; }
            if (tool === 'p' || tool === 'h' || tool === 'e') {
                drawing = { t: tool === 'e' ? 'e' : 'p', c: tool === 'e' ? '#000000' : color, w: w, p: [p] };
                if (base.a) { drawing.a = base.a; }
                if (tool !== 'e') { if (dash) { drawing.d = dash; } if (smooth) { drawing.sm = 1; } if (tool === 'h') { drawing.k = 'h'; } }
            } else if (tool === 'l') { drawing = { t: 'l', c: color, w: w, x1: p[0], y1: p[1], x2: p[0], y2: p[1] }; }
            else { drawing = { t: tool, c: color, w: w, x: p[0], y: p[1], bw: 0, bh: 0 }; }
            if (drawing.t !== 'p' && drawing.t !== 'e') { if (base.a) { drawing.a = base.a; } if (dash) { drawing.d = dash; } }
            render();
        });
        cv.addEventListener('pointermove', function (e) {
            var p = pt(e);
            if (drag) { dragMove(p, e); return; }
            if (!drawing) { if (tool === 's') { hoverCursor(p); } return; }
            if (drawing.p) { var l = drawing.p[drawing.p.length - 1]; if (Math.abs(l[0] - p[0]) + Math.abs(l[1] - p[1]) < 1.5) { return; } drawing.p.push(p); }
            else if (drawing.t === 'l') { drawing.x2 = p[0]; drawing.y2 = p[1]; }
            else { drawing.bw = r1(p[0] - drawing.x); drawing.bh = r1(p[1] - drawing.y); }
            render();
        });
        function end() {
            if (drag) { endDrag(); return; }
            if (!drawing) { return; }
            var o = drawing; drawing = null;
            var keep = o.p ? o.p.length > 0 : o.t === 'l' ? (o.x1 !== o.x2 || o.y1 !== o.y2) : (Math.abs(o.bw) > 2 || Math.abs(o.bh) > 2);
            if (keep) { add(o); }
            render();
        }
        cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);

        /* ---------- الأدوات ---------- */
        function setAlpha(v) { alpha = clamp(v, 0.05, 1); $('b-alpha').value = Math.round(alpha * 100); $('b-alphav').textContent = Math.round(alpha * 100) + '%'; }
        D.querySelectorAll('[data-tool]').forEach(function (b) {
            b.addEventListener('click', function () {
                var next = b.getAttribute('data-tool');
                if (next === 'h' && tool !== 'h') { preHl = alpha; if (alpha > 0.4) { setAlpha(0.4); } }       // التظليل شبه شفاف افتراضيًا
                if (tool === 'h' && next !== 'h' && preHl !== null) { setAlpha(preHl); preHl = null; }
                tool = next; if (tool !== 's') { si = -1; drag = null; }
                D.querySelectorAll('[data-tool]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                cv.style.cursor = tool === 'e' ? 'cell' : tool === 's' ? 'default' : 'crosshair';
                syncPanel(); render();
            });
        });
        function setColor(c) {
            color = c; $('b-color').value = c;
            D.querySelectorAll('#b-sw .st-sw').forEach(function (s) { s.setAttribute('aria-pressed', s.getAttribute('data-c') === c ? 'true' : 'false'); });
            if (tool === 'e' || tool === 's') { D.querySelector('[data-tool="p"]').click(); }
        }
        COLORS.forEach(function (c) {
            var s = el('button', 'st-sw'); s.type = 'button'; s.style.background = c; s.setAttribute('data-c', c); s.setAttribute('aria-label', c); s.setAttribute('aria-pressed', 'false');
            s.addEventListener('click', function () { setColor(c); }); $('b-sw').appendChild(s);
        });
        $('b-color').addEventListener('input', function (e) { setColor(e.target.value.toLowerCase()); });
        $('b-size').addEventListener('input', function (e) { size = parseInt(e.target.value, 10) || 6; $('b-sizev').textContent = String(size); });
        $('b-alpha').addEventListener('input', function (e) { setAlpha((parseInt(e.target.value, 10) || 100) / 100); });
        $('b-dash').addEventListener('change', function (e) { dash = [0, 1, 2].indexOf(parseInt(e.target.value, 10)) >= 0 ? parseInt(e.target.value, 10) : 0; });
        $('b-smooth').addEventListener('change', function (e) { smooth = !!e.target.checked; });
        $('b-undo').addEventListener('click', undo);
        $('b-redo').addEventListener('click', redo);
        $('b-clear').addEventListener('click', function () { if (objs.length && confirm(T.board.confirm_clear)) { pushHist(objs.slice()); objs = []; si = -1; changed(); render(); syncPanel(); } });
        $('b-dl').addEventListener('click', function () {
            var c = D.createElement('canvas'); c.width = W; c.height = H; var x = c.getContext('2d');
            x.fillStyle = getComputedStyle(cv.parentNode).backgroundColor || '#0b1b25'; x.fillRect(0, 0, W, H);
            render(true); x.drawImage(cv, 0, 0); render();   // التصدير بلا مقابض التحديد
            c.toBlob(function (b) { if (!b) { return; } var a = D.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'whiteboard.png'; D.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000); }, 'image/png');
        });

        /* لوحة الصورة المحدّدة */
        $('b-i-rot').addEventListener('input', function (e) { var v = clamp(parseInt(e.target.value, 10) || 0, -180, 180); edit('rot', function (o) { return assign(o, { rot: v }); }); });
        $('b-i-alpha').addEventListener('input', function (e) { var v = clamp(parseInt(e.target.value, 10) || 100, 5, 100) / 100; edit('alpha', function (o) { return assign(o, { a: v }); }); });
        $('b-i-up').addEventListener('click', function () { scaleBy(1.1); });
        $('b-i-down').addEventListener('click', function () { scaleBy(1 / 1.1); });
        $('b-i-fx').addEventListener('click', function () { edit(null, function (o) { return assign(o, { fx: o.fx ? 0 : 1 }); }); });
        $('b-i-fy').addEventListener('click', function () { edit(null, function (o) { return assign(o, { fy: o.fy ? 0 : 1 }); }); });
        $('b-i-front').addEventListener('click', function () { reorderSel(true); });
        $('b-i-back').addEventListener('click', function () { reorderSel(false); });
        $('b-i-dup').addEventListener('click', duplicateSel);
        $('b-i-del').addEventListener('click', removeSel);
        $('b-i-reset').addEventListener('click', function () { edit(null, function (o) { return assign(o, { rot: 0, a: 1, fx: 0, fy: 0 }); }); });

        /* اختصارات لوحة المفاتيح (فقط عند ظهور السبورة وعدم الكتابة في حقل) */
        D.addEventListener('keydown', function (e) {
            if ($('pane-board').hidden || e.altKey) { return; }
            var t = e.target, tag = t && t.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) { return; }
            var mod = e.ctrlKey || e.metaKey, k = e.key;
            if (mod && (k === 'z' || k === 'Z')) { e.preventDefault(); if (e.shiftKey) { redo(); } else { undo(); } return; }
            if (mod && (k === 'y' || k === 'Y')) { e.preventDefault(); redo(); return; }
            if (k === 'Escape' && full && !sel()) { exitFull(); return; }   // الوضع الاحتياطي (المتصفح يعالج Esc بنفسه في الوضع الأصلي)
            if (!sel() || tool !== 's') { return; }
            if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); removeSel(); }
            else if (k === 'Escape') { setSel(-1); render(); }
            else if (mod && (k === 'd' || k === 'D')) { e.preventDefault(); duplicateSel(); }
            else if (k.indexOf('Arrow') === 0) {
                e.preventDefault();
                var st = e.shiftKey ? 10 : 1, dx = k === 'ArrowLeft' ? -st : k === 'ArrowRight' ? st : 0, dy = k === 'ArrowUp' ? -st : k === 'ArrowDown' ? st : 0;
                edit('nudge', function (o) { return assign(o, { x: r1(clamp(o.x + dx, -o.bw / 2, W - o.bw / 2)), y: r1(clamp(o.y + dy, -o.bh / 2, H - o.bh / 2)) }); });
            }
        });

        /* ---------- حجم العرض وملء الشاشة ---------- */
        var card = $('b-card'), wrap = $('b-wrap'), full = false, zoom = 100, ZKEY = 'madar.board.zoom';
        try { var z = parseInt(localStorage.getItem(ZKEY), 10); if (z >= 50 && z <= 100) { zoom = z; } } catch (e) {}
        function fit() {
            if (!full) { wrap.style.width = zoom + '%'; return; }
            var used = 0;   // ارتفاع ما فوق السبورة داخل البطاقة (الأدوات إن كانت ظاهرة)
            Array.prototype.forEach.call(card.children, function (c) { if (c !== wrap && c.offsetParent !== null) { used += c.offsetHeight; } });
            var w = Math.max(240, Math.min(card.clientWidth, (card.clientHeight - used) * 16 / 9));
            wrap.style.width = Math.floor(w) + 'px';
        }
        function setFull(on) {
            full = on; card.classList.toggle('st-full', on); $('b-fsctl').hidden = !on;
            if (!on) { card.classList.remove('st-fallback', 'st-notools'); D.documentElement.style.overflow = ''; $('b-fs-tools').setAttribute('aria-pressed', 'false'); }
            $('b-fs').setAttribute('aria-pressed', on ? 'true' : 'false');
            fit(); render();
        }
        function enterFull() {
            var req = card.requestFullscreen || card.webkitRequestFullscreen;
            function fallback() { card.classList.add('st-fallback'); D.documentElement.style.overflow = 'hidden'; setFull(true); }
            if (!req) { fallback(); return; }
            try { var r = req.call(card); if (r && r.catch) { r.catch(fallback); } } catch (e) { fallback(); }
        }
        function exitFull() {
            if (D.fullscreenElement || D.webkitFullscreenElement) { (D.exitFullscreen || D.webkitExitFullscreen).call(D); }
            else if (full) { setFull(false); }
        }
        function onFsChange() { var el2 = D.fullscreenElement || D.webkitFullscreenElement; if (el2 === card) { setFull(true); } else if (full && !card.classList.contains('st-fallback')) { setFull(false); } }
        D.addEventListener('fullscreenchange', onFsChange); D.addEventListener('webkitfullscreenchange', onFsChange);
        $('b-fs').addEventListener('click', function () { if (full) { exitFull(); } else { enterFull(); } });
        $('b-fs-exit').addEventListener('click', exitFull);
        $('b-fs-tools').addEventListener('click', function () {
            var hide = !card.classList.contains('st-notools'); card.classList.toggle('st-notools', hide);
            $('b-fs-tools').setAttribute('aria-pressed', hide ? 'true' : 'false'); fit(); render();
        });
        $('b-zoom').value = zoom; $('b-zoomv').textContent = zoom + '%';
        $('b-zoom').addEventListener('input', function (e) {
            zoom = clamp(parseInt(e.target.value, 10) || 100, 50, 100); $('b-zoomv').textContent = zoom + '%';
            try { localStorage.setItem(ZKEY, String(zoom)); } catch (er) {}
            fit(); render();
        });
        fit();

        /* رفع صورة: نصغّرها في المتصفح قبل التخزين (الخادم يرفض الكبير) */
        $('b-img').addEventListener('click', function () { if (cur) { $('b-file').click(); } });
        $('b-file').addEventListener('change', function (e) {
            var f = e.target.files && e.target.files[0]; e.target.value = '';
            if (!f || !/^image\/(png|jpeg|webp)$/.test(f.type) || f.size > 8 * 1024 * 1024) { if (f) { alert(T.board.image_bad); } return; }
            var url = URL.createObjectURL(f), im = new Image();
            im.onerror = function () { URL.revokeObjectURL(url); alert(T.board.image_bad); };
            im.onload = function () {
                URL.revokeObjectURL(url);
                var max = 720, out = null, q = 0.82;
                while (max >= 160) {
                    var k = Math.min(1, max / Math.max(im.naturalWidth, im.naturalHeight)), w = Math.max(1, Math.round(im.naturalWidth * k)), h = Math.max(1, Math.round(im.naturalHeight * k));
                    var c = D.createElement('canvas'); c.width = w; c.height = h; var x = c.getContext('2d'); x.fillStyle = '#ffffff'; x.fillRect(0, 0, w, h); x.drawImage(im, 0, 0, w, h);
                    var s = c.toDataURL('image/jpeg', q);
                    if (s.length <= 850000) { out = { src: s, w: w, h: h }; break; }
                    max = Math.round(max * 0.75); q = 0.75;
                }
                if (!out) { alert(T.board.image_bad); return; }
                var dw = Math.min(out.w, 640), dh = Math.round(out.h * dw / out.w);
                if (add({ t: 'i', src: out.src, x: Math.round((W - dw) / 2), y: Math.round((H - dh) / 2), bw: dw, bh: dh })) {
                    D.querySelector('[data-tool="s"]').click(); setSel(objs.length - 1);   // الانتقال للتحديد فورًا ليعدّلها المستخدم
                }
                render();
            };
            im.src = url;
        });

        window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });
        D.addEventListener('visibilitychange', function () { if (D.hidden) { auto.cancel(); flush(); } });
        window.addEventListener('resize', function () { fit(); render(); });

        function init() {
            setColor(color); syncHist();
            if (pages.length) { renderPages(); open(pages[0].id); return; }
            api('POST', URLS.pages, {}).then(function (p) { pages.push({ id: p.id, name: p.name }); return open(p.id); }).catch(function () { setStatus(T.save_failed); });
        }
        return { init: init };
    })();

    if (location.hash === '#board') { showTab('board'); }
})();
