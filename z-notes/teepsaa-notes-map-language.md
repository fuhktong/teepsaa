# Teepsaa — Map Language Setting (Build Later)

## Goal

Allow users to choose their preferred map style from account settings. Default is English (Mapbox). Khmer speakers can switch to CARTO Light which renders OSM `name` fields natively in Khmer script.

---

## How it works

| Setting | Tile provider | Labels |
|---------|--------------|--------|
| English (default) | Mapbox streets-v12 | English |
| ខ្មែរ (Khmer) | CARTO Light (`light_all`) | Khmer script |

Switching between them is a single tile URL swap in `js/map.js`.

---

## What to build

### 1. Database

Add a `map_lang` column to the `users` table:

```sql
ALTER TABLE users ADD COLUMN map_lang ENUM('en', 'km') NOT NULL DEFAULT 'en';
```

### 2. Account settings page

New page at `account/index.php` — auth-gated, lets the user update their `map_lang` preference. A simple form with two radio options: English map / Khmer map.

### 3. Session

On login (and on save), write `map_lang` to `$_SESSION['map_lang']`. Pages that load the map read this value.

### 4. Wire into map pages

In `browse/index.php` and any other page that loads a map, output the preference to JS before `map.js` loads:

```php
<script>
const MAP_LANG = '<?= $_SESSION['map_lang'] ?? 'en' ?>';
</script>
```

### 5. Update `js/map.js`

Swap the tile source based on `MAP_LANG`:

```js
const style = MAP_LANG === 'km'
    ? 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json'
    : 'mapbox://styles/mapbox/streets-v12';
```

---

## Notes

- This is separate from the KH/EN UI language toggle — that toggle is for site text (buttons, labels, navigation). This setting is map tiles only.
- CARTO Light has no API key requirement for reasonable traffic.
- The `submit/index.php` map should also respect this setting.
