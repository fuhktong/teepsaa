/* Province → district → commune pickers.

   The page prints its first options server-side (location_options() in
   config/locations.php), so a saved address shows selected without waiting
   on this. This only refills the lower lists when a higher one changes.

   Markup expected on the page:
     <?= location_data_script(...) ?>      → <script id="locations-data">
     <form>
       <select name="city"    data-loc-city>
       <select name="khan"    data-loc-khan>
       <select name="sangkat" data-loc-sangkat>
     </form>

   Any number of forms on one page; each is wired on its own. Values are
   always the English name, labels are Khmer on a Khmer page. */
(function () {
    'use strict';

    var el = document.getElementById('locations-data');
    if (!el) return;

    var data;
    try { data = JSON.parse(el.textContent || '{}'); } catch (e) { return; }
    var km = data.lang === 'km';

    function fill(sel, names, placeholder) {
        sel.innerHTML = '';
        var first = document.createElement('option');
        first.value = '';
        first.textContent = placeholder;
        sel.appendChild(first);
        Object.keys(names || {}).forEach(function (en) {
            var opt = document.createElement('option');
            opt.value = en;
            opt.textContent = km ? (names[en].km || names[en]) : en;
            sel.appendChild(opt);
        });
    }

    document.querySelectorAll('[data-loc-city]').forEach(function (city) {
        var form    = city.form;
        var khan    = form && form.querySelector('[data-loc-khan]');
        var sangkat = form && form.querySelector('[data-loc-sangkat]');
        if (!khan || !sangkat) return;

        function districts() {
            var p = data.provinces[city.value];
            return p ? p.districts : {};
        }

        city.addEventListener('change', function () {
            fill(khan, districts(), data.placeholders.khan);
            fill(sangkat, {}, data.placeholders.sangkat);
        });

        khan.addEventListener('change', function () {
            var d = districts()[khan.value];
            fill(sangkat, d ? d.communes : {}, data.placeholders.sangkat);
        });
    });
})();
