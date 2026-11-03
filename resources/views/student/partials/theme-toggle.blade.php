<script>
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-theme-toggle]');
        if (!b) return;
        var dark = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (err) {}
    });
</script>
