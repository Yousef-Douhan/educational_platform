(function () {
    var h = document.getElementById('mh');
    function onScroll() { h.classList.toggle('scrolled', window.scrollY > 8); }
    onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
    var dds = document.querySelectorAll('[data-mh-dd]');
    dds.forEach(function (d) { d.addEventListener('toggle', function () { if (d.open) { dds.forEach(function (o) { if (o !== d) { o.open = false; } }); } }); });
    document.addEventListener('click', function (e) { dds.forEach(function (d) { if (d.open && !d.contains(e.target)) { d.open = false; } }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { dds.forEach(function (d) { d.open = false; }); } });
})();
