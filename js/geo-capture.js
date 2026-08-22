// Fills lat/lng from the device GPS.
//
// Two jobs, both used by the canvassing pages:
//   1. data-geo-capture — a button inside a form; writes into that form's
//      hidden lat/lng inputs. Add data-geo-auto to fire once on page load, so
//      standing outside a shop and tapping "Add" already has the pin.
//   2. data-geo-goto — a button that reloads the current page with lat/lng in
//      the query string, used to sort the list by nearest.
//
// Needs HTTPS, which production is. Safari shows its own permission prompt the
// first time and remembers the answer per site.

(function () {
    'use strict';

    var OPTS = { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 };

    function say(el, msg) { if (el) el.textContent = msg; }

    function locate(onOk, onFail) {
        if (!navigator.geolocation) { onFail('This browser has no location support.'); return; }
        navigator.geolocation.getCurrentPosition(
            function (pos) { onOk(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy); },
            function (err) {
                onFail(err.code === err.PERMISSION_DENIED
                    ? 'Location permission denied.'
                    : 'Could not get a location fix.');
            },
            OPTS
        );
    }

    function wireCapture(btn) {
        var form = btn.form || btn.closest('form');
        if (!form) return;
        var latIn = form.querySelector('input[name="lat"]');
        var lngIn = form.querySelector('input[name="lng"]');
        var info  = form.querySelector('[data-geo-info]');
        if (!latIn || !lngIn) return;

        function run() {
            say(info, 'Locating…');
            btn.disabled = true;
            locate(function (lat, lng, acc) {
                latIn.value = lat.toFixed(7);
                lngIn.value = lng.toFixed(7);
                btn.disabled = false;
                say(info, 'Pin set — accurate to about ' + Math.round(acc) + ' m.');
            }, function (msg) {
                btn.disabled = false;
                say(info, msg + ' You can still save without a pin.');
            });
        }

        btn.addEventListener('click', run);
        if (btn.hasAttribute('data-geo-auto')) run();
    }

    function wireGoto(btn) {
        btn.addEventListener('click', function () {
            var label = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Locating…';
            locate(function (lat, lng) {
                var url = new URL(window.location.href);
                url.searchParams.set('lat', lat.toFixed(7));
                url.searchParams.set('lng', lng.toFixed(7));
                url.searchParams.set('sort', 'near');
                window.location.href = url.toString();
            }, function (msg) {
                btn.disabled = false;
                btn.textContent = label;
                alert(msg);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-geo-capture]').forEach(wireCapture);
        document.querySelectorAll('[data-geo-goto]').forEach(wireGoto);
    });
})();
