/* 移动端适配层（S19）
   - 窄屏侧栏抽屉：接管已有汉堡按钮，缺按钮的产品自动补一个浮动按钮
   - 遮罩点击 / Esc / 点菜单项 自动收起
   - 给未包 .table-responsive 的表格补滚动容器，避免横向撑破页面
   桌面端不做任何改动。 */
(function () {
    'use strict';

    var BP = 992;

    function isMobile() {
        return window.innerWidth < BP;
    }

    function q(sel, root) {
        return (root || document).querySelector(sel);
    }

    function wrapTables() {
        document.querySelectorAll('table.table').forEach(function (t) {
            if (t.closest('.table-responsive')) return;
            if (t.closest('.no-table-scroll')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    }

    function pickAside() {
        return q('.app-wrapper .sidebar') || q('aside.mes-side') || q('aside.hr-side') ||
            q('aside.app-sidebar') || q('aside.sys-side');
    }

    function findToggle(aside) {
        if (aside.classList.contains('sidebar')) {
            // ERP：顶栏已有按钮，onclick 里切换 .show
            return q('.topbar button.d-lg-none') || q('button[onclick*="appSidebar"]');
        }
        if (aside.classList.contains('app-sidebar')) {
            return q('.app-topbar .d-lg-none') || q('[data-nav-toggle]');
        }
        return q('[data-nav-toggle]');
    }

    function init() {
        wrapTables();

        var aside = pickAside();
        if (!aside || !isMobile()) return;

        var backdrop = document.createElement('div');
        backdrop.className = 'nav-backdrop';
        document.body.appendChild(backdrop);

        var toggle = findToggle(aside);
        var autoBtn = null;

        if (!toggle) {
            autoBtn = document.createElement('button');
            autoBtn.type = 'button';
            autoBtn.className = 'mobile-nav-toggle is-needed';
            autoBtn.setAttribute('aria-label', '打开导航菜单');
            autoBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
            document.body.appendChild(autoBtn);
            document.body.classList.add('has-mobile-toggle');
            toggle = autoBtn;

            // HR / MES / 系统后台 没有顶栏，给主区让出按钮位置
            var main = q('.mes-main') || q('.hr-main') || q('.sys-main');
            if (main) main.style.paddingTop = '56px';
        }

        function isOpen() {
            return aside.classList.contains('is-open') || aside.classList.contains('show');
        }

        function sync() {
            var open = isOpen();
            backdrop.classList.toggle('show', open);
            document.body.classList.toggle('nav-open', open);
        }

        function open() {
            aside.classList.add('is-open');
            if (aside.classList.contains('sidebar')) aside.classList.add('show');
            sync();
        }

        function close() {
            aside.classList.remove('is-open', 'show');
            sync();
        }

        if (autoBtn) {
            toggle.addEventListener('click', function () {
                aside.classList.toggle('is-open');
                if (aside.classList.contains('sidebar')) aside.classList.toggle('show');
                sync();
            });
        } else {
            // 产品自带的按钮已在 onclick 里切换状态，点击后同步遮罩即可
            toggle.addEventListener('click', function () {
                setTimeout(sync, 0);
            });
        }

        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
        aside.addEventListener('click', function (e) {
            if (e.target.closest('a')) close();
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                close();
                if (autoBtn) autoBtn.style.display = 'none';
            } else if (autoBtn) {
                autoBtn.style.display = '';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
