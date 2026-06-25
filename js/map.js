function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

mapboxgl.accessToken = MAPBOX_TOKEN;

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/streets-v12',
    center: [104.9160, 11.5564],
    zoom: 13,
    maxBounds: [[104.654628, 11.324807], [105.055619, 11.737473]]
});

map.on('load', () => {
    addCityMask(map);

    const dataPromise = typeof window.BUSINESSES !== 'undefined'
        ? Promise.resolve(window.BUSINESSES)
        : fetch('/api/businesses/').then(r => r.json());

    dataPromise.then(businesses => {
        businesses.forEach(b => {
            const photos = b.photos.length
                ? b.photos.map(f => `<img src="/uploads/${escHtml(f)}" alt="">`).join('')
                : '';

            const popup = new mapboxgl.Popup({ maxWidth: '260px' }).setHTML(`
                <div class="popup">
                    <strong><a href="/business/?id=${encodeURIComponent(b.id)}">${escHtml(b.name)}</a></strong>
                    <span class="popup-category">${escHtml(b.category)}</span>
                    ${b.address ? `<span class="popup-address">${escHtml(b.address)}</span>` : ''}
                    ${b.description ? `<p class="popup-desc">${escHtml(b.description)}</p>` : ''}
                    ${photos ? `<div class="popup-photos">${photos}</div>` : ''}
                </div>`);

            new mapboxgl.Marker()
                .setLngLat([b.lng, b.lat])
                .setPopup(popup)
                .addTo(map);
        });
    });
});
