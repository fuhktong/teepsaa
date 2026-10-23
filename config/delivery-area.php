<?php
// Where a map pin may be dropped: anywhere in Cambodia. This used to be the
// Phnom Penh outline; it is now a box round the whole country, islands
// included, so a pin in Siem Reap or Kampot is accepted. Which provinces an
// address may name is still config/cities.php — this only stops a pin
// landing in Thailand, Vietnam, Laos or the sea off the map.
//
// The outline itself is DELIVERY_AREA in js/boundary.js, the file the
// website's maps test against, and read from there so there is one copy.
// The apps get it as `map.boundary` (app_map_settings() below). Old app
// builds still test pins against that field, which is why it is still sent:
// they now get the country box, and stop refusing pins outside Phnom Penh.

/** The outline as [[lat, lng], …], read from js/boundary.js. */
function delivery_area(): array
{
    static $ring = null;
    if ($ring !== null) return $ring;

    $ring = [];
    $js   = @file_get_contents(__DIR__ . '/../js/boundary.js');
    if ($js !== false && preg_match('/DELIVERY_AREA\s*=\s*\[(.*?)\];/s', $js, $m)) {
        preg_match_all('/\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\]/', $m[1], $pairs, PREG_SET_ORDER);
        foreach ($pairs as $p) $ring[] = [(float)$p[1], (float)$p[2]];
    }
    return $ring;
}

/** pointInPolygon() from js/boundary.js, line for line. */
function point_in_delivery_area(float $lat, float $lng): bool
{
    $poly = delivery_area();
    // No outline to test against is a server fault, not the user's pin.
    if (count($poly) < 3) return true;

    $inside = false;
    for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
        [$latI, $lngI] = $poly[$i];
        [$latJ, $lngJ] = $poly[$j];
        $intersect = (($lngI > $lng) !== ($lngJ > $lng))
            && ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}

/**
 * What an app needs to draw the pin map. Sent by api/v1/buyer/addresses.php
 * and api/v1/business.php. Needs config/mapbox.php.
 *
 * `center` and `zoom` are where a map with no pin opens on app builds that
 * predate the province centres (`provinces.*.centre`). `bounds` is how far the
 * map can scroll: the country, with a margin.
 */
function app_map_settings(): array
{
    return [
        'token'    => MAPBOX_TOKEN,
        'style'    => 'mapbox://styles/mapbox/streets-v12',
        'center'   => [104.9160, 11.5564],
        'zoom'     => 13,
        'bounds'   => [[101.8, 9.5], [108.2, 15.0]],
        'boundary' => delivery_area(),
    ];
}
