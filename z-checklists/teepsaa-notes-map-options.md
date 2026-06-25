# Teepsaa — Map Options

## Current: Hard boundary (maxBounds)

Users cannot scroll or pan outside the Phnom Penh boundary rectangle. Pins on the submit page are also restricted to inside the polygon via `pointInPolygon`.

---

## Alternative: Free scroll, pin restriction only

Remove `maxBounds` from both maps so users can scroll anywhere. Keep the `pointInPolygon` check on the submit page so business pins can only be placed inside the boundary. The dark overlay still shows the active zone visually.

**To implement:**
- Remove `maxBounds` from `map.js` and `submit/index.php`
- Keep `addCityMask(map)` and `pointInPolygon` check in `submit/index.php`
