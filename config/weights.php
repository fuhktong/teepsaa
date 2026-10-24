<?php
// The weights a vendor can choose for a product. The product form shows a
// dropdown, not a number box: vendors think in kilos and rarely own an exact
// scale, and a fixed list cannot be mistyped as 5000 for 500.
//
// Each choice means "up to this much" and saves its top value, in grams, to
// products.weight_g. Couriers round up too, so rounding up never undercharges.
// The last value means "more than 10 kg" — no courier band covers it, and
// what it costs to ship is decided where shipping is priced.
//
// The list goes to the vendor app through api/v1/product-form.php, so the steps
// can follow the courier's price bands without a new app build. Change them
// here only; a product saved with a value no longer listed shows the next one
// up until its vendor saves it again.

const WEIGHT_OVER_MAX_G = 10001;

function weight_options(): array
{
    return [500, 1000, 1500, 2000, 2500, 3000,
            4000, 5000, 6000, 7000, 8000, 9000, 10000,
            WEIGHT_OVER_MAX_G];
}

/** The choice a saved weight falls under: the smallest one at or above it. 0 for never weighed. */
function weight_option_for(int $grams): int
{
    if ($grams <= 0) return 0;
    foreach (weight_options() as $g) {
        if ($g >= $grams) return $g;
    }
    return WEIGHT_OVER_MAX_G;
}

/** "Less than 0.5 kg", "1.5 kg", "More than 10 kg" — the same words the app builds. */
function weight_label(int $grams, array $t): string
{
    $opts = weight_options();
    $kg   = fn(int $g) => rtrim(rtrim(number_format($g / 1000, 1, '.', ''), '0'), '.');
    if ($grams === WEIGHT_OVER_MAX_G) {
        return sprintf($t['prod_weight_over'], $kg($opts[count($opts) - 2]));
    }
    if ($grams === $opts[0]) return sprintf($t['prod_weight_under'], $kg($grams));
    return sprintf($t['prod_weight_kg'], $kg($grams));
}
