/* Cascading category picker, multi-select.

   This is the same tree vendors pick from, read straight from `categories`.
   It only reads: canvassing stores category *names* on the prospect row, so
   nothing here touches `businesses` and no prospect can surface as a vendor.
   Names are held comma separated, the same shape businesses.category uses.

   Markup expected on the page:
     <script type="application/json" id="cat-tree-data">
       [{"id":1,"parent_id":null,"name":"Food"}, ...]
     </script>
     <div class="psp-field">
       <ul class="psp-cat-chosen" data-cat-chosen></ul>
       <div class="psp-cat" data-cat-cascade data-target="category"></div>
       <button type="button" data-cat-add>Add category</button>
       <input type="hidden" id="category" name="category" value="Bakery, Coffee">
     </div>

   Unlike the vendor signup form this does not insist on a leaf. A shop you are
   standing outside is worth filing under "Food" when you have not yet asked
   what kind — whatever the deepest chosen level says is what Add takes. */
(function () {
    'use strict';

    var data = document.getElementById('cat-tree-data');
    if (!data) return;

    var cats;
    try { cats = JSON.parse(data.textContent || '[]'); } catch (e) { return; }

    var byParent = {}, byId = {}, byName = {};
    cats.forEach(function (c) {
        byId[c.id] = c;
        byName[c.name.toLowerCase()] = c;
        var key = (c.parent_id === null || c.parent_id === undefined) ? 'root' : String(c.parent_id);
        (byParent[key] = byParent[key] || []).push(c);
    });

    function splitNames(raw) {
        return String(raw || '').split(',').map(function (n) {
            return n.trim();
        }).filter(function (n) { return n !== ''; });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-cat-cascade]'), function (box) {
        var hidden = document.getElementById(box.getAttribute('data-target'));
        if (!hidden) return;

        var field  = box.closest('.psp-field') || box.parentNode;
        var chips  = field.querySelector('[data-cat-chosen]');
        var addBtn = field.querySelector('[data-cat-add]');
        var clrBtn = field.querySelector('[data-cat-clear]');
        var chosen = splitNames(hidden.value);

        function has(name) {
            var lower = name.toLowerCase();
            return chosen.some(function (n) { return n.toLowerCase() === lower; });
        }

        function levels() {
            return Array.prototype.slice.call(box.querySelectorAll('select'));
        }

        // The deepest level with something selected is what Add would take.
        function candidate() {
            var pick = null;
            levels().forEach(function (s) { if (s.value) pick = byId[s.value]; });
            return pick;
        }

        function refreshAdd() {
            if (!addBtn) return;
            var c = candidate();
            addBtn.disabled = !c || has(c.name);
        }

        function paint() {
            if (!chips) return;
            chips.textContent = '';
            chosen.forEach(function (name, i) {
                var known = Object.prototype.hasOwnProperty.call(byName, name.toLowerCase());
                var li = document.createElement('li');
                li.className = 'psp-cat-chip' + (known ? '' : ' psp-cat-chip-unknown');
                // A name typed before this list existed, or one since renamed.
                // Say so, because saving will drop it.
                if (!known) li.title = 'Not in the category list any more — saving will drop it.';
                li.appendChild(document.createTextNode(name));

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'psp-cat-chip-x';
                x.setAttribute('aria-label', 'Remove ' + name);
                x.textContent = '×';
                x.addEventListener('click', function () {
                    chosen.splice(i, 1);
                    commit();
                });
                li.appendChild(x);
                chips.appendChild(li);
            });
            chips.hidden = chosen.length === 0;
        }

        function commit() {
            hidden.value = chosen.join(', ');
            paint();
            refreshAdd();
        }

        function render(parentKey, level) {
            var children = byParent[parentKey] || [];
            if (!children.length) return;

            var sel = document.createElement('select');
            sel.className = 'psp-select psp-cat-level';

            var ph = document.createElement('option');
            ph.value = '';
            ph.textContent = level === 0 ? '— Select a category —' : '— Narrow it down (optional) —';
            sel.appendChild(ph);

            children.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });

            sel.addEventListener('change', function () {
                levels().slice(level + 1).forEach(function (s) { s.remove(); });
                if (sel.value) render(sel.value, level + 1);
                refreshAdd();
            });

            box.appendChild(sel);
        }

        // Back to the top level, ready for the next one.
        function reset() {
            levels().slice(1).forEach(function (s) { s.remove(); });
            var first = levels()[0];
            if (first) first.value = '';
            refreshAdd();
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var c = candidate();
                if (c && !has(c.name)) {
                    chosen.push(c.name);
                    commit();
                }
                reset();
            });
        }

        if (clrBtn) {
            clrBtn.addEventListener('click', function () {
                chosen = [];
                commit();
                reset();
            });
        }

        render('root', 0);
        paint();
        refreshAdd();
    });
})();
