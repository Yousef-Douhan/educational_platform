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
        var pages = DATA.pages.slice(), cur = null, objs = [], tool = 'p', color = '#22d3ee', size = 6, drawing = null, imgs = {}, dirty = false, saving = false, again = false;
        var COLORS = ['#ffffff', '#ef4444', '#22c55e', '#f59e0b', '#a78bfa', '#f472b6', '#000000'];
        var status = $('b-status');
        function setStatus(t) { status.textContent = t || ''; }

        function draw(o, c) {
            c.save(); c.lineCap = 'round'; c.lineJoin = 'round'; c.lineWidth = o.w; c.strokeStyle = o.c || '#000';
            if (o.t === 'e') { c.globalCompositeOperation = 'destination-out'; }
            if (o.t === 'p' || o.t === 'e') {
                c.beginPath();
                if (o.p.length === 1) { c.arc(o.p[0][0], o.p[0][1], o.w / 2, 0, 6.2832); c.fillStyle = o.t === 'e' ? '#000' : o.c; c.fill(); }
                else { c.moveTo(o.p[0][0], o.p[0][1]); for (var i = 1; i < o.p.length; i++) { c.lineTo(o.p[i][0], o.p[i][1]); } c.stroke(); }
            } else if (o.t === 'l') { c.beginPath(); c.moveTo(o.x1, o.y1); c.lineTo(o.x2, o.y2); c.stroke(); }
            else if (o.t === 'r') { c.strokeRect(o.x, o.y, o.bw, o.bh); }
            else if (o.t === 'o') { c.beginPath(); c.ellipse(o.x + o.bw / 2, o.y + o.bh / 2, Math.abs(o.bw / 2), Math.abs(o.bh / 2), 0, 0, 6.2832); c.stroke(); }
            else if (o.t === 'i') {
                var im = imgs[o.src];
                if (!im) { im = new Image(); im.onload = render; im.src = o.src; imgs[o.src] = im; }
                if (im.complete && im.naturalWidth) { c.drawImage(im, o.x, o.y, o.bw, o.bh); }
            }
            c.restore();
        }
        function render() {
            ctx.clearRect(0, 0, W, H);
            objs.forEach(function (o) { draw(o, ctx); });
            if (drawing) { draw(drawing, ctx); }
        }

        /* الحفظ التلقائي */
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

        /* الصفحات */
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
            var add = el('button', 'st-ptab', '+'); add.type = 'button'; add.title = T.board.add_page; add.setAttribute('aria-label', T.board.add_page);
            add.addEventListener('click', addPage); root.appendChild(add);
        }
        function open(id) {
            return flush().then(function () { return api('GET', withId(URLS.page, id)); }).then(function (p) {
                auto.cancel(); cur = { id: p.id }; objs = Array.isArray(p.objects) ? p.objects : []; dirty = false; setStatus(''); renderPages(); render();
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

        /* الرسم بالمؤشر (ماوس/لمس/قلم) */
        function pt(e) {
            var r = cv.getBoundingClientRect();
            return [Math.round((e.clientX - r.left) * (W / r.width) * 10) / 10, Math.round((e.clientY - r.top) * (H / r.height) * 10) / 10];
        }
        cv.addEventListener('pointerdown', function (e) {
            if (!cur || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
            e.preventDefault(); cv.setPointerCapture(e.pointerId);
            var p = pt(e), w = tool === 'e' ? Math.max(size * 2, 10) : size;
            if (tool === 'p' || tool === 'e') { drawing = { t: tool, c: tool === 'e' ? '#000000' : color, w: w, p: [p] }; }
            else if (tool === 'l') { drawing = { t: 'l', c: color, w: w, x1: p[0], y1: p[1], x2: p[0], y2: p[1] }; }
            else { drawing = { t: tool, c: color, w: w, x: p[0], y: p[1], bw: 0, bh: 0 }; }
            render();
        });
        cv.addEventListener('pointermove', function (e) {
            if (!drawing) { return; }
            var p = pt(e);
            if (drawing.p) { var l = drawing.p[drawing.p.length - 1]; if (Math.abs(l[0] - p[0]) + Math.abs(l[1] - p[1]) < 1.5) { return; } drawing.p.push(p); }
            else if (drawing.t === 'l') { drawing.x2 = p[0]; drawing.y2 = p[1]; }
            else { drawing.bw = Math.round((p[0] - drawing.x) * 10) / 10; drawing.bh = Math.round((p[1] - drawing.y) * 10) / 10; }
            render();
        });
        function end() {
            if (!drawing) { return; }
            var o = drawing; drawing = null;
            var keep = o.p ? o.p.length > 0 : o.t === 'l' ? (o.x1 !== o.x2 || o.y1 !== o.y2) : (Math.abs(o.bw) > 2 || Math.abs(o.bh) > 2);
            if (keep && objs.length < 3000) { objs.push(o); changed(); }
            render();
        }
        cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);

        /* الأدوات */
        D.querySelectorAll('[data-tool]').forEach(function (b) {
            b.addEventListener('click', function () {
                tool = b.getAttribute('data-tool');
                D.querySelectorAll('[data-tool]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                cv.style.cursor = tool === 'e' ? 'cell' : 'crosshair';
            });
        });
        function setColor(c) {
            color = c; $('b-color').value = c;
            D.querySelectorAll('#b-sw .st-sw').forEach(function (s) { s.setAttribute('aria-pressed', s.getAttribute('data-c') === c ? 'true' : 'false'); });
            if (tool === 'e') { D.querySelector('[data-tool="p"]').click(); }
        }
        COLORS.forEach(function (c) {
            var s = el('button', 'st-sw'); s.type = 'button'; s.style.background = c; s.setAttribute('data-c', c); s.setAttribute('aria-label', c); s.setAttribute('aria-pressed', 'false');
            s.addEventListener('click', function () { setColor(c); }); $('b-sw').appendChild(s);
        });
        $('b-color').addEventListener('input', function (e) { setColor(e.target.value.toLowerCase()); });
        $('b-size').addEventListener('input', function (e) { size = parseInt(e.target.value, 10) || 6; $('b-sizev').textContent = String(size); });
        $('b-undo').addEventListener('click', function () { if (objs.length) { objs.pop(); changed(); render(); } });
        $('b-clear').addEventListener('click', function () { if (objs.length && confirm(T.board.confirm_clear)) { objs = []; changed(); render(); } });
        $('b-dl').addEventListener('click', function () {
            var c = D.createElement('canvas'); c.width = W; c.height = H; var x = c.getContext('2d');
            x.fillStyle = getComputedStyle(cv.parentNode).backgroundColor || '#0b1b25'; x.fillRect(0, 0, W, H); x.drawImage(cv, 0, 0);
            c.toBlob(function (b) { if (!b) { return; } var a = D.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'whiteboard.png'; D.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000); }, 'image/png');
        });

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
                objs.push({ t: 'i', src: out.src, x: Math.round((W - dw) / 2), y: Math.round((H - dh) / 2), bw: dw, bh: dh }); changed(); render();
            };
            im.src = url;
        });

        window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });
        D.addEventListener('visibilitychange', function () { if (D.hidden) { auto.cancel(); flush(); } });

        function init() {
            setColor(color);
            if (pages.length) { renderPages(); open(pages[0].id); return; }
            api('POST', URLS.pages, {}).then(function (p) { pages.push({ id: p.id, name: p.name }); return open(p.id); }).catch(function () { setStatus(T.save_failed); });
        }
        return { init: init };
    })();

    if (location.hash === '#board') { showTab('board'); }
})();
