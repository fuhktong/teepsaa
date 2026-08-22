// Shrinks a camera photo in the browser before it is uploaded.
//
// A phone shoots 3-5 MB, which is painful on mobile data and pointless for a
// canvassing record — you only need to recognise the shopfront. This downscales
// the photo and re-encodes it, stepping the width down until the result fits
// the budget. The small copy goes into a hidden field as a data URL and the
// original file is never uploaded.
//
// Progressive enhancement: if this script does not run, the file input keeps
// its name and the raw photo posts normally (the server caps it at 8 MB).
//
// Markup it expects:
//   <input type="file" accept="image/*" capture="environment" name="photo" data-photo-shrink>
//   <input type="hidden" name="photo_data">
//   <img data-photo-preview hidden>
//   <span data-photo-info></span>

(function () {
    'use strict';

    // Widths are tried in order until the encoded result fits BUDGET_BYTES.
    //
    // FORMAT is the one knob that matters for file size. JPEG at 0.85 puts a
    // shopfront at roughly 45-60 KB and stays at the full 640 px, so the ladder
    // below almost never has to step down. PNG is lossless and lands at
    // 45-250 KB for the same picture — switch FORMAT to 'image/png' and the
    // posterise step, the preview and the server all follow it.
    var FORMAT       = 'image/jpeg';       // or 'image/png'
    var JPEG_QUALITY = 0.85;               // ignored when FORMAT is PNG
    var WIDTHS       = [640, 520, 420, 340];
    var BUDGET_BYTES = 220 * 1024;
    var LEVELS       = 32;                 // colours per channel; only used for PNG

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload  = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('not an image')); };
            img.src = url;
        });
    }

    function render(img, maxEdge) {
        var scale = Math.min(1, maxEdge / Math.max(img.width, img.height));
        var w = Math.max(1, Math.round(img.width  * scale));
        var h = Math.max(1, Math.round(img.height * scale));

        var canvas = document.createElement('canvas');
        canvas.width  = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);

        // Posterise. PNG is lossless, so the only way to make it meaningfully
        // smaller is to give it fewer distinct values to encode. JPEG throws
        // detail away on its own, and banding only makes it larger, so this is
        // skipped there.
        if (FORMAT === 'image/png') {
            var step = 255 / (LEVELS - 1);
            var data = ctx.getImageData(0, 0, w, h);
            var px   = data.data;
            for (var i = 0; i < px.length; i += 4) {
                px[i]     = Math.round(Math.round(px[i]     / step) * step);
                px[i + 1] = Math.round(Math.round(px[i + 1] / step) * step);
                px[i + 2] = Math.round(Math.round(px[i + 2] / step) * step);
            }
            ctx.putImageData(data, 0, 0);
        }
        return canvas.toDataURL(FORMAT, JPEG_QUALITY);
    }

    function bytesOf(dataUrl) {
        var b64 = dataUrl.slice(dataUrl.indexOf(',') + 1);
        return Math.round(b64.length * 3 / 4);
    }

    function wire(input) {
        var form   = input.form;
        var hidden = form.querySelector('input[name="photo_data"]');
        var img    = form.querySelector('[data-photo-preview]');
        var info   = form.querySelector('[data-photo-info]');
        if (!hidden) return;

        // Kept so the fallback can be restored if shrinking fails.
        var fallbackName = input.getAttribute('name');

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            hidden.value = '';
            input.setAttribute('name', fallbackName);
            if (img) { img.hidden = true; img.removeAttribute('src'); }

            if (!file) {
                if (info) info.textContent = '';
                return;
            }
            if (info) info.textContent = 'Shrinking…';

            loadImage(file).then(function (image) {
                var out = '';
                for (var i = 0; i < WIDTHS.length; i++) {
                    out = render(image, WIDTHS[i]);
                    if (bytesOf(out) <= BUDGET_BYTES) break;
                }
                hidden.value = out;
                // The shrunk copy is what gets posted, so drop the name off the
                // file input and the multi-megabyte original stays on the phone.
                input.removeAttribute('name');
                if (img) { img.src = out; img.hidden = false; }
                if (info) info.textContent = 'Ready — ' + Math.round(bytesOf(out) / 1024) + ' KB';
            }).catch(function () {
                // Leave the file input named; the server handles the raw upload.
                hidden.value = '';
                input.setAttribute('name', fallbackName);
                if (info) info.textContent = 'Could not shrink — uploading the original.';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('[data-photo-shrink]');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].form) wire(inputs[i]);
        }
    });
})();
