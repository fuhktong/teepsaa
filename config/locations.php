<?php
// The province → district → commune lists the address forms offer, and the
// check that a posted trio is real. Shared by the buyer address book, the
// vendor's business page and the new-business form, so the three can never
// disagree about what an address may say.
//
// The names live in cambodia-locations.php. Which provinces are offered is
// config/cities.php — that file is the on-switch for nationwide delivery, so
// nothing here lists a province it does not hold.
//
// The English name is always what is posted and saved; the Khmer one is only
// the label shown on a Khmer page.

/** cambodia-locations.php, cut down to the provinces in cities.php, in its order. */
function locations_offered(): array
{
    static $out = null;
    if ($out !== null) return $out;

    $all    = require __DIR__ . '/cambodia-locations.php';
    $cities = require __DIR__ . '/cities.php';
    $out    = [];
    foreach ($cities as $c) {
        if (isset($all[$c])) $out[$c] = $all[$c];
    }
    return $out;
}

/** [English => Khmer] for each province offered. */
function location_provinces(): array
{
    return array_map(fn($p) => $p['km'], locations_offered());
}

/** [English => Khmer] for the districts of one province; [] if it isn't offered. */
function location_districts(?string $city): array
{
    $p = locations_offered()[$city ?? ''] ?? null;
    return $p ? array_map(fn($d) => $d['km'], $p['districts']) : [];
}

/** [English => Khmer] for the communes of one district; [] if either doesn't fit. */
function location_communes(?string $city, ?string $khan): array
{
    return locations_offered()[$city ?? '']['districts'][$khan ?? '']['communes'] ?? [];
}

/**
 * Which of 'city', 'khan', 'sangkat' do not fit together — the first one
 * wrong, or []. A blank khan or sangkat is not reported here: that is
 * address_missing_fields()'s job, and the business forms allow blanks.
 */
function location_errors(?string $city, ?string $khan, ?string $sangkat): array
{
    $p = locations_offered()[$city ?? ''] ?? null;
    if (!$p) return ['city'];
    if (!$khan) return [];
    $d = $p['districts'][$khan] ?? null;
    if (!$d) return ['khan'];
    if ($sangkat && !isset($d['communes'][$sangkat])) return ['sangkat'];
    return [];
}

/** <option>s for one level. Value in English, label in the page's language. */
function location_options(array $names, ?string $selected): string
{
    $km  = current_lang() === 'km';
    $out = '';
    foreach ($names as $en => $kh) {
        $out .= '<option value="' . htmlspecialchars($en) . '"' . ($selected === $en ? ' selected' : '') . '>'
              . htmlspecialchars($km ? $kh : $en) . '</option>';
    }
    return $out;
}

/**
 * The <script> js/locations.js reads to refill the district and commune
 * lists when the province or district changes.
 */
function location_data_script(string $selectDistrict, string $selectCommune): string
{
    $data = [
        'lang'         => current_lang(),
        'placeholders' => ['khan' => $selectDistrict, 'sangkat' => $selectCommune],
        'provinces'    => locations_offered(),
    ];
    return '<script type="application/json" id="locations-data">'
         . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
         . '</script>';
}
