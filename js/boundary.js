// Phnom Penh boundary polygon — [lat, lng] order (used by pointInPolygon)
const CITY_BOUNDARY = [
    [11.666378297891825, 104.7466262130589],
    [11.717071915564418, 104.82421092309426],
    [11.73747349406851,  104.91835613138448],
    [11.709329407412099, 104.97153804964967],
    [11.607273809829039, 104.9435121415662],
    [11.556582809353316, 104.9478201199492],
    [11.543207604750776, 104.99524058105595],
    [11.541092236502678, 105.03909041474913],
    [11.530530127596279, 105.05561961304522],
    [11.479823570147545, 105.05131325337925],
    [11.461511268396663, 105.02759663173464],
    [11.327626129291545, 104.97225284859883],
    [11.324807338711722, 104.91691338824705],
    [11.336809274016957, 104.75377888942012],
    [11.351602719598802, 104.7322243710205],
    [11.435417403474787, 104.65462848642892],
    [11.555823203600255, 104.67762538597441],
    [11.666319472508249, 104.74659901426247]
];

function addCityMask(map) {
    const ring = CITY_BOUNDARY.map(([lat, lng]) => [lng, lat]);
    ring.push(ring[0]);

    map.addSource('city-mask', {
        type: 'geojson',
        data: {
            type: 'Feature',
            geometry: {
                type: 'Polygon',
                coordinates: [
                    [[-180, -90], [180, -90], [180, 90], [-180, 90], [-180, -90]],
                    ring
                ]
            }
        }
    });

    map.addLayer({
        id: 'city-mask-fill',
        type: 'fill',
        source: 'city-mask',
        paint: { 'fill-color': '#000', 'fill-opacity': 0.3 }
    });

    map.addSource('city-outline', {
        type: 'geojson',
        data: {
            type: 'Feature',
            geometry: { type: 'Polygon', coordinates: [ring] }
        }
    });

    map.addLayer({
        id: 'city-outline-line',
        type: 'line',
        source: 'city-outline',
        paint: {
            'line-color': '#111',
            'line-width': 2,
            'line-dasharray': [2, 1.5]
        }
    });
}

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
