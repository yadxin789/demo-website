{{--
    主题声明：在任何样式加载前让浏览器知道页面同时支持浅色与深色，
    并把 Bootstrap 5.3 的 data-bs-theme 跟随系统偏好（含运行时切换）。
    离线可用，不引入任何脚本文件。
--}}
<meta name="color-scheme" content="light dark">
<script>
    (() => {
        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const apply = () => {
            document.documentElement.setAttribute('data-bs-theme', query.matches ? 'dark' : 'light');
        };
        apply();
        query.addEventListener('change', apply);
    })();
</script>
