/* 扫码与编码公共组件：硬件扫码枪键盘楔入 + 摄像头扫码（BarcodeDetector / jsQR），
 * 以及一维条码 / 二维码渲染。全部离线，依赖 public/vendor 本地库。
 */
(function () {
    'use strict';

    var CONFIG = window.ERP_SCAN || {};
    var resolveUrl = CONFIG.resolveUrl || '/scan/resolve';
    var csrf = CONFIG.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function normalize(value) {
        return String(value == null ? '' : value).trim();
    }

    async function resolve(code, warehouseId) {
        var payload = { code: normalize(code) };
        if (warehouseId) { payload.warehouse_id = Number(warehouseId); }

        var res = await fetch(resolveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });

        var data = {};
        try { data = await res.json(); } catch (e) { data = {}; }

        if (!res.ok) {
            throw new Error(data.message || '扫码解析失败');
        }

        return data;
    }

    /**
     * 绑定硬件扫码枪输入框：回车提交，提交后清空并保持焦点。
     * options.onScan(value), options.minLength, options.clear
     */
    function bind(input, options) {
        if (!input) { return; }
        options = options || {};
        var minLength = Number(options.minLength || 2);

        input.addEventListener('keydown', function (event) {
            if (event.isComposing || event.keyCode === 229) { return; }
            if (event.key !== 'Enter') { return; }

            event.preventDefault();
            var value = normalize(input.value);
            if (value.length < minLength) { return; }

            if (options.clear !== false) { input.value = ''; }
            if (typeof options.onScan === 'function') { options.onScan(value); }
            input.focus();
        });
    }

    function ensureModal() {
        var modal = document.getElementById('erp-scan-camera-modal');
        if (modal) { return modal; }

        modal = document.createElement('div');
        modal.id = 'erp-scan-camera-modal';
        modal.className = 'erp-scan-modal d-none';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'erp-scan-camera-title');
        modal.innerHTML =
            '<div class="erp-scan-dialog">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<h2 id="erp-scan-camera-title" class="h6 mb-0">摄像头扫码</h2>' +
                    '<button type="button" class="btn-close" data-scan-close aria-label="关闭"></button>' +
                '</div>' +
                '<div class="erp-scan-video-wrap">' +
                    '<video data-scan-video playsinline muted></video>' +
                    '<div class="erp-scan-frame" aria-hidden="true"></div>' +
                '</div>' +
                '<p class="small text-muted mt-2 mb-1" data-scan-status>将商品条码或二维码放入取景框。</p>' +
                '<p class="small text-muted mb-2">支持一维码与二维码；无摄像头时可直接使用 USB 扫码枪。</p>' +
                '<div class="d-flex gap-2">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" data-scan-close>关闭</button>' +
                    '<button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" data-scan-manual>手动输入</button>' +
                '</div>' +
                '<canvas data-scan-canvas class="d-none"></canvas>' +
            '</div>';

        document.body.appendChild(modal);
        return modal;
    }

    /**
     * 摄像头扫码。options.title/onResult/onError。
     */
    function scanCamera(options) {
        options = options || {};
        var modal = ensureModal();
        var video = modal.querySelector('[data-scan-video]');
        var canvas = modal.querySelector('[data-scan-canvas]');
        var status = modal.querySelector('[data-scan-status]');
        var title = modal.querySelector('#erp-scan-camera-title');
        var stream = null;
        var timer = null;
        var closed = false;

        if (options.title) { title.textContent = options.title; }
        status.textContent = '正在打开摄像头…';
        modal.classList.remove('d-none');

        function cleanup() {
            closed = true;
            if (timer) { clearTimeout(timer); timer = null; }
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
            modal.classList.add('d-none');
        }

        function finish(value) {
            if (closed) { return; }
            cleanup();
            if (typeof options.onResult === 'function') { options.onResult(normalize(value)); }
        }

        function fail(message) {
            status.textContent = message;
            if (typeof options.onError === 'function') { options.onError(message); }
        }

        modal.querySelectorAll('[data-scan-close]').forEach(function (button) {
            button.onclick = cleanup;
        });

        var manualButton = modal.querySelector('[data-scan-manual]');
        manualButton.onclick = function () {
            var value = window.prompt('请输入条码 / 二维码内容');
            if (value && normalize(value)) { finish(value); }
        };

        async function detectFrame() {
            if (closed) { return; }

            var detector = null;
            if ('BarcodeDetector' in window) {
                try {
                    var formats = ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'qr_code', 'data_matrix', 'itf', 'codabar'];
                    if (typeof window.BarcodeDetector.getSupportedFormats === 'function') {
                        var supported = await window.BarcodeDetector.getSupportedFormats();
                        formats = formats.filter(function (f) { return supported.indexOf(f) !== -1; });
                    }
                    if (formats.length) { detector = new window.BarcodeDetector({ formats: formats }); }
                } catch (e) { detector = null; }
            }

            if (detector && video.readyState >= 2) {
                try {
                    var found = await detector.detect(video);
                    if (found && found.length && found[0].rawValue) {
                        finish(found[0].rawValue);
                        return;
                    }
                } catch (e) { /* 下一帧重试 */ }
            }

            if (typeof window.jsQR === 'function' && video.readyState >= 2) {
                try {
                    var width = video.videoWidth;
                    var height = video.videoHeight;
                    if (width && height) {
                        canvas.width = width;
                        canvas.height = height;
                        var ctx = canvas.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(video, 0, 0, width, height);
                        var image = ctx.getImageData(0, 0, width, height);
                        var qr = window.jsQR(image.data, width, height, { inversionAttempts: 'dontInvert' });
                        if (qr && qr.data) {
                            finish(qr.data);
                            return;
                        }
                    }
                } catch (e) { /* 下一帧重试 */ }
            }

            if (!detector && typeof window.jsQR !== 'function') {
                fail('当前浏览器不支持摄像头识别一维码，请使用 USB 扫码枪或手动输入。');
                return;
            }

            timer = setTimeout(detectFrame, 300);
        }

        (async function start() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                fail('当前浏览器不支持摄像头，请使用 USB 扫码枪或手动输入。');
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                video.srcObject = stream;
                await video.play();
                status.textContent = '请将条码 / 二维码放入取景框…';
                detectFrame();
            } catch (e) {
                fail('无法打开摄像头（' + (e.message || '权限被拒绝') + '），请检查权限或使用扫码枪。');
            }
        })();

        return { close: cleanup };
    }

    function isEan13(value) {
        var digits = String(value || '');
        if (!/^\d{13}$/.test(digits)) { return false; }
        var sum = 0;
        for (var i = 0; i < 12; i++) { sum += Number(digits[i]) * (i % 2 === 0 ? 1 : 3); }
        return (10 - (sum % 10)) % 10 === Number(digits[12]);
    }

    function barcodeFormat(value) {
        var text = String(value || '');
        if (isEan13(text)) { return 'EAN13'; }
        if (/^\d{12}$/.test(text)) { return 'UPC'; }
        return 'CODE128';
    }

    function renderBarcode(canvas, value, options) {
        if (!canvas || !window.JsBarcode) { return false; }
        options = options || {};
        var format = options.format || barcodeFormat(value);

        try {
            window.JsBarcode(canvas, String(value == null ? '' : value), {
                format: format,
                width: options.width || 1.4,
                height: options.height || 44,
                displayValue: options.displayValue !== false,
                fontSize: options.fontSize || 12,
                margin: options.margin || 0,
                background: '#fff',
                lineColor: '#111',
            });
            return true;
        } catch (e) {
            try {
                window.JsBarcode(canvas, String(value || ''), {
                    format: 'CODE128', width: options.width || 1.4, height: options.height || 44,
                    displayValue: true, fontSize: 12, margin: 0, background: '#fff', lineColor: '#111',
                });
                return true;
            } catch (e2) {
                return false;
            }
        }
    }

    function renderQr(element, value, size) {
        if (!element) { return false; }
        if (!window.QRCode) {
            element.textContent = '二维码库未加载';
            return false;
        }

        element.innerHTML = '';
        try {
            new window.QRCode(element, {
                text: String(value == null ? '' : value),
                width: size || 120,
                height: size || 120,
                colorDark: '#111',
                colorLight: '#fff',
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.M : 0,
            });
            return true;
        } catch (e) {
            element.textContent = '二维码生成失败';
            return false;
        }
    }

    window.ErpScan = {
        init: function (config) {
            CONFIG = Object.assign(CONFIG, config || {});
            resolveUrl = CONFIG.resolveUrl || resolveUrl;
            csrf = CONFIG.csrf || csrf;
        },
        resolve: resolve,
        bind: bind,
        scanCamera: scanCamera,
        renderBarcode: renderBarcode,
        renderQr: renderQr,
        barcodeFormat: barcodeFormat,
    };
})();
