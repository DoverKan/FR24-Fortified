<?php
require __DIR__ . '/../src/Config.php';
$errors      = Config::load();
$mapboxToken = defined('MAPBOX_TOKEN') ? trim(MAPBOX_TOKEN) : '';
$icao        = defined('ICAO') && trim(ICAO) !== '' ? strtoupper(trim(ICAO)) : null;
$lat         = defined('LAT') ? (float) LAT : null;
$lon         = defined('LON') ? (float) LON : null;
$hasCenter   = $lat !== null && $lon !== null && !($lat == 0 && $lon == 0);

if ($mapboxToken === ''):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Mapa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f1f3f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 520px; padding: 1.5rem;">
        <div class="text-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#dc3545" viewBox="0 0 16 16">
                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
            </svg>
            <h4 class="mt-3 mb-1 fw-bold">Token de Mapbox requerido</h4>
            <p class="text-muted mb-0" style="font-size:.9rem">La vista Mapa requiere un token de Mapbox para funcionar.</p>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-start gap-3 px-4 py-3">
                <span class="mt-1 text-danger flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg>
                </span>
                <span style="font-size:.9rem"><code>MAPBOX_TOKEN</code> no está configurado en <code>config/config.php</code>.<br>
                Obtén un token gratuito en <a href="https://account.mapbox.com/" target="_blank" rel="noopener">account.mapbox.com</a> y añádelo a tu configuración.</span>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-sm btn-secondary">← Volver al dashboard</a>
        </div>
    </div>
</body>
</html>
<?php
    exit;
endif;

$geojsonFiles = [];
foreach (glob(__DIR__ . '/geojson/*.geojson') as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $geojsonFiles[] = ['url' => 'geojson/' . basename($file), 'name' => $name];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Mapa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.css" rel="stylesheet">
    <link href="https://unpkg.com/mapbox-gl-controls@2.4.0/lib/controls.css" rel="stylesheet">
    <link href="https://unpkg.com/@watergis/mapbox-gl-elevation@latest/dist/mapbox-gl-elevation.css" rel="stylesheet">
    <link href="css/layout.css" rel="stylesheet">
    <style>
        #content { padding: 0; position: relative; }
        #map { height: calc(100vh - var(--topbar-height)); width: 100%; }

        #layer-panel {
            position: absolute;
            top: 10px; left: 10px;
            z-index: 10;
            background: rgba(10,18,35,.92);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: .82rem;
            color: #ccc;
            min-width: 170px;
            max-height: calc(100vh - var(--topbar-height) - 80px);
            overflow-y: auto;
            backdrop-filter: blur(4px);
        }
        .lp-title {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #555;
            margin: 6px 0 3px;
        }
        .lp-title:first-child { margin-top: 0; }
        .lp-row {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 2px 0;
            cursor: pointer;
            user-select: none;
            line-height: 1.4;
        }
        .lp-row input { cursor: pointer; margin: 0; }
        .mapboxgl-popup-content {
            background: rgba(10,18,35,.95) !important;
            color: #e0e0e0 !important;
            border: 1px solid rgba(255,255,255,.1) !important;
            border-radius: 6px !important;
            padding: 10px 14px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,.5) !important;
        }
        .mapboxgl-popup-close-button { color: #888 !important; font-size: 1rem !important; }
        .mapboxgl-popup-tip { border-top-color: rgba(10,18,35,.95) !important; }
    </style>
</head>
<body class="sidebar-collapsed">

<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/topbar.php'; ?>

<main id="main">
    <div id="content">
        <div id="map"></div>
        <div id="layer-panel"></div>
    </div>
</main>

<script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
<script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.js"></script>
<script src="https://unpkg.com/mapbox-gl-controls@2.4.0/lib/controls.js"></script>
<script src="https://unpkg.com/@watergis/mapbox-gl-elevation@latest/dist/mapbox-gl-elevation.umd.js"></script>
<script>
(function () {
    const mapboxToken  = <?= json_encode($mapboxToken) ?>;
    const icao         = <?= json_encode($icao) ?>;
    const lat          = <?= json_encode($hasCenter ? $lat : null) ?>;
    const lon          = <?= json_encode($hasCenter ? $lon : null) ?>;
    const geojsonFiles = <?= json_encode($geojsonFiles) ?>;

    const center = lon !== null ? [lon, lat] : [-3, 40];
    const zoom   = lat !== null ? 9 : 6;

    // ---- Estilos base disponibles ----
    const STYLES = mapboxToken ? [
        { id: 'dark',      label: 'Oscuro',    url: 'mapbox://styles/mapbox/dark-v11' },
        { id: 'streets',   label: 'Calles',    url: 'mapbox://styles/mapbox/streets-v12' },
        { id: 'satellite', label: 'Satélite',  url: 'mapbox://styles/mapbox/satellite-streets-v12' },
        { id: 'terrain',   label: 'Terreno',   url: 'mapbox://styles/mapbox/outdoors-v12' },
    ] : [
        { id: 'carto-dark', label: 'Oscuro', url: null },
        { id: 'osm',        label: 'OpenStreetMap', url: {
            version: 8,
            glyphs: 'https://fonts.openmaptiles.org/{fontstack}/{range}.pbf',
            sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '© OpenStreetMap' } },
            layers: [{ id: 'osm-bg', type: 'raster', source: 'osm' }]
        }},
    ];

    const noTokenDarkStyle = {
        version: 8,
        glyphs: 'https://fonts.openmaptiles.org/{fontstack}/{range}.pbf',
        sources: {
            'carto-dark': {
                type: 'raster',
                tiles: [
                    'https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
                    'https://b.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
                ],
                tileSize: 256,
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/">CARTO</a>'
            }
        },
        layers: [{ id: 'carto-dark-bg', type: 'raster', source: 'carto-dark' }]
    };

    let currentStyleId = mapboxToken ? 'dark' : 'carto-dark';

    if (mapboxToken) mapboxgl.accessToken = mapboxToken;

    const map = new mapboxgl.Map({
        container: 'map',
        style: mapboxToken ? 'mapbox://styles/mapbox/dark-v11' : noTokenDarkStyle,
        center,
        zoom,
        attributionControl: true,
        pitchWithRotate: true,
        cooperativeGestures: true,
        antialias: true,
        projection: 'globe',
        hash: true,
    });

    map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
    map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'top-right');

    const draw = new MapboxDraw({
        displayControlsDefault: false,
        controls: { polygon: true, line_string: true, point: true, trash: true },
        defaultMode: 'simple_select',
    });
    map.addControl(draw, 'top-right');

    // mapbox-gl-controls: regla de medición + inspección de coordenadas
    if (typeof mapboxglControls !== 'undefined') {
        map.addControl(new mapboxglControls.RulerControl(), 'top-right');
        map.addControl(new mapboxglControls.InspectControl(), 'top-right');
    }

    // mapbox-gl-elevation: perfil de elevación del terreno
    if (mapboxToken && typeof MapboxElevation !== 'undefined') {
        const elevation = new MapboxElevation({
            API: 'https://api.mapbox.com/v4/mapbox.mapbox-terrain-v2/tilequery/',
            token: mapboxToken,
        });
        map.addControl(elevation, 'bottom-right');
    }

    // ---- Terreno 3D ----
    function addTerrain() {
        if (!mapboxToken) return;
        if (!map.getSource('mapbox-dem')) {
            map.addSource('mapbox-dem', {
                type: 'raster-dem',
                url: 'mapbox://mapbox.mapbox-terrain-dem-v1',
                tileSize: 512,
                maxzoom: 14
            });
        }
        map.setTerrain({ source: 'mapbox-dem', exaggeration: 1.5 });
    }

    // ---- Fuentes de iconos (elementos HTML) ----
    function mkEl(html, cursor) {
        const d = document.createElement('div');
        d.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:2px' + (cursor ? ';cursor:pointer' : '');
        d.innerHTML = html;
        return d;
    }

    function vorElement(id) {
        const c = '#22d3ee';
        return mkEl(
            '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">'
            + '<polygon points="13,1 22.5,6.5 22.5,17.5 13,23 3.5,17.5 3.5,6.5" fill="rgba(34,211,238,.12)" stroke="' + c + '" stroke-width="1.5"/>'
            + '<line x1="13" y1="3"  x2="13" y2="8"  stroke="' + c + '" stroke-width="1"/>'
            + '<line x1="13" y1="18" x2="13" y2="23" stroke="' + c + '" stroke-width="1"/>'
            + '<line x1="3"  y1="13" x2="8"  y2="13" stroke="' + c + '" stroke-width="1"/>'
            + '<line x1="18" y1="13" x2="23" y2="13" stroke="' + c + '" stroke-width="1"/>'
            + '<circle cx="13" cy="13" r="2.5" fill="' + c + '"/>'
            + '</svg>'
            + '<div style="background:rgba(0,0,0,.75);color:' + c + ';font-size:9px;font-family:monospace;font-weight:700;padding:1px 4px;border-radius:3px;white-space:nowrap;letter-spacing:.5px">' + id + '</div>',
        true);
    }

    function airportElement(id) {
        const c = '#60a5fa';
        return mkEl(
            '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">'
            + '<circle cx="13" cy="13" r="7" fill="rgba(96,165,250,.1)" stroke="' + c + '" stroke-width="1.5"/>'
            + '<line x1="13" y1="1"  x2="13" y2="6"  stroke="' + c + '" stroke-width="1.5"/>'
            + '<line x1="13" y1="20" x2="13" y2="25" stroke="' + c + '" stroke-width="1.5"/>'
            + '<line x1="1"  y1="13" x2="6"  y2="13" stroke="' + c + '" stroke-width="1.5"/>'
            + '<line x1="20" y1="13" x2="25" y2="13" stroke="' + c + '" stroke-width="1.5"/>'
            + '</svg>'
            + '<div style="background:rgba(0,0,0,.75);color:' + c + ';font-size:9px;font-family:monospace;font-weight:700;padding:1px 4px;border-radius:3px;white-space:nowrap;letter-spacing:.5px">' + id + '</div>',
        true);
    }

    function visualpointElement(id) {
        const d = document.createElement('div');
        d.style.cssText = 'width:0;height:0;position:relative;cursor:pointer';
        d.innerHTML = '<div style="width:26px;height:26px;background:#27ae60;border:2px solid #fff;'
            + 'clip-path:polygon(50% 0%,100% 100%,0% 100%);'
            + 'position:absolute;top:-26px;left:-13px;box-shadow:0 2px 5px rgba(0,0,0,.5)"></div>'
            + '<span style="position:absolute;top:-20px;left:-13px;width:26px;'
            + 'text-align:center;font-size:9px;font-weight:700;color:#fff;line-height:26px">' + id + '</span>';
        return d;
    }

    const VARIOS_COLORS = { helipad: '#ef4444', antenna: '#a855f7', milestone: '#f59e0b' };

    function variosElement(type, fill, nombre) {
        const f = fill || VARIOS_COLORS[type] || '#22c55e';
        const rgba = 'rgba(' + parseInt(f.slice(1,3),16) + ',' + parseInt(f.slice(3,5),16) + ',' + parseInt(f.slice(5,7),16) + ',.12)';
        let svgInner = '';
        if (type === 'helipad') {
            svgInner = '<circle cx="13" cy="13" r="10" fill="' + rgba + '" stroke="' + f + '" stroke-width="1.5"/>'
                + '<text x="13" y="18" text-anchor="middle" fill="' + f + '" font-size="13" font-weight="bold" font-family="sans-serif">H</text>';
        } else if (type === 'antenna') {
            svgInner = '<line x1="13" y1="13" x2="13" y2="24" stroke="' + f + '" stroke-width="2"/>'
                + '<line x1="7" y1="19" x2="19" y2="19" stroke="' + f + '" stroke-width="1.5"/>'
                + '<circle cx="13" cy="13" r="1.5" fill="' + f + '"/>'
                + '<path d="M9,11 A5,5 0 0,1 17,11" fill="none" stroke="' + f + '" stroke-width="1.2"/>'
                + '<path d="M6,8 A8,8 0 0,1 20,8" fill="none" stroke="' + f + '" stroke-width="1.2" opacity=".6"/>';
        } else if (type === 'milestone') {
            svgInner = '<polygon points="13,2 24,13 13,24 2,13" fill="' + rgba + '" stroke="' + f + '" stroke-width="1.5"/>'
                + '<text x="13" y="17" text-anchor="middle" fill="' + f + '" font-size="8" font-weight="bold" font-family="monospace">NM</text>';
        } else {
            svgInner = '<circle cx="13" cy="13" r="5" fill="' + f + '"/>';
        }
        return mkEl(
            '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">' + svgInner + '</svg>'
            + '<div style="background:rgba(0,0,0,.75);color:' + f + ';font-size:9px;font-family:monospace;font-weight:700;padding:1px 4px;border-radius:3px;white-space:nowrap;letter-spacing:.5px">' + nombre + '</div>',
        true);
    }

    // ---- Constructores de popup HTML ----
    function vorPopup(p) {
        return '<div style="min-width:160px;font-size:.85rem">'
            + '<div style="font-weight:700;font-size:1rem;margin-bottom:.35rem">'
            + (p.identifier ?? '?') + ' <span style="font-weight:400;color:#888">' + (p.tipo ?? '') + '</span>'
            + '</div>'
            + '<table style="border-collapse:collapse;width:100%">'
            + '<tr><td style="color:#888;padding:2px 6px 2px 0">Nombre</td><td><strong>' + (p.name ?? '—') + '</strong></td></tr>'
            + '<tr><td style="color:#888;padding:2px 6px 2px 0">Frecuencia</td><td>' + (p.frecuencia ?? '—') + '</td></tr>'
            + '<tr><td style="color:#888;padding:2px 6px 2px 0">País</td><td>' + (p.pais ?? '—') + '</td></tr>'
            + '</table></div>';
    }

    function lerPopup(p) {
        return '<div style="min-width:140px;font-size:.85rem">'
            + '<strong style="font-size:1rem">' + (p.nombre ?? '—') + '</strong><br>'
            + '<table style="border-collapse:collapse;width:100%;margin-top:.35rem">'
            + '<tr><td style="color:#888;padding:2px 8px 2px 0">Descripción</td><td>' + (p.descripcion ?? '—') + '</td></tr>'
            + '<tr><td style="color:#888;padding:2px 8px 2px 0">Techo</td><td>' + (p.superior ?? '—') + '</td></tr>'
            + '<tr><td style="color:#888;padding:2px 8px 2px 0">Base</td><td>' + (p.inferior ?? '—') + '</td></tr>'
            + '</table></div>';
    }

    function simplePopup(nombre, descripcion) {
        return '<div style="font-size:.85rem">'
            + '<strong style="font-size:1rem">' + (nombre ?? '—') + '</strong><br>'
            + '<span style="color:#aaa">' + (descripcion ?? '') + '</span>'
            + '</div>';
    }

    function airportPopup(p) {
        return '<div style="font-size:.85rem">'
            + '<strong style="font-size:1rem">' + (p.nombre ?? '—') + '</strong>'
            + (p.icao ? ' <span style="color:#888;font-size:.8rem">(' + p.icao + ')</span>' : '') + '<br>'
            + '<span style="color:#aaa">' + (p.descripcion ?? '') + '</span>'
            + '</div>';
    }

    // ---- Registros de capas ----
    const LAYER_LABELS = {
        vor: 'VOR / DME', visualpoint: 'Puntos visuales',
        varios: 'Varios', Varios: 'Varios',
        ler: 'Restringidas', airports: 'Aeropuertos',
    };

    const GLYPHS_FONT = mapboxToken ? 'DIN Pro Bold,Arial Unicode MS Bold' : 'Open Sans Bold,Arial Unicode MS Bold';

    const markerObjects   = []; // { marker, popupHTML, layerLabel, layerColor, groupId }
    const fillLayerIds    = []; // para queryRenderedFeatures
    const layerPopupMeta  = {}; // layerId → { label, colorProp, popupFn }
    const layerGroups     = {}; // groupId → { label, ids[], markers[] }
    const pendingData     = []; // { name, data }

    let markersAdded = false;
    let dataReady    = false;

    // ---- Añadir source ----
    function addSource(id, data) {
        if (!map.getSource(id)) map.addSource(id, { type: 'geojson', data });
    }

    // ---- Añadir fill + line + label ----
    function addPolygonLayers(groupId, sourceId, defaultFill, lineWidth, label, popupFn, opacity) {
        const fillId  = groupId + '-fill';
        const lineId  = groupId + '-line';
        const labelId = groupId + '-label';

        if (!map.getLayer(fillId)) {
            map.addLayer({ id: fillId, type: 'fill', source: sourceId,
                filter: ['any', ['==', ['geometry-type'], 'Polygon'], ['==', ['geometry-type'], 'MultiPolygon']],
                paint: { 'fill-color': ['coalesce', ['get', 'fill'], defaultFill], 'fill-opacity': opacity ?? 0.2 }
            });
        }
        if (!map.getLayer(lineId)) {
            map.addLayer({ id: lineId, type: 'line', source: sourceId,
                filter: ['any', ['==', ['geometry-type'], 'Polygon'], ['==', ['geometry-type'], 'MultiPolygon']],
                paint: { 'line-color': ['coalesce', ['get', 'fill'], defaultFill], 'line-width': lineWidth ?? 1.5 }
            });
        }
        if (!map.getLayer(labelId)) {
            map.addLayer({ id: labelId, type: 'symbol', source: sourceId,
                filter: ['any', ['==', ['geometry-type'], 'Polygon'], ['==', ['geometry-type'], 'MultiPolygon']],
                layout: { 'text-field': ['get', 'nombre'], 'text-size': 11, 'text-font': [GLYPHS_FONT] },
                paint: {
                    'text-color': ['coalesce', ['get', 'fill'], defaultFill],
                    'text-halo-color': 'rgba(8,14,30,.85)', 'text-halo-width': 1.5
                }
            });
        }

        fillLayerIds.push(fillId);
        layerPopupMeta[fillId] = { label, colorProp: 'fill', defaultColor: defaultFill, popupFn };
        layerGroups[groupId].ids.push(fillId, lineId, labelId);
    }

    // ---- Procesar un GeoJSON ----
    function processGeoJSON(name, data) {
        const rawLabel   = name.includes('-') ? name.split('-').pop() : name;
        const layerLabel = LAYER_LABELS[name] ?? (rawLabel.charAt(0).toUpperCase() + rawLabel.slice(1));
        const groupId    = 'gl-' + name;

        if (!layerGroups[groupId]) layerGroups[groupId] = { label: layerLabel, ids: [], markers: [] };

        if (name === 'vor') {
            if (!markersAdded) {
                data.features.forEach(f => {
                    if (f.geometry.type !== 'Point') return;
                    const p = f.properties ?? {};
                    const [lng, lt] = f.geometry.coordinates;
                    const el = vorElement(p.identifier ?? '?');
                    const marker = new mapboxgl.Marker({ element: el, anchor: 'top' }).setLngLat([lng, lt]).addTo(map);
                    const m = { marker, popupHTML: vorPopup(p), layerLabel, layerColor: '#22d3ee', groupId };
                    markerObjects.push(m);
                    layerGroups[groupId].markers.push(m);
                });
            }

        } else if (name === 'visualpoint') {
            if (!markersAdded) {
                data.features.forEach(f => {
                    if (f.geometry.type !== 'Point') return;
                    const p = f.properties ?? {};
                    const [lng, lt] = f.geometry.coordinates;
                    const el = visualpointElement(p.nombre ?? '?');
                    const marker = new mapboxgl.Marker({ element: el, anchor: 'bottom' }).setLngLat([lng, lt]).addTo(map);
                    const m = { marker, popupHTML: simplePopup(p.nombre, p.descripcion), layerLabel, layerColor: '#27ae60', groupId };
                    markerObjects.push(m);
                    layerGroups[groupId].markers.push(m);
                });
            }

        } else if (name === 'ler') {
            addSource(groupId, data);
            addPolygonLayers(groupId, groupId, '#4a9eda', 2, layerLabel, lerPopup, 0.25);

        } else if (name.toLowerCase() === 'varios') {
            if (!markersAdded) {
                data.features.filter(f => f.geometry.type === 'Point').forEach(f => {
                    const p = f.properties ?? {};
                    const [lng, lt] = f.geometry.coordinates;
                    const el = variosElement(p.icon ?? '', p.fill, p.nombre ?? '?');
                    const marker = new mapboxgl.Marker({ element: el, anchor: 'top' }).setLngLat([lng, lt]).addTo(map);
                    const color = p.fill || VARIOS_COLORS[p.icon] || '#22c55e';
                    const m = { marker, popupHTML: simplePopup(p.nombre, p.descripcion), layerLabel, layerColor: color, groupId };
                    markerObjects.push(m);
                    layerGroups[groupId].markers.push(m);
                });
            }
            // Líneas (M10, etc.)
            if (data.features.some(f => f.geometry.type === 'LineString')) {
                addSource(groupId, data);
                const lineId = groupId + '-lines';
                if (!map.getLayer(lineId)) {
                    map.addLayer({ id: lineId, type: 'line', source: groupId,
                        filter: ['==', ['geometry-type'], 'LineString'],
                        paint: { 'line-color': '#666', 'line-width': 1.2, 'line-dasharray': [4, 3] }
                    });
                }
                layerGroups[groupId].ids.push(lineId);
            }

        } else if (name === 'airports') {
            if (!markersAdded) {
                data.features.forEach(f => {
                    if (f.geometry.type !== 'Point') return;
                    const p = f.properties ?? {};
                    const [lng, lt] = f.geometry.coordinates;
                    const el = airportElement(p.icao ?? p.nombre ?? '?');
                    const marker = new mapboxgl.Marker({ element: el, anchor: 'top' }).setLngLat([lng, lt]).addTo(map);
                    const m = { marker, popupHTML: airportPopup(p), layerLabel, layerColor: '#60a5fa', groupId };
                    markerObjects.push(m);
                    layerGroups[groupId].markers.push(m);
                });
            }

        } else {
            // Genérico: nombre, descripcion, fill
            addSource(groupId, data);
            addPolygonLayers(groupId, groupId, '#e67e22', 1.5, layerLabel, p => simplePopup(p.nombre, p.descripcion), 0.2);
            // Líneas genéricas
            if (data.features.some(f => f.geometry.type === 'LineString')) {
                const lineId = groupId + '-generic-lines';
                if (!map.getLayer(lineId)) {
                    map.addLayer({ id: lineId, type: 'line', source: groupId,
                        filter: ['==', ['geometry-type'], 'LineString'],
                        paint: { 'line-color': ['coalesce', ['get', 'fill'], '#e67e22'], 'line-width': 1.5 }
                    });
                }
                layerGroups[groupId].ids.push(lineId);
            }
        }
    }

    // ---- Anillos de distancia ----
    function addDistanceRings() {
        if (lat === null || lon === null) return;

        const NM_RINGS = [5, 10, 25, 50, 100, 150];

        function ringCoords(cLng, cLat, nm, steps) {
            const r = nm * 1852, R = 6371000;
            const coords = [];
            for (let i = 0; i <= steps; i++) {
                const b  = (i * 360 / steps) * Math.PI / 180;
                const φ1 = cLat * Math.PI / 180;
                const φ2 = Math.asin(Math.sin(φ1) * Math.cos(r / R) + Math.cos(φ1) * Math.sin(r / R) * Math.cos(b));
                const λ2 = cLng * Math.PI / 180 + Math.atan2(Math.sin(b) * Math.sin(r / R) * Math.cos(φ1), Math.cos(r / R) - Math.sin(φ1) * Math.sin(φ2));
                coords.push([λ2 * 180 / Math.PI, φ2 * 180 / Math.PI]);
            }
            return coords;
        }

        const ringFeatures = NM_RINGS.map(nm => ({
            type: 'Feature',
            properties: { nm },
            geometry: { type: 'LineString', coordinates: ringCoords(lon, lat, nm, 128) }
        }));

        const labelFeatures = NM_RINGS.map(nm => ({
            type: 'Feature',
            properties: { label: nm + ' NM' },
            geometry: { type: 'Point', coordinates: ringCoords(lon, lat, nm, 128)[0] }
        }));

        const srcRings  = 'dr-rings';
        const srcLabels = 'dr-labels';

        if (!map.getSource(srcRings))  map.addSource(srcRings,  { type: 'geojson', data: { type: 'FeatureCollection', features: ringFeatures } });
        if (!map.getSource(srcLabels)) map.addSource(srcLabels, { type: 'geojson', data: { type: 'FeatureCollection', features: labelFeatures } });

        if (!map.getLayer('dr-line-bg')) {
            map.addLayer({ id: 'dr-line-bg', type: 'line', source: srcRings, paint: {
                'line-color': 'rgba(0,0,0,.55)',
                'line-width': 3.5,
                'line-dasharray': [5, 4]
            }});
        }
        if (!map.getLayer('dr-line')) {
            map.addLayer({ id: 'dr-line', type: 'line', source: srcRings, paint: {
                'line-color': 'rgba(220,235,255,.85)',
                'line-width': 1.2,
                'line-dasharray': [5, 4]
            }});
        }
        if (!map.getLayer('dr-label')) {
            map.addLayer({ id: 'dr-label', type: 'symbol', source: srcLabels, layout: {
                'text-field': ['get', 'label'],
                'text-size': 11,
                'text-font': [GLYPHS_FONT],
                'text-anchor': 'bottom',
                'text-offset': [0, -.2]
            }, paint: {
                'text-color': 'rgba(220,235,255,.95)',
                'text-halo-color': 'rgba(0,0,0,.8)',
                'text-halo-width': 2
            }});
        }

        if (!layerGroups['dr']) layerGroups['dr'] = { label: 'Anillos NM', ids: [], markers: [] };
        layerGroups['dr'].ids = ['dr-line-bg', 'dr-line', 'dr-label'];
    }

    // ---- Tráfico ----
    function addTraffic() {
        if (!mapboxToken) return;
        if (!layerGroups['traffic']) layerGroups['traffic'] = { label: 'Tráfico', ids: [], markers: [] };
        layerGroups['traffic'].ids = [];

        if (!map.getSource('mapbox-traffic')) {
            map.addSource('mapbox-traffic', {
                type: 'vector',
                url: 'mapbox://mapbox.mapbox-traffic-v1'
            });
        }
        if (!map.getLayer('traffic-layer')) {
            map.addLayer({
                id: 'traffic-layer',
                type: 'line',
                source: 'mapbox-traffic',
                'source-layer': 'traffic',
                minzoom: 11,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: {
                    'line-width': ['interpolate', ['linear'], ['zoom'], 8, 1, 14, 3, 18, 6],
                    'line-color': [
                        'case',
                        ['==', ['get', 'congestion'], 'low'],      '#22c55e',
                        ['==', ['get', 'congestion'], 'moderate'], '#f59e0b',
                        ['==', ['get', 'congestion'], 'heavy'],    '#ef4444',
                        ['==', ['get', 'congestion'], 'severe'],   '#7c3aed',
                        '#888'
                    ]
                }
            });
        }
        layerGroups['traffic'].ids.push('traffic-layer');
    }

    // ---- Edificios 3D ----
    function addBuildings() {
        if (!mapboxToken || !map.getSource('composite')) return;
        if (!layerGroups['buildings']) layerGroups['buildings'] = { label: 'Edificios 3D', ids: [], markers: [] };
        layerGroups['buildings'].ids = [];

        if (!map.getLayer('3d-buildings')) {
            map.addLayer({
                id: '3d-buildings',
                source: 'composite',
                'source-layer': 'building',
                filter: ['==', 'extrude', 'true'],
                type: 'fill-extrusion',
                minzoom: 15,
                paint: {
                    'fill-extrusion-color': '#1e2d45',
                    'fill-extrusion-height': [
                        'interpolate', ['linear'], ['zoom'],
                        15, 0, 15.05, ['get', 'height']
                    ],
                    'fill-extrusion-base': [
                        'interpolate', ['linear'], ['zoom'],
                        15, 0, 15.05, ['get', 'min_height']
                    ],
                    'fill-extrusion-opacity': 0.75
                }
            });
        }
        layerGroups['buildings'].ids.push('3d-buildings');
    }

    // ---- Añadir todas las capas (y re-añadir tras cambio de estilo) ----
    function addAllCustomLayers() {
        addTerrain();
        // Limpiar IDs de capas (el setStyle las elimina)
        fillLayerIds.length = 0;
        Object.values(layerGroups).forEach(g => { g.ids = []; });

        pendingData.forEach(({ name, data }) => processGeoJSON(name, data));
        addDistanceRings();
        addBuildings();
        addTraffic();
        markersAdded = true;
        buildLayerPanel();
    }

    map.on('style.load', () => {
        if (dataReady) addAllCustomLayers();
        else addTerrain();
    });

    // ---- Cargar GeoJSON ----
    Promise.all(
        geojsonFiles.map(({ url, name }) =>
            fetch(url, { cache: 'no-cache' }).then(r => r.json()).then(data => ({ name, data }))
                .catch(err => { console.warn('[GeoJSON]', url, err); return null; })
        )
    ).then(results => {
        results.filter(Boolean).forEach(item => pendingData.push(item));
        dataReady = true;
        if (map.isStyleLoaded()) addAllCustomLayers();
    });

    // ---- Popup multi-capa ----
    const popup = new mapboxgl.Popup({ maxWidth: '340px', closeButton: true });

    function buildPopupHTML(hits) {
        const count = hits.length > 1
            ? '<div style="font-size:.7rem;color:#666;margin-bottom:.5rem">Capas superpuestas (' + hits.length + ')</div>'
            : '';
        const sections = hits.map(h => {
            const hdr = h.label
                ? '<div style="color:' + h.color + ';font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem;padding-bottom:.2rem;border-bottom:1px solid ' + h.color + '44">' + h.label + '</div>'
                : '';
            return hdr + h.html;
        });
        return '<div style="min-width:200px">' + count + sections.join('<hr style="margin:.5rem 0;border-color:#2a3550">') + '</div>';
    }

    map.on('click', e => {
        const hits = [];

        // Marcadores HTML
        markerObjects.forEach(m => {
            if (m.marker.getElement().style.display === 'none') return;
            const mp  = map.project(m.marker.getLngLat());
            const dx  = mp.x - e.point.x, dy = mp.y - e.point.y;
            if (Math.hypot(dx, dy) < 22) hits.push({ html: m.popupHTML, label: m.layerLabel, color: m.layerColor });
        });

        // Capas de polígono (solo visibles)
        const visibleFillIds = fillLayerIds.filter(id => {
            try { return map.getLayoutProperty(id, 'visibility') !== 'none'; } catch { return false; }
        });
        if (visibleFillIds.length > 0) {
            const features = map.queryRenderedFeatures(e.point, { layers: visibleFillIds });
            features.forEach(f => {
                const meta = layerPopupMeta[f.layer.id];
                if (!meta) return;
                const color = (meta.colorProp && f.properties[meta.colorProp]) || meta.defaultColor || '#aaa';
                hits.push({ html: meta.popupFn(f.properties), label: meta.label, color });
            });
        }

        if (hits.length > 0) {
            popup.setLngLat(e.lngLat).setHTML(buildPopupHTML(hits)).addTo(map);
        } else if (popup.isOpen()) {
            popup.remove();
        }
    });

    map.on('mouseenter', 'gl-ler-fill',      () => { map.getCanvas().style.cursor = 'pointer'; });
    map.on('mouseleave', 'gl-ler-fill',      () => { map.getCanvas().style.cursor = ''; });

    // ---- Panel de capas ----
    function buildLayerPanel() {
        const panel = document.getElementById('layer-panel');
        panel.innerHTML = '';

        if (STYLES.length > 1) {
            const t = document.createElement('div');
            t.className = 'lp-title'; t.textContent = 'Mapa base';
            panel.appendChild(t);
            STYLES.forEach(s => {
                const lbl = document.createElement('label');
                lbl.className = 'lp-row';
                const radio = document.createElement('input');
                radio.type = 'radio'; radio.name = 'base-style'; radio.value = s.id;
                radio.checked = s.id === currentStyleId;
                radio.addEventListener('change', () => {
                    currentStyleId = s.id;
                    map.setStyle(s.url ?? noTokenDarkStyle);
                });
                lbl.appendChild(radio);
                lbl.appendChild(document.createTextNode(' ' + s.label));
                panel.appendChild(lbl);
            });
        }

        const groups = Object.entries(layerGroups);
        if (groups.length > 0) {
            const t = document.createElement('div');
            t.className = 'lp-title'; t.style.marginTop = '10px'; t.textContent = 'Capas';
            panel.appendChild(t);
            groups.forEach(([groupId, group]) => {
                const lbl = document.createElement('label');
                lbl.className = 'lp-row';
                const cb = document.createElement('input');
                cb.type = 'checkbox'; cb.checked = true;
                cb.addEventListener('change', () => {
                    const vis = cb.checked ? 'visible' : 'none';
                    group.ids.forEach(id => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', vis); });
                    group.markers.forEach(m => { m.marker.getElement().style.display = cb.checked ? '' : 'none'; });
                });
                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(' ' + group.label));
                panel.appendChild(lbl);
            });
        }
    }

    // ---- Marcador de posición (config) ----
    if (lat !== null) {
        const el = document.createElement('div');
        el.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 16 16" fill="#0d6efd" style="filter:drop-shadow(0 2px 4px rgba(0,0,0,.5))">'
            + '<path d="M6.428 1.151C6.708.591 7.213 0 8 0s1.292.592 1.572 1.151C9.861 1.73 10 2.431 10 3v3.691l5.17 2.585a1.5 1.5 0 0 1 .83 1.342V12a.5.5 0 0 1-.582.493l-5.507-.918-.375 2.253 1.318 1.318A.5.5 0 0 1 10.5 16h-5a.5.5 0 0 1-.354-.854l1.319-1.318-.376-2.253-5.507.918A.5.5 0 0 1 0 12v-1.382a1.5 1.5 0 0 1 .83-1.342L6 6.691V3c0-.568.14-1.271.428-1.849"/>'
            + '</svg>';
        const homePopup = new mapboxgl.Popup({ offset: 14 })
            .setHTML(icao ? '<strong>' + icao + '</strong>' : 'Mi posición');
        new mapboxgl.Marker({ element: el, anchor: 'center' })
            .setLngLat([lon, lat])
            .setPopup(homePopup)
            .addTo(map);
    }

    // Cursor pointer al pasar sobre genéricos (se añaden dinámicamente)
    map.on('mousemove', e => {
        if (fillLayerIds.length === 0) return;
        const f = map.queryRenderedFeatures(e.point, { layers: fillLayerIds });
        map.getCanvas().style.cursor = f.length > 0 ? 'pointer' : '';
    });

})();
</script>

</body>
</html>
