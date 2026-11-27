/* 移动端适配层（S18）
   - 窄屏侧栏抽屉：接管已有汉堡按钮，缺按钮的产品自动补右下角浮动按钮
   - 遮罩点击 / Esc / 点菜单项 自动收起
   - 表格：未包 .table-responsive 的补滚动容器；密表（≥6 列）在手机上转卡片流
   - POS 收银台：手机端补底部「购物车 · 应收」浮动条，可跳商品 / 结算
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
        document.querySelectorAll('table').forEach(function (t) {
            if (t.closest('.table-responsive')) return;
            if (t.closest('.no-table-scroll')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    }

    /* 密表 → 手机卡片流：≥6 列、无合并单元格、行数 ≤300 才转换 */
    function mobileCards() {
        document.querySelectorAll('table').forEach(function (t) {
            if (t.dataset.mcards) return;
            if (t.closest('.pos-cart')) return;
            var heads = Array.prototype.slice.call(t.querySelectorAll('thead th'));
            if (heads.length < 6) return;
            var merged = heads.some(function (th) { return th.colSpan > 1 || th.rowSpan > 1; });
            if (merged) return;
            var rows = Array.prototype.slice.call(t.querySelectorAll('tbody tr'));
            if (!rows.length || rows.length > 300) return;

            var labels = heads.map(function (th) { return th.textContent.trim(); });
            var box = document.createElement('div');
            box.className = 'mcards';

            rows.forEach(function (tr) {
                var tds = Array.prototype.slice.call(tr.children);
                if (tds.length < 6) return;
                var card = document.createElement('article');
                card.className = 'mcard';

                var head = document.createElement('div');
                head.className = 'mcard-head';
                head.innerHTML = tds[0].innerHTML;
                card.appendChild(head);

                var body = document.createElement('div');
                body.className = 'mcard-body';
                tds.slice(1).forEach(function (td, i) {
                    var row = document.createElement('div');
                    var isAction = td.querySelector('button, a.btn') && td.textContent.trim().length < 12;
                    row.className = 'mcard-row' + (isAction ? ' mcard-foot' : '');
                    var k = document.createElement('span');
                    k.className = 'mcard-k';
                    k.textContent = labels[i + 1] || '';
                    var v = document.createElement('span');
                    v.className = 'mcard-v';
                    v.innerHTML = td.innerHTML;
                    row.appendChild(k);
                    row.appendChild(v);
                    body.appendChild(row);
                });
                card.appendChild(body);
                box.appendChild(card);
            });

            if (!box.children.length) return;
            t.classList.add('mcards-on');
            t.parentNode.insertBefore(box, t.nextSibling);
            t.dataset.mcards = '1';
        });
    }

    /* POS 手机浮动条 */
    function initPosBar() {
        var grid = q('#pos-main.pos-grid');
        if (!grid) return;
        var cartCount = q('#pos-cart-count');
        var total = q('#pos-grand-total');
        if (!cartCount || !total) return;

        var bar = document.createElement('div');
        bar.className = 'pos-mobile-bar';
        bar.innerHTML =
            '<div class="pmb-info"><span class="pmb-count">0 件</span><span class="pmb-total">￥0.00</span></div>' +
            '<div class="pmb-actions">' +
            '<button type="button" data-go="catalog">商品</button>' +
            '<button type="button" data-go="pay" class="pmb-primary">结算</button>' +
            '</div>';
        document.body.appendChild(bar);
        document.body.classList.add('pos-mobile');

        var countEl = q('.pmb-count', bar);
        var totalEl = q('.pmb-total', bar);

        function sync() {
            countEl.textContent = cartCount.textContent.trim();
            totalEl.textContent = total.textContent.trim();
        }
        sync();
        try {
            new MutationObserver(sync).observe(document.body, {
                subtree: true, childList: true, characterData: true
            });
        } catch (e) {
            setInterval(sync, 800);
        }

        bar.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-go]');
            if (!b) return;
            var target = b.dataset.go === 'pay'
                ? q('.pos-panel[aria-label="支付面板"]')
                : q('.pos-panel[aria-label="商品面板"]');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = q('#pos-search');
                if (b.dataset.go === 'catalog' && input) input.focus({ preventScroll: true });
            }
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

    function initDrawer() {
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
            autoBtn.className = 'mobile-nav-toggle';
            autoBtn.setAttribute('aria-label', '打开导航菜单');
            autoBtn.innerHTML = '☰';
            document.body.appendChild(autoBtn);
            if (isMobile()) autoBtn.classList.add('is-show');
            toggle = autoBtn;
        }

        function isOpen() {
            return aside.classList.contains('is-open') || aside.classList.contains('show');
        }

        function sync() {
            var open = isOpen();
            backdrop.classList.toggle('show', open);
            document.body.classList.toggle('nav-open', open);
        }

        function close() {
            aside.classList.remove('is-open', 'show');
            sync();
        }

        if (toggle.hasAttribute && toggle.hasAttribute('onclick')) {
            // ERP 自带 onclick 已切换 .show，点击后只需同步遮罩
            toggle.addEventListener('click', function () {
                setTimeout(sync, 0);
            });
        } else {
            toggle.addEventListener('click', function () {
                aside.classList.toggle('is-open');
                sync();
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
                if (autoBtn) autoBtn.classList.remove('is-show');
            } else if (autoBtn) {
                autoBtn.classList.add('is-show');
            }
        });
    }

    function init() {
        wrapTables();
        mobileCards();
        initPosBar();
        initDrawer();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
