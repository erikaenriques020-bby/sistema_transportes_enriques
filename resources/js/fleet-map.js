import truckPinUrl from '../images/truck-pin.svg';

let googleMapsPromise;

function loadGoogleMaps(apiKey) {
    if (window.google?.maps?.Map) return Promise.resolve(window.google.maps);
    if (googleMapsPromise) return googleMapsPromise;
    googleMapsPromise = new Promise((resolve, reject) => {
        const timer = window.setTimeout(() => reject(new Error('Google Maps no respondió. Comprueba la conexión o vuelve a abrir el módulo.')), 25000);
        window.__fleetMapsReady = () => {
            window.clearTimeout(timer);
            resolve(window.google.maps);
        };
        const script = document.createElement('script');
        const params = new URLSearchParams({ key: apiKey, callback: '__fleetMapsReady', loading: 'async', v: 'weekly', language: 'es', region: 'BO' });
        script.src = `https://maps.googleapis.com/maps/api/js?${params}`;
        script.async = true;
        script.onerror = () => {
            window.clearTimeout(timer);
            reject(new Error('No se pudo cargar Google Maps. Comprueba la conexión y los bloqueadores del navegador.'));
        };
        document.head.appendChild(script);
    });
    return googleMapsPromise;
}

export async function mountFleetMap(element, config, onError, onSelect = () => {}) {
    if (!config.apiKey) throw new Error('Falta configurar la clave de Google Maps en este servidor.');
    let disposed = false;
    let pending = config.initial;
    let render = null;
    let map;
    let line;
    let places;
    let track;
    let info;
    let truckPin;
    const fleetPins = [];
    const receive = (event) => {
        pending = event.detail.payload;
        if (render) render(pending);
    };
    const togglePlaces = (event) => places?.setMap(event.detail.visible ? map : null);
    const authFailure = () => onError('Google rechazó la clave o el acceso al mapa. Revisa la clave de demostración, sus restricciones y su cuota.');
    window.addEventListener('fleet-map-data', receive);
    window.addEventListener('fleet-map-places', togglePlaces);
    window.addEventListener('fleet-map-auth-error', authFailure);
    window.gm_authFailure = () => window.dispatchEvent(new Event('fleet-map-auth-error'));
    const dispose = () => {
        disposed = true;
        window.removeEventListener('fleet-map-data', receive);
        window.removeEventListener('fleet-map-places', togglePlaces);
        window.removeEventListener('fleet-map-auth-error', authFailure);
        places?.setMap(null);
        track?.setMap(null);
        line?.setMap(null);
        truckPin?.setMap(null);
        fleetPins.forEach((pin) => pin.setMap(null));
        info?.close();
    };
    try {
        const maps = await loadGoogleMaps(config.apiKey);
        if (disposed || !element.isConnected) { dispose(); return dispose; }
        map = new maps.Map(element, {
            center: { lat: -16.5, lng: -64.5 }, zoom: 6, mapTypeId: maps.MapTypeId.HYBRID,
            mapTypeControl: true, streetViewControl: false, fullscreenControl: true,
        });
        places = new maps.Data({ map });
        track = new maps.Data({ map });
        info = new maps.InfoWindow({ maxWidth: 320, maxHeight: 260 });
        class TruckPin extends maps.OverlayView {
            constructor(point, truck, onClick, compact = false) {
                super();
                this.position = new maps.LatLng(point.lat, point.lng);
                this.button = document.createElement('button');
                this.button.type = 'button';
                this.button.title = `Ver detalles del camión ${truck?.plate || ''}`;
                this.button.setAttribute('aria-label', this.button.title);
                this.button.style.cssText = 'position:absolute;transform:translate(-50%,-100%);display:flex;flex-direction:column;align-items:center;border:0;padding:0;background:transparent;cursor:pointer;white-space:nowrap;z-index:10';
                const plate = document.createElement('span');
                plate.textContent = truck?.plate || 'Camión';
                plate.style.cssText = 'background:#18181b;color:#fff;border:2px solid #fff;border-radius:6px;padding:3px 7px;font:bold 12px sans-serif;box-shadow:0 2px 5px #0005';
                const icon = document.createElement('img');
                icon.src = truckPinUrl;
                icon.alt = '';
                icon.width = compact ? 28 : 48;
                icon.height = compact ? 35 : 60;
                icon.style.cssText = 'display:block;filter:drop-shadow(0 2px 2px #0005)';
                this.button.append(plate, icon);
                this.button.addEventListener('click', onClick);
                maps.OverlayView.preventMapHitsAndGesturesFrom(this.button);
            }
            onAdd() { this.getPanes().overlayMouseTarget.appendChild(this.button); }
            draw() {
                const pixel = this.getProjection().fromLatLngToDivPixel(this.position);
                if (!pixel) return;
                this.button.style.left = `${pixel.x}px`;
                this.button.style.top = `${pixel.y}px`;
            }
            onRemove() { this.button.remove(); }
        }
        const style = (color, radius) => ({ icon: { path: maps.SymbolPath.CIRCLE, fillColor: color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: radius } });
        places.setStyle(style('#2563eb', 6));
        track.setStyle(style('#e30613', 6));
        const feature = (lat, lng, title, at = '', details = {}, truck = null) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [lng, lat] }, properties: { title, at, lat, lng, details, truck } });
        const showInfo = (event) => {
            const box = document.createElement('div');
            box.style.cssText = 'color:#111827;max-width:280px;font:14px sans-serif;line-height:1.6';
            const title = document.createElement('strong');
            title.textContent = event.feature.getProperty('title');
            box.appendChild(title);
            const truck = event.feature.getProperty('truck');
            if (truck) {
                for (const text of [`Conductor asignado: ${truck.driver || 'Sin registrar'}`, `Dueño: ${truck.owner || 'Sin registrar'}`, `Estado: ${truck.active ? 'Activo' : 'Inactivo'}`]) {
                    const row = document.createElement('div'); row.textContent = text; box.appendChild(row);
                }
            }
            for (const text of [event.feature.getProperty('at'), `${event.feature.getProperty('lat')}, ${event.feature.getProperty('lng')}`]) {
                if (!text) continue;
                const row = document.createElement('div'); row.textContent = text; box.appendChild(row);
            }
            const labels = {event: 'Evento', speed: 'Velocidad GPS', observed_driver: 'Conductor informado', address: 'Dirección', heading: 'Orientación', odometer: 'Odómetro', nearest_place: 'Lugar cercano', geofence: 'Geocerca', received_at: 'Recepción', buffered: 'Buffer', temperature: 'Temperatura', temperature2: 'Temperatura 2'};
            const details = event.feature.getProperty('details') || {};
            for (const [key, label] of Object.entries(labels)) {
                if (details[key] === undefined || details[key] === null || details[key] === '') continue;
                const row = document.createElement('div'); row.textContent = `${label}: ${details[key]}`; box.appendChild(row);
            }
            info.setOptions({ pixelOffset: new maps.Size(0, event.offset || 0) });
            info.setContent(box); info.setPosition(event.latLng); info.open({ map });
        };
        places.addListener('click', showInfo);
        track.addListener('click', showInfo);
        places.addGeoJson({ type: 'FeatureCollection', features: config.places.map((p) => feature(p.lat, p.lng, `Lugar: ${p.name}`)) });
        for (const entry of config.fleet || []) {
            const pin = new TruckPin(entry.point, entry.truck, () => onSelect(entry.truck.id), true);
            pin.truckId = entry.truck.id;
            pin.setMap(map);
            fleetPins.push(pin);
        }
        const placeBounds = new maps.LatLngBounds();
        config.places.forEach((p) => placeBounds.extend({ lat: p.lat, lng: p.lng }));
        if (!(config.fleet || []).length && config.places.length > 1 && !pending?.points?.length) map.fitBounds(placeBounds, 48);
        render = (payload) => {
            if (disposed || !payload) return;
            track.forEach((entry) => track.remove(entry));
            line?.setMap(null); line = null; info.close();
            truckPin?.setMap(null); truckPin = null;
            const points = payload.points || [];
            fleetPins.forEach((pin) => pin.setMap(payload.route || pin.truckId === payload.truck?.id ? null : map));
            if (!payload.truck && !points.length && fleetPins.length) {
                const bounds = new maps.LatLngBounds();
                (config.fleet || []).forEach((entry) => bounds.extend({ lat: entry.point.lat, lng: entry.point.lng }));
                if (fleetPins.length === 1) { map.setCenter(bounds.getCenter()); map.setZoom(17); }
                else map.fitBounds(bounds, 70);
            }
            track.addGeoJson({ type: 'FeatureCollection', features: (payload.route ? points.slice(0, -1) : []).map((p) => feature(p.lat, p.lng, payload.truck?.plate || 'Camión', p.at, p.details || {}, payload.truck)) });
            if (points.length) {
                const point = points[points.length - 1];
                const data = new maps.Data.Feature({ properties: feature(point.lat, point.lng, `${payload.truck?.plate || 'Camión'} · ${payload.route ? 'Último punto del recorrido' : 'Última posición GPS'}`, point.at, point.details || {}, payload.truck).properties });
                truckPin = new TruckPin(point, payload.truck, () => showInfo({ feature: data, latLng: new maps.LatLng(point.lat, point.lng), offset: -60 }));
                truckPin.setMap(map);
            }
            if (payload.route && points.length > 1) {
                line = new maps.Polyline({ map, path: points.map((p) => ({ lat: p.lat, lng: p.lng })), strokeColor: '#e30613', strokeOpacity: 0.85, strokeWeight: 4 });
            }
            const oneLocation = points.length > 0 && points.every((point) => point.lat === points[0].lat && point.lng === points[0].lng);
            if (oneLocation) {
                map.setCenter({ lat: points[0].lat, lng: points[0].lng }); map.setZoom(17);
                if (config.fleet) map.panBy(Math.min(150, element.clientWidth * 0.22), 0);
            }
            if (points.length > 1 && !oneLocation) {
                const bounds = new maps.LatLngBounds(); points.forEach((p) => bounds.extend({ lat: p.lat, lng: p.lng })); map.fitBounds(bounds, 48);
            }
        };
        render(pending);
        return dispose;
    } catch (error) {
        dispose(); throw error;
    }
}

window.fleetGoogleMap = { mount: mountFleetMap };
