/* 百货零售 ERP — 通用前端脚本（离线，原生 JS，无第三方依赖） */
(function () {
    'use strict';

    // 读取 Blade 注入的 CSRF Token
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // 原生 fetch 封装：自动携带 CSRF 与 AJAX 标记
    window.shopFetch = function (url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-Requested-With'] = 'XMLHttpRequest';
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf((options.method || 'GET').toUpperCase()) !== -1) {
            options.headers['X-CSRF-TOKEN'] = csrfToken();
        }
        return fetch(url, options);
    };

    // 表单提交后禁用按钮，防止重复提交
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        var btn = form.querySelector('[type="submit"]:not([data-no-disable])');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            // 提交失败时由后端/前端回滚时再放开；简单场景随页面导航重置
            setTimeout(function () { btn.disabled = false; }, 3000);
        }
    });
})();
