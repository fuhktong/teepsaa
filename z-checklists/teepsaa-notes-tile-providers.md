# Teepsaa — Map Tile Provider Comparison

Leaflet is just a rendering library — it draws markers and handles interactions. It does not provide map images. You need a separate **tile provider** to supply the map imagery.

**Currently in use: Mapbox GL JS v2.15.0 with `mapbox://styles/mapbox/streets-v12` (English labels)**

---

## Options

### OpenStreetMap (tile.openstreetmap.org)
- **Cost:** Free — open source data
- **Tile server:** OSM's public tile server is not for heavy or commercial use — run your own or use a commercial provider
- **Khmer label quality:** Excellent — best community-maintained Khmer street names in Phnom Penh
- **Verdict:** Great data source. Not a production tile host.

### CARTO
- **Cost:** Free CDN tiles at low volume, no API key — ToS limits commercial use
- **Paid plans:** ~$149/month
- **Khmer label quality:** Good — uses OSM data
- **Verdict:** Good for Khmer map view. Expensive if you only need tiles.

### Esri / ArcGIS
- **Cost:** 1 million tiles/month free, then ~$0.04/1k tiles
- **API key:** Required for production
- **Khmer label quality:** Weak
- **Verdict:** Fine for English view. Poor for Khmer.

### Mapbox ✅ Currently in use
- **Cost:** 50,000 map loads/month free, then $0.50/1k loads
- **API key:** Required (see `config/mapbox.php`, not committed)
- **Khmer label quality:** English only on streets-v12
- **Custom styles:** Yes — Mapbox Studio
- **Verdict:** Most polished option. Good free tier. Current choice.

### Google Maps
- **Cost:** ~$200/month free credit (~28k map loads), $7/1k loads after
- **API key:** Required + credit card
- **Khmer label quality:** Excellent
- **Verdict:** Best data quality. Expensive at scale. High lock-in.

### MapTiler
- **Cost:** 100k views/month free, ~$25/month after
- **API key:** Required
- **Khmer label quality:** Good — uses OSM data
- **Verdict:** Generous free tier. Good Mapbox alternative.

### Stadia Maps
- **Cost:** 200k tile requests/month free (no credit card), $19/month after
- **Khmer label quality:** Good — OSM data
- **Verdict:** Most generous free tier. Good for staying free as long as possible.

---

## Summary

| Provider | Free tier | After free | Khmer | API key |
|----------|-----------|------------|-------|---------|
| OSM direct | Free (dev only) | Self-host | Excellent | No |
| CARTO | Low volume | ~$149/month | Good | No (dev) |
| Esri/ArcGIS | 1M tiles/month | ~$0.04/1k | Weak | Yes |
| Mapbox | 50k loads/month | $0.50/1k | English only | Yes |
| Google Maps | ~28k loads/month | $7/1k | Excellent | Yes + card |
| MapTiler | 100k views/month | ~$25/month | Good | Yes |
| Stadia Maps | 200k tiles/month | ~$19/month | Good | Optional |

*Pricing based on publicly available information as of mid-2024 — verify current rates before committing.*
