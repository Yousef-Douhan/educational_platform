<script>
    /* يُنفَّذ قبل الرسم لتفادي وميض الوضع الفاتح */
    (function () {
        try {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>
