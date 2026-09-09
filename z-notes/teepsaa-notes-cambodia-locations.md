# Cambodia Full Locations Data — Checklist

Extract all provinces, districts, communes, and villages from the official NCDD gazetteer for use in a national address picker (beyond Phnom Penh only).

**Source:** https://db.ncdd.gov.kh/gazetteer/view/index.castle
**Official data:** 25 provinces/municipalities, 163 Srok, 14 Khan, 1,378 Communes, 274 Sangkats, 14,578 villages

---

## Step 1 — Understand the site structure

- [ ] Fetch the index page and inspect the HTML source
- [ ] Determine if data is static HTML or AJAX-loaded (look for API calls in network tab)
- [ ] Check if there is a JSON/XML endpoint (e.g. `?provinceId=X` or `/api/province`)
- [ ] Note whether individual province pages have consistent URLs (e.g. `/view/province.castle?id=1`)

## Step 2 — Extract the data

- [ ] If JSON API exists: loop through all 25 provinces programmatically and collect responses
- [ ] If static HTML: fetch each province page sequentially with WebFetch and parse the hierarchy
- [ ] Capture all four levels: Province → District (Srok/Khan) → Commune (Sangkat) → Village
- [ ] Include both English and Khmer names where available
- [ ] Include official admin codes (the numeric codes shown in the table)

## Step 3 — Store the data

- [ ] Decide on format: PHP array (like `phnom-penh-locations.php`) or database tables
- [ ] If DB: create migration with tables `provinces`, `districts`, `communes`, `villages` with `id`, `code`, `name_en`, `name_km`, `parent_id`
- [ ] If PHP array: structure as `['Province' => ['District' => ['Commune' => [...villages]]]]`
- [ ] Seed the database or save the PHP config file

## Step 4 — Wire into the address picker

- [ ] Replace the current Phnom Penh-only dropdowns with a full national cascade: Province → District → Commune
- [ ] Update buyer settings address form (`dashboard-buyer/settings/index.php`)
- [ ] Update vendor settings business address form (`dashboard-vendor/settings/index.php`)
- [ ] Update the cascading JS (`updateSangkats` → generalize to `updateDistricts` / `updateCommunes`)
- [ ] Update `address-action.php` and `business-address-action.php` to save province + district + commune
- [ ] Update DB columns if needed (currently `khan` and `sangkat` — may need `province` column added)

## Notes

- The current `config/phnom-penh-locations.php` covers only Phnom Penh's 14 Khans and 105 Sangkats
- That file can be retired once national data is in place, or kept as a fast-path for PP-only users
- Phnom Penh data was verified against Wikipedia (May 2026) and is accurate
- The NCDD site may have rate limiting — fetch politely with delays between requests if scraping HTML
