/* square-cropper.js — a tiny, dependency-free square crop step for product
 * photo uploads. When a vendor picks one or more photos, each is shown in a
 * modal with a fixed square frame; they pan (drag) and zoom (slider) to
 * choose what shows, and on "Use photo" the visible square is rendered to an
 * 800×800 JPEG. The cropped files replace the input's selection, so the normal
 * form submit uploads exactly what the vendor saw — which is exactly what the
 * storefront card and featured hero display (both use a square, cover-fit box).
 *
 * Usage:  attachSquareCropper(inputEl, { size: 800 });
 * Labels can be overridden via the `labels` option (for i18n).
 */
(function () {
    'use strict';

    var STYLE_ID = 'square-cropper-style';
    function injectStyle() {
        if (document.getElementById(STYLE_ID)) return;
        var css =
            '.sqc-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;' +
            'align-items:center;justify-content:center;z-index:9999;padding:1rem}' +
            '.sqc-modal{background:#fff;border-radius:14px;padding:1.25rem;max-width:92vw;' +
            'box-shadow:0 12px 40px rgba(0,0,0,.3);display:flex;flex-direction:column;gap:.9rem}' +
            '.sqc-head{font-size:.95rem;font-weight:600;color:#111}' +
            '.sqc-sub{font-size:.8rem;color:#6b7280;margin-top:.15rem;font-weight:400}' +
            '.sqc-stage{position:relative;align-self:center;border-radius:10px;overflow:hidden;' +
            'background:#f3f4f6;touch-action:none;cursor:grab}' +
            '.sqc-stage:active{cursor:grabbing}' +
            '.sqc-stage canvas{display:block}' +
            '.sqc-zoom{display:flex;align-items:center;gap:.6rem;font-size:.8rem;color:#6b7280}' +
            '.sqc-zoom input{flex:1}' +
            '.sqc-actions{display:flex;justify-content:flex-end;gap:.6rem}' +
            '.sqc-btn{padding:.55rem 1.1rem;border-radius:8px;font-size:.9rem;font-weight:600;' +
            'cursor:pointer;border:1px solid transparent}' +
            '.sqc-btn-cancel{background:#fff;border-color:#d1d5db;color:#374151}' +
            '.sqc-btn-ok{background:#c8734f;color:#fff}';
        var el = document.createElement('style');
        el.id = STYLE_ID;
        el.textContent = css;
        document.head.appendChild(el);
    }

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('bad image')); };
            img.src = url;
        });
    }

    // Show the crop modal for one image; resolves with a cropped File, or null if skipped.
    function cropOne(file, opts, index, total) {
        return new Promise(function (resolve) {
            loadImage(file).then(function (img) {
                var frame = Math.min(360, window.innerWidth - 80, window.innerHeight - 260);
                frame = Math.max(220, frame);

                var overlay = document.createElement('div');
                overlay.className = 'sqc-overlay';
                var counter = total > 1 ? ' (' + index + '/' + total + ')' : '';
                overlay.innerHTML =
                    '<div class="sqc-modal">' +
                        '<div class="sqc-head">' + opts.labels.title + counter +
                            '<div class="sqc-sub">' + opts.labels.hint + '</div></div>' +
                        '<div class="sqc-stage"><canvas></canvas></div>' +
                        '<div class="sqc-zoom"><span>–</span>' +
                            '<input type="range" min="100" max="300" value="100"><span>+</span></div>' +
                        '<div class="sqc-actions">' +
                            '<button type="button" class="sqc-btn sqc-btn-cancel">' + opts.labels.cancel + '</button>' +
                            '<button type="button" class="sqc-btn sqc-btn-ok">' + opts.labels.ok + '</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(overlay);

                var canvas = overlay.querySelector('canvas');
                canvas.width = frame;
                canvas.height = frame;
                var ctx = canvas.getContext('2d');
                var zoom = overlay.querySelector('input[type=range]');

                var coverScale = Math.max(frame / img.width, frame / img.height);
                var userZoom = 1;
                var ox = 0, oy = 0; // top-left of drawn image within the frame

                function scale() { return coverScale * userZoom; }
                function clamp() {
                    var dw = img.width * scale(), dh = img.height * scale();
                    ox = Math.min(0, Math.max(frame - dw, ox));
                    oy = Math.min(0, Math.max(frame - dh, oy));
                }
                function draw() {
                    clamp();
                    var dw = img.width * scale(), dh = img.height * scale();
                    ctx.clearRect(0, 0, frame, frame);
                    ctx.drawImage(img, ox, oy, dw, dh);
                }
                // Center initially.
                ox = (frame - img.width * scale()) / 2;
                oy = (frame - img.height * scale()) / 2;
                draw();

                zoom.addEventListener('input', function () {
                    var cx = frame / 2, cy = frame / 2;
                    var before = scale();
                    userZoom = zoom.value / 100;
                    var after = scale();
                    // Zoom around the frame center so it feels anchored.
                    ox = cx - (cx - ox) * (after / before);
                    oy = cy - (cy - oy) * (after / before);
                    draw();
                });

                var stage = overlay.querySelector('.sqc-stage');
                var dragging = false, lastX = 0, lastY = 0;
                function toCanvas(e) {
                    var r = canvas.getBoundingClientRect();
                    return { x: (e.clientX - r.left) * (canvas.width / r.width),
                             y: (e.clientY - r.top) * (canvas.height / r.height) };
                }
                stage.addEventListener('pointerdown', function (e) {
                    dragging = true;
                    var p = toCanvas(e); lastX = p.x; lastY = p.y;
                    stage.setPointerCapture(e.pointerId);
                });
                stage.addEventListener('pointermove', function (e) {
                    if (!dragging) return;
                    var p = toCanvas(e);
                    ox += p.x - lastX; oy += p.y - lastY;
                    lastX = p.x; lastY = p.y;
                    draw();
                });
                stage.addEventListener('pointerup', function () { dragging = false; });
                stage.addEventListener('pointercancel', function () { dragging = false; });

                function close() { overlay.remove(); }

                overlay.querySelector('.sqc-btn-cancel').addEventListener('click', function () {
                    close();
                    resolve(null);
                });
                overlay.querySelector('.sqc-btn-ok').addEventListener('click', function () {
                    var size = opts.size;
                    var out = document.createElement('canvas');
                    out.width = size; out.height = size;
                    var octx = out.getContext('2d');
                    octx.fillStyle = '#fff';
                    octx.fillRect(0, 0, size, size);
                    // Source rectangle currently visible in the frame.
                    var s = scale();
                    var sx = -ox / s, sy = -oy / s, sw = frame / s, sh = frame / s;
                    octx.drawImage(img, sx, sy, sw, sh, 0, 0, size, size);
                    out.toBlob(function (blob) {
                        close();
                        if (!blob) { resolve(null); return; }
                        var base = (file.name || 'photo').replace(/\.[^.]+$/, '');
                        resolve(new File([blob], base + '.jpg', { type: 'image/jpeg' }));
                    }, 'image/jpeg', 0.9);
                });
            }).catch(function () { resolve(file); }); // if it won't load, pass through untouched
        });
    }

    window.attachSquareCropper = function (input, options) {
        if (!input) return;
        injectStyle();
        var opts = Object.assign({
            size: 800,
            labels: {
                title: 'Crop photo',
                hint: 'Drag to move · slider to zoom. The square is what shoppers see.',
                ok: 'Use photo',
                cancel: 'Cancel'
            }
        }, options || {});
        if (options && options.labels) {
            opts.labels = Object.assign({}, opts.labels, options.labels);
        }

        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files || []);
            if (!files.length) return;
            var images = files.filter(function (f) { return /^image\//.test(f.type); });
            if (!images.length) return;

            var out = [];
            var i = 0;
            (function next() {
                if (i >= images.length) {
                    if (!out.length) { input.value = ''; return; } // all cancelled
                    var dt = new DataTransfer();
                    out.forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                    return;
                }
                cropOne(images[i], opts, i + 1, images.length).then(function (f) {
                    if (f) out.push(f);
                    i++;
                    next();
                });
            })();
        });
    };
})();
