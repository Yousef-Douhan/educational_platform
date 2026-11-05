(function () {
    var CFG = JSON.parse(document.getElementById('sc-config').textContent);
    var L = CFG.labels, ITEMS = CFG.items, CSRF = CFG.csrf;
    var URL_BASE = CFG.url, HH = 56, COLORS = ['#0f8f7a', '#0369a1', '#7c3aed', '#db2777', '#ea580c', '#ca8a04', '#16a34a', '#475569'];
    var $ = function (id) { return document.getElementById(id); };
    var grid = $('sc-grid'), editing = null, color = COLORS[0], busy = false;

    function toMin(t) { var p = t.split(':'); return +p[0] * 60 + +p[1]; }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(m) { return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }

    function api(method, url, body) {
        return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error('x'); e.data = j; throw e; } return j; }); });
    }

    function visibleDays() {
        var days = [0, 1, 2, 3, 4, 5];
        if (ITEMS.some(function (i) { return i.day === 6; })) { days.push(6); }
        return days;
    }

    /* تعارض: نفس اليوم وتقاطع الفترات */
    function conflicts() {
        var bad = {};
        ITEMS.forEach(function (a, i) {
            ITEMS.forEach(function (b, j) {
                if (i < j && a.day === b.day && toMin(a.start) < toMin(b.end) && toMin(b.start) < toMin(a.end)) { bad[a.id] = bad[b.id] = true; }
            });
        });
        return bad;
    }

    /* توزيع العناصر المتقاطعة جنبًا إلى جنب في أعمدة داخل اليوم */
    function lanes(list) {
        var sorted = list.slice().sort(function (a, b) { return toMin(a.start) - toMin(b.start) || toMin(a.end) - toMin(b.end); });
        var out = [], cluster = [], clusterEnd = -1;
        function flush() {
            var ends = [];
            cluster.forEach(function (it) {
                var lane = 0;
                while (ends[lane] !== undefined && ends[lane] > toMin(it.start)) { lane++; }
                ends[lane] = toMin(it.end); it._lane = lane;
            });
            cluster.forEach(function (it) { it._lanes = ends.length; out.push(it); });
            cluster = []; clusterEnd = -1;
        }
        sorted.forEach(function (it) {
            if (cluster.length && toMin(it.start) >= clusterEnd) { flush(); }
            cluster.push(it); clusterEnd = Math.max(clusterEnd, toMin(it.end));
        });
        if (cluster.length) { flush(); }
        return out;
    }

    function render() {
        var days = visibleDays(), bad = conflicts();
        var lo = 8 * 60, hi = 17 * 60;
        ITEMS.forEach(function (i) { lo = Math.min(lo, Math.floor(toMin(i.start) / 60) * 60); hi = Math.max(hi, Math.ceil(toMin(i.end) / 60) * 60); });
        var rows = (hi - lo) / 60;
        grid.textContent = '';
        grid.style.gridTemplateColumns = '86px repeat(' + days.length + ', 1fr)';
        grid.style.setProperty('--sc-hh', HH + 'px');

        var h = document.createElement('div'); h.className = 'sc-hd'; h.textContent = L.time; grid.appendChild(h);
        days.forEach(function (d) { var e = document.createElement('div'); e.className = 'sc-hd'; e.textContent = L.days[d]; grid.appendChild(e); });

        var timeCol = document.createElement('div');
        timeCol.style.display = 'grid'; timeCol.style.gridTemplateRows = 'repeat(' + rows + ', ' + HH + 'px)';
        for (var r = 0; r < rows; r++) { var t = document.createElement('div'); t.className = 'sc-time'; t.textContent = fmt(lo + r * 60) + ' - ' + fmt(lo + r * 60 + 60); timeCol.appendChild(t); }
        grid.appendChild(timeCol);

        days.forEach(function (d) {
            var col = document.createElement('div'); col.className = 'sc-col'; col.style.height = (rows * HH) + 'px';
            col.addEventListener('click', function (ev) {
                if (ev.target !== col) { return; }
                var y = ev.offsetY, start = lo + Math.floor(y / HH) * 60;
                openForm(null, { day: d, start: fmt(start), end: fmt(Math.min(start + 60, 23 * 60 + 59)) });
            });
            lanes(ITEMS.filter(function (i) { return i.day === d; })).forEach(function (it) {
                var b = document.createElement('button'); b.type = 'button'; b.className = 'sc-item' + (bad[it.id] ? ' bad' : '');
                var top = (toMin(it.start) - lo) / 60 * HH, hgt = Math.max((toMin(it.end) - toMin(it.start)) / 60 * HH - 2, 24), w = 100 / it._lanes;
                b.style.cssText = 'top:' + top + 'px;height:' + hgt + 'px;width:calc(' + w + '% - 4px);inset-inline-start:calc(' + (it._lane * w) + '% + 2px);background:' + it.color;
                var t1 = document.createElement('b'); t1.textContent = it.title; b.appendChild(t1);
                var t2 = document.createElement('span'); t2.textContent = it.start + ' - ' + it.end + ' · ' + (L.kinds[it.kind] || ''); b.appendChild(t2);
                if (it.room) { var t3 = document.createElement('span'); t3.textContent = it.room; b.appendChild(t3); }
                if (bad[it.id]) { var g = document.createElement('span'); g.className = 'sc-badge'; g.textContent = L.conflictBadge; b.appendChild(g); }
                b.addEventListener('click', function (ev) { ev.stopPropagation(); openForm(it); });
                col.appendChild(b);
            });
            grid.appendChild(col);
        });

        var st = $('sc-status'), has = Object.keys(bad).length > 0;
        st.className = 'sc-note ' + (has ? 'bad' : 'ok');
        st.textContent = has ? L.conflictOne : L.noConflict;
        $('sc-empty').hidden = ITEMS.length > 0;
        $('sc-clear').disabled = ITEMS.length === 0;
    }

    function swatches() {
        var box = $('sc-sw'); box.textContent = '';
        COLORS.forEach(function (c) {
            var b = document.createElement('button'); b.type = 'button'; b.style.background = c; b.setAttribute('aria-label', c); b.setAttribute('aria-pressed', c === color ? 'true' : 'false');
            b.addEventListener('click', function () { color = c; swatches(); });
            box.appendChild(b);
        });
    }

    function showErr(msg) { var e = $('sc-err'); e.hidden = !msg; e.textContent = msg || ''; }

    function openForm(item, preset) {
        editing = item; showErr('');
        var p = item || preset || { day: 0, start: '08:00', end: '09:00' };
        $('sc-mt').textContent = item ? L.edit : L.new;
        $('sc-title').value = item ? item.title : '';
        $('sc-kind').value = item ? item.kind : 'lecture';
        $('sc-day').value = p.day; $('sc-start').value = p.start; $('sc-end').value = p.end;
        $('sc-room').value = item && item.room ? item.room : '';
        color = item ? item.color : COLORS[0]; swatches();
        $('sc-del').hidden = !item;
        $('sc-modal').classList.add('open'); $('sc-title').focus();
    }
    function closeForm() { $('sc-modal').classList.remove('open'); editing = null; }

    $('sc-add').addEventListener('click', function () { openForm(null); });
    $('sc-cancel').addEventListener('click', closeForm);
    $('sc-modal').addEventListener('mousedown', function (e) { if (e.target === this) { closeForm(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeForm(); } });
    $('sc-print').addEventListener('click', function () { window.print(); });

    $('sc-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) { return; }
        var body = { title: $('sc-title').value.trim(), kind: $('sc-kind').value, day: +$('sc-day').value, start: $('sc-start').value, end: $('sc-end').value, room: $('sc-room').value.trim() || null, color: color };
        if (!body.title || !body.start || !body.end) { showErr(L.failed); return; }
        if (body.end <= body.start) { showErr(L.endAfterStart); return; }
        busy = true;
        var req = editing ? api('PUT', URL_BASE + '/' + editing.id, body) : api('POST', URL_BASE, body);
        req.then(function (saved) {
            if (editing) { ITEMS = ITEMS.map(function (i) { return i.id === saved.id ? saved : i; }); } else { ITEMS.push(saved); }
            closeForm(); render();
        }).catch(function (err) {
            var m = err && err.data && err.data.errors ? Object.keys(err.data.errors).map(function (k) { return err.data.errors[k][0]; }).join(' ') : L.failed;
            showErr(m);
        }).then(function () { busy = false; });
    });

    $('sc-del').addEventListener('click', function () {
        if (!editing || !confirm(L.confirmDelete)) { return; }
        var id = editing.id;
        api('DELETE', URL_BASE + '/' + id).then(function () { ITEMS = ITEMS.filter(function (i) { return i.id !== id; }); closeForm(); render(); })
            .catch(function () { showErr(L.failed); });
    });

    $('sc-clear').addEventListener('click', function () {
        if (!ITEMS.length || !confirm(L.confirmClear)) { return; }
        api('DELETE', URL_BASE).then(function () { ITEMS = []; render(); }).catch(function () { alert(L.failed); });
    });

    render();
})();
