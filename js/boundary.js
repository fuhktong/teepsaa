// Where a map pin may be dropped — a box round the whole of Cambodia, islands
// included. [lat, lng] order (used by pointInPolygon). It replaced the Phnom
// Penh outline when delivery went nationwide.
//
// config/delivery-area.php reads DELIVERY_AREA out of this file for the
// server's check and for the apps, so this is the only copy. Keep the name
// and the [lat, lng] pairs as they are or that read finds nothing.
const DELIVERY_AREA = [
    [9.85, 102.30],
    [9.85, 107.65],
    [14.70, 107.65],
    [14.70, 102.30]
];

// How far the maps can scroll: the country with a margin, [lng, lat] pairs.
const DELIVERY_BOUNDS = [[101.8, 9.5], [108.2, 15.0]];

function pointInPolygon(lat, lng, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const latI = polygon[i][0], lngI = polygon[i][1];
        const latJ = polygon[j][0], lngJ = polygon[j][1];
        const intersect = ((lngI > lng) !== (lngJ > lng)) &&
            (lat < (latJ - latI) * (lng - lngI) / (lngJ - lngI) + latI);
        if (intersect) inside = !inside;
    }
    return inside;
}

function inDeliveryArea(lat, lng) {
    return pointInPolygon(lat, lng, DELIVERY_AREA);
}

// The province's town, from the page's locations data — [lng, lat] and a
// zoom — or Phnom Penh when the page has none.
function provinceView(name) {
    const el = document.getElementById('locations-data');
    try {
        const p = JSON.parse(el.textContent).provinces[name];
        if (p && p.centre) return { center: p.centre[0], zoom: p.centre[1] };
    } catch (e) { /* fall through */ }
    return { center: [104.9160, 11.5564], zoom: 13 };
}

// Picking a province moves the map to it, so the vendor or buyer is not left
// looking at Phnom Penh while they set an address in Kampot.
function followProvince(map, select) {
    if (!select) return;
    select.addEventListener('change', () => {
        const v = provinceView(select.value);
        map.flyTo({ center: v.center, zoom: v.zoom });
    });
}
