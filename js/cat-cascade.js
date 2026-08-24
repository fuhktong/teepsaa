/* Cascading category picker — one <select> per level of the categories tree,
   writing the chosen category's name into a hidden input.

   This is the same tree vendors pick from, read straight from `categories`.
   It only reads: canvassing stores the category *name* on the prospect row, so
   nothing here touches `businesses` and no prospect can surface as a vendor.

   Markup expected on the page:
     <script type="application/json" id="cat-tree-data">
       [{"id":1,"parent_id":null,"name":"Food"}, ...]
     </script>
     <div class="psp-cat" data-cat-cascade data-target="category"></div>
     <input type="hidden" id="category" name="category" value="Bakery">

   Unlike the vendor signup form this does not insist on a leaf. A shop you are
   standing outside is worth filing under "Food" when you have not yet asked
   what kind — whatever the deepest chosen level says is the category. */
(function () {
    var data = document.getElementById('cat-tree-data');
    if (!data) return;

    var cats;
    try { cats = JSON.parse(data.textContent || '[]'); } catch (e) { return; }

    var byParent = {}, byId = {};
    cats.forEach(function (c) {
        byId[c.id] = c;
        var key = (c.parent_id === null || c.parent_id === undefined) ? 'root' : String(c.parent_id);
        (byParent[key] = byParent[key] || []).push(c);
    });

    // The stored value is a name, so the path back to the root has to be found
    // by name. First match wins — duplicate names across branches are the
    // category admin's problem, not this picker's.
    function pathTo(name) {
        if (!name) return [];
        var found = null;
        cats.forEach(function (c) {
            if (!found && c.name.toLowerCase() === name.toLowerCase()) found = c;
        });
        if (!found) return [];
        var path = [], node = found;
        while (node) {
            path.unshift(node);
            node = node.parent_id ? byId[node.parent_id] : null;
        }
        return path;
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-cat-cascade]'), function (box) {
        var hidden = document.getElementById(box.getAttribute('data-target'));
        if (!hidden) return;

        function levels() {
            return Array.prototype.slice.call(box.querySelectorAll('select'));
        }

        function sync() {
            var chosen = '';
            levels().forEach(function (s) { if (s.value) chosen = byId[s.value].name; });
            hidden.value = chosen;
        }

        function render(parentKey, level, selectedId) {
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
                if (selectedId && String(c.id) === String(selectedId)) opt.selected = true;
                sel.appendChild(opt);
            });

            sel.addEventListener('change', function () {
                levels().slice(level + 1).forEach(function (s) { s.remove(); });
                if (sel.value) render(sel.value, level + 1, null);
                sync();
            });

            box.appendChild(sel);
        }

        var path = pathTo(hidden.value);
        var parentKey = 'root';
        for (var i = 0; i <= path.length; i++) {
            render(parentKey, i, path[i] ? path[i].id : null);
            if (!path[i]) break;
            parentKey = String(path[i].id);
        }

        // A name the picker cannot place — typed before this list existed, or
        // since renamed. Say so, because saving the form will drop it.
        if (hidden.value && !path.length) {
            var warn = document.createElement('p');
            warn.className = 'psp-hint psp-cat-orphan';
            warn.textContent = 'Currently saved as "' + hidden.value + '", which is not in the category list any more. '
                             + 'Pick one above — saving without a pick clears it.';
            box.appendChild(warn);
        }

        var clear = box.parentNode.querySelector('[data-cat-clear]');
        if (clear) {
            clear.addEventListener('click', function () {
                levels().slice(1).forEach(function (s) { s.remove(); });
                var first = levels()[0];
                if (first) first.value = '';
                var orphan = box.querySelector('.psp-cat-orphan');
                if (orphan) orphan.remove();
                hidden.value = '';
            });
        }
    });
})();
