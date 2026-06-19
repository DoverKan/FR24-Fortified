<?php
require_once __DIR__ . '/../config/config.php';

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// CONFIGURACIÓN — edita únicamente estas líneas
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$SBS_IP       = '192.168.1.11';   // IP del feeder FlightRadar24 en la red local
$MAPBOX_TOKEN = defined('MAPBOX_TOKEN') ? MAPBOX_TOKEN : '';
$WEATHER_ICAO = defined('ICAO') ? ICAO : '';

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$action = $_GET['action'] ?? '';

// ── SSE proxy: retransmite el stream TCP SBS como Server-Sent Events ────────
if ($action === 'stream') {
    set_time_limit(0);
    ignore_user_abort(true);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    if (ob_get_level()) ob_end_clean();

    $fp = @fsockopen($SBS_IP, 30003, $errno, $errstr, 5);
    if (!$fp) {
        echo 'data: ' . json_encode(['error' => "No se puede conectar a {$SBS_IP}:30003 — {$errstr}"]) . "\n\n";
        flush();
        exit;
    }
    stream_set_timeout($fp, 60);

    while (!feof($fp)) {
        if (connection_aborted()) break;
        $line = fgets($fp, 1024);
        if ($line !== false) {
            $line = trim($line);
            if ($line !== '') {
                echo 'data: ' . json_encode($line) . "\n\n";
                flush();
            }
        }
    }
    fclose($fp);
    exit;
}

// ── Ping ─────────────────────────────────────────────────────────────────────
if ($action === 'ping') {
    header('Content-Type: application/json');
    $fp = @fsockopen($SBS_IP, 30003, $errno, $errstr, 2);
    if ($fp) { fclose($fp); echo json_encode(['online' => true]); }
    else      {             echo json_encode(['online' => false]); }
    exit;
}

// ── Stats feeder FR24 (puerto 8754) ─────────────────────────────────────────
if ($action === 'stats') {
    header('Content-Type: application/json');
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $raw = @file_get_contents("http://{$SBS_IP}:8754/monitor.json", false, $ctx);
    if ($raw === false) {
        echo json_encode(['fr24_feeding' => null]);
        exit;
    }
    $d = json_decode($raw, true) ?? [];
    $connState = strtolower($d['connection_state'] ?? $d['feed_status'] ?? '');
    $latRaw = $d['gps_lat'] ?? $d['lat'] ?? null;
    $lonRaw = $d['gps_lon'] ?? $d['lon'] ?? null;
    echo json_encode([
        'fr24_feeding'    => in_array($connState, ['connected', 'yes', '1']),
        'aircraft_1090'   => $d['d11_conn']      ?? $d['rx_connected']   ?? null,
        'temperature'     => isset($d['cpu_temp']) ? $d['cpu_temp'] . ' °C' : null,
        'radar_code'      => $d['station_id']    ?? $d['fr24_id']        ?? null,
        'version'         => $d['build_version'] ?? null,
        'uptime'          => isset($d['uptime']) ? gmdate('H\h i\m', (int)$d['uptime']) : null,
        'partition_usage' => $d['disk_usage']    ?? null,
        'mac_address'     => $d['mac_address']   ?? null,
        'external_ip'     => $d['external_ip']   ?? null,
        'internal_ip'     => $d['internal_ip']   ?? null,
        'dns_public'      => null,
        'dns_configured'  => null,
        'gps_status'      => $d['gps_status']    ?? null,
        'satellites'      => $d['gps_sats']      ?? null,
        'gps_position'    => ($latRaw && $lonRaw) ? "{$latRaw}, {$lonRaw}" : null,
        'signal_levels'   => $d['signal_levels'] ?? null,
    ]);
    exit;
}

// ── Meteorología LEBZ (aviationweather.gov) ──────────────────────────────────
if ($action === 'weather') {
    header('Content-Type: application/json');
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $metarRaw = @file_get_contents("https://aviationweather.gov/api/data/metar?ids={$WEATHER_ICAO}&format=json", false, $ctx);
    $tafRaw   = @file_get_contents("https://aviationweather.gov/api/data/taf?ids={$WEATHER_ICAO}&format=json",   false, $ctx);

    $metar = null;
    $taf   = null;

    if ($metarRaw !== false) {
        $md = json_decode($metarRaw, true);
        if (!empty($md[0]['rawOb'])) {
            $metar = ['raw' => $md[0]['rawOb'], 'issued_at' => $md[0]['reportTime'] ?? null];
        }
    }
    if ($tafRaw !== false) {
        $td = json_decode($tafRaw, true);
        if (!empty($td[0]['rawTAF'])) {
            $taf = ['raw' => $td[0]['rawTAF'], 'issued_at' => $td[0]['issueTime'] ?? null];
        }
    }
    echo json_encode(['metar' => $metar, 'taf' => $taf]);
    exit;
}

// ── Cargar todos los GeoJSON desde ./geojson/ ────────────────────────────────
$allFeatures = [];
foreach (glob(__DIR__ . '/geojson/*.geojson') ?: [] as $path) {
    $gd = json_decode(file_get_contents($path), true) ?? [];
    foreach ($gd['features'] ?? [] as $f) {
        $allFeatures[] = $f;
    }
}
$ALL_GEOJSON_INLINE = json_encode(array_values($allFeatures));
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Monitor ADS-B LEBZ</title>
    <script>window.tailwind_config={darkMode:'class'};</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>if(window.tailwind)tailwind.config={darkMode:'class'};</script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <style>
        body { background:#0f172a; }
        #ac-map .leaflet-popup-content-wrapper { background:#1e293b; color:#e2e8f0; border:1px solid #334155; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.5); }
        #ac-map .leaflet-popup-tip            { background:#1e293b; }
        #ac-map .leaflet-popup-close-button   { color:#94a3b8 !important; }
        #ac-tbody tr:hover td                 { background:rgba(59,130,246,.08); }
        #ac-map:-webkit-full-screen           { height:100vh!important; }
        #ac-map:-moz-full-screen              { height:100vh!important; }
        #ac-map:fullscreen                    { height:100vh!important; }
        .ac-map-tools { display:flex; flex-direction:column; gap:5px; }
        .ac-map-tool-btn { width:30px;height:30px;background:#fff;border:2px solid rgba(0,0,0,.2);border-radius:4px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#333; }
        .ac-map-tool-btn:hover  { background:#f4f4f4; }
        .ac-map-tool-btn.active { background:#dbeafe; border-color:#60a5fa; color:#1d4ed8; }
        .ac-follow-badge { position:absolute;right:10px;top:10px;z-index:500;background:rgba(15,23,42,.88);color:#cbd5e1;border:1px solid rgba(148,163,184,.35);border-radius:999px;padding:4px 10px;font-size:11px;font-weight:600;letter-spacing:.2px;pointer-events:none; }
        .ac-follow-badge.on { color:#86efac; border-color:rgba(34,197,94,.5); }
        .ac-layer-panel { background:rgba(15,23,42,.94);border:1px solid rgba(255,255,255,.13);border-radius:6px;padding:8px 10px;min-width:160px;box-shadow:0 4px 16px rgba(0,0,0,.5); }
        .ac-layer-row  { display:flex;align-items:center;gap:8px;padding:4px 2px;cursor:pointer;font-size:12px;color:#cbd5e1;font-family:ui-sans-serif,system-ui,sans-serif;user-select:none;white-space:nowrap; }
        .ac-layer-row:hover { color:#f1f5f9; }
        .ac-layer-row input[type=checkbox] { width:13px;height:13px;accent-color:#60a5fa;cursor:pointer;flex-shrink:0; }
        .ac-layer-dot { width:18px;height:4px;border-radius:2px;flex-shrink:0; }
        @keyframes emergency-blink { 0%,100%{opacity:1} 50%{opacity:.35} }
        .ac-emergency-pulse { animation:emergency-blink .7s ease-in-out infinite; }
        .ring-label.leaflet-tooltip            { background:rgba(0,0,0,.65)!important;border:none!important;color:#475569;font-size:10px;font-family:monospace;padding:1px 5px!important;box-shadow:none!important; }
        .ring-label.leaflet-tooltip::before   { display:none!important; }
        .geo-polygon-label.leaflet-tooltip     { background:rgba(0,0,0,.55)!important;border:none!important;font-size:10px;font-family:monospace;font-weight:700;padding:2px 6px!important;box-shadow:none!important; }
        .geo-polygon-label.leaflet-tooltip::before { display:none!important; }
        .sensitive-value  { transition:filter .18s ease, opacity .18s ease; }
        .sensitive-hidden { filter:blur(5px); opacity:.55; user-select:none; }
    </style>
</head>
<body class="min-h-screen text-gray-100" style="background:#0f172a">
<div class="w-full px-4 py-6">

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-100">FlightRadar24</h1>
                <p class="text-gray-400 mt-1">Monitor ADS-B — LEBZ</p>
            </div>
            <div id="ping-dot" class="w-4 h-4 rounded-full bg-yellow-400 animate-pulse ml-2 transition-all duration-500" title="Estado del feeder"></div>
            <span id="ping-label" class="text-xs font-medium text-gray-500">Comprobando…</span>
        </div>
        <div class="flex items-center gap-2">
            <span id="last-update" class="text-xs text-gray-500"></span>
            <button id="toggle-sensitive-all" type="button" onclick="toggleSensitive()"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-600 px-3 py-2 text-sm font-medium text-gray-300 transition-colors hover:bg-gray-700 hover:text-gray-100"
                title="Ocultar datos sensibles" aria-label="Ocultar datos sensibles" aria-pressed="false">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span class="hidden sm:inline">Datos sensibles</span>
            </button>
            <button id="refresh-btn" onclick="loadStats()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors">
                <svg id="refresh-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Actualizar
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="error-banner" style="display:none;background:rgba(127,29,29,.2);border-color:#991b1b;color:#fca5a5"
         class="mb-4 px-4 py-3 rounded-lg border text-sm">
    </div>

    <!-- Emergency banner -->
    <div id="emergency-banner" style="display:none"
         class="mb-4 px-4 py-3 rounded-lg bg-red-600 border border-red-400 text-white text-sm font-semibold flex items-center gap-3">
        <span class="text-lg leading-none">⚠</span>
        <span>SQUAWK EMERGENCIA:</span>
        <span id="emergency-list" class="font-mono"></span>
    </div>

    <!-- Consola SBS -->
    <div class="mb-6 rounded-xl overflow-hidden border border-gray-700">
        <div class="px-4 py-2 bg-gray-800 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div id="sbs-dot" class="w-2 h-2 rounded-full" style="background:#facc15;box-shadow:0 0 5px #facc15"></div>
                <span class="text-xs text-gray-400 font-mono">Raw SBS — Puerto 30003</span>
            </div>
            <div class="flex items-center gap-1.5">
                <button id="sbs-btn-pause" onclick="sbsTogglePause()"
                    class="px-2.5 py-1 text-xs rounded bg-yellow-700 hover:bg-yellow-600 text-white font-medium transition-colors">Pausar</button>
                <button onclick="sbsStop()"
                    class="px-2.5 py-1 text-xs rounded bg-red-700 hover:bg-red-600 text-white font-medium transition-colors">Detener</button>
                <button onclick="sbsReconnect()"
                    class="px-2.5 py-1 text-xs rounded bg-green-700 hover:bg-green-600 text-white font-medium transition-colors">Reconectar</button>
                <button onclick="sbsClear()"
                    class="px-2.5 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-white font-medium transition-colors">Limpiar</button>
            </div>
        </div>
        <div id="sbs-console" class="h-48 overflow-y-auto font-mono text-xs p-3 leading-5" style="background:#000;color:#4ade80">
            <div id="sbs-content" style="color:#6b7280">— Auto-conectando… —</div>
        </div>
    </div>

    <!-- METAR / TAFOR -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-sm p-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500"><?= htmlspecialchars($WEATHER_ICAO) ?></p>
                    <h3 class="text-sm font-semibold text-gray-100">METAR</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span id="metar-issued" class="text-[11px] font-mono text-gray-400">Cargando…</span>
                    <button type="button" onclick="loadWeather('metar')"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-600 px-2 py-1 text-[11px] font-medium text-gray-400 transition-colors hover:bg-gray-700" title="Actualizar METAR">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                    <button type="button" onclick="copyWeather('metar')"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-600 px-2 py-1 text-[11px] font-medium text-gray-400 transition-colors hover:bg-gray-700" title="Copiar METAR">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="10" height="10" rx="2" ry="2"></rect>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15V7a2 2 0 0 1 2-2h8"></path>
                        </svg>
                        Copiar
                    </button>
                </div>
            </div>
            <pre id="metar-raw" class="whitespace-pre-wrap break-words rounded-lg border border-gray-700 px-3 py-3 text-[11px] leading-5 font-mono text-gray-200" style="background:rgba(15,23,42,.6)">Cargando METAR…</pre>
        </div>
        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-sm p-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">LEBZ</p>
                    <h3 class="text-sm font-semibold text-gray-100">TAFOR</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span id="taf-issued" class="text-[11px] font-mono text-gray-400">Cargando…</span>
                    <button type="button" onclick="loadWeather('taf')"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-600 px-2 py-1 text-[11px] font-medium text-gray-400 transition-colors hover:bg-gray-700" title="Actualizar TAFOR">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                    <button type="button" onclick="copyWeather('taf')"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-600 px-2 py-1 text-[11px] font-medium text-gray-400 transition-colors hover:bg-gray-700" title="Copiar TAFOR">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="10" height="10" rx="2" ry="2"></rect>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15V7a2 2 0 0 1 2-2h8"></path>
                        </svg>
                        Copiar
                    </button>
                </div>
            </div>
            <pre id="taf-raw" class="whitespace-pre-wrap break-words rounded-lg border border-gray-700 px-3 py-3 text-[11px] leading-5 font-mono text-gray-200" style="background:rgba(15,23,42,.6)">Cargando TAFOR…</pre>
        </div>
    </div>

    <!-- Tarjetas feeder -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <div id="feed-dot" class="w-3 h-3 rounded-full bg-gray-300 mb-2"></div>
            <p id="feed-text" class="text-sm font-semibold text-gray-400">—</p>
            <p class="text-xs text-gray-500 mt-0.5">FR24 Feed</p>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="aircraft-1090" class="text-3xl font-bold text-gray-100">—</p>
            <p class="text-xs text-gray-500 mt-1">Aviones 1090 MHz</p>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="temperature" class="text-3xl font-bold text-gray-100">—</p>
            <p class="text-xs text-gray-500 mt-1">Temperatura</p>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="radar-code" class="sensitive-value text-xl font-bold font-mono text-gray-100">—</p>
            <p class="text-xs text-gray-500 mt-1">Radar code</p>
        </div>
    </div>

    <!-- Stream stats -->
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="ac-total" class="text-3xl font-bold text-blue-400">0</p>
            <p class="text-xs text-gray-500 mt-1">Aviones detectados</p>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="ac-pos" class="text-3xl font-bold text-green-400">0</p>
            <p class="text-xs text-gray-500 mt-1">Con posición</p>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-4 flex flex-col items-center justify-center text-center">
            <p id="ac-msgrate" class="text-3xl font-bold text-gray-100">0</p>
            <p class="text-xs text-gray-500 mt-1">Mensajes / min</p>
        </div>
    </div>

    <!-- Paneles detalle -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-3">Sistema</p>
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Versión</span>
                    <span id="version" class="font-mono font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Uptime</span>
                    <span id="uptime" class="font-mono text-xs font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Partición</span>
                    <span id="partition" class="font-mono text-xs font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">MAC</span>
                    <span id="mac" class="sensitive-value font-mono text-xs font-semibold text-gray-100">—</span>
                </div>
            </div>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-3">Red</p>
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">IP externa</span>
                    <span id="ext-ip" class="sensitive-value font-mono text-xs font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">IP interna</span>
                    <span id="int-ip" class="sensitive-value font-mono text-xs font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">DNS público</span>
                    <span id="dns-pub" class="font-mono font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">DNS config.</span>
                    <span id="dns-cfg" class="font-mono font-semibold text-gray-100">—</span>
                </div>
            </div>
        </div>
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-3">GPS</p>
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Estado</span>
                    <span id="gps-status" class="font-mono font-semibold text-gray-100">—</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">Satélites</span>
                    <span id="gps-sats" class="font-mono font-semibold text-gray-100">—</span>
                </div>
                <div class="flex flex-col gap-0.5 text-sm">
                    <span class="text-gray-400">Posición</span>
                    <span id="gps-pos" class="sensitive-value font-mono text-xs font-semibold text-gray-100 break-all">—</span>
                </div>
                <div class="flex flex-col gap-0.5 text-sm">
                    <span class="text-gray-400">Señal</span>
                    <span id="gps-signal" class="font-mono text-xs text-gray-300 break-all">—</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa + Tabla -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
        <div class="lg:col-span-3 rounded-xl overflow-hidden border border-gray-700 shadow-sm" style="height:clamp(520px,74vh,920px)">
            <div id="ac-map" style="height:100%;width:100%;background:#0f172a"></div>
        </div>
        <div class="lg:col-span-2 bg-gray-800 rounded-xl border border-gray-700 shadow-sm overflow-hidden flex flex-col" style="height:clamp(520px,74vh,920px)">
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-700">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Aviones en tiempo real</span>
                <span id="ac-table-count" class="text-xs text-gray-500"></span>
            </div>
            <div class="px-3 py-2 border-b border-gray-700">
                <input id="ac-filter" type="text" placeholder="Filtrar callsign / ICAO…"
                       class="w-full text-xs rounded-md px-2.5 py-1.5 bg-gray-700 border border-gray-600 text-gray-300 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-400"/>
            </div>
            <div class="overflow-auto flex-1">
                <table class="w-full">
                    <thead class="sticky top-0 z-10 bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">ICAO</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Callsign</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">Alt ft</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">kt</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">Hdg</th>
                        </tr>
                    </thead>
                    <tbody id="ac-tbody" class="divide-y divide-gray-700">
                        <tr><td colspan="5" class="px-3 py-8 text-center text-xs text-gray-400">Conectando al stream…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /max-w-7xl -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    // ── Constantes ────────────────────────────────────────────────────────────
    const STREAM_URL         = '?action=stream';
    const STATS_URL          = '?action=stats';
    const PING_URL           = '?action=ping';
    const WEATHER_URL        = '?action=weather';
    const ALL_GEOJSON_INLINE = <?= $ALL_GEOJSON_INLINE ?>;
    const MAPBOX_TOKEN       = <?= json_encode($MAPBOX_TOKEN) ?>;
    const SBS_IP             = <?= json_encode($SBS_IP) ?>;
    const MAP_STYLE_KEY      = 'fr24-mapbox-style';
    const SENSITIVE_PREF_KEY = 'fr24-sensitive-visibility';
    const SENSITIVE_FIELDS   = ['radar-code', 'mac', 'ext-ip', 'int-ip', 'gps-pos'];

    // ── Datos sensibles ───────────────────────────────────────────────────────
    function getSensitiveVisible() {
        try { const v = JSON.parse(localStorage.getItem(SENSITIVE_PREF_KEY) ?? 'true'); return typeof v === 'boolean' ? v : true; } catch(_) { return true; }
    }
    function setSensitiveVisible(v) { try { localStorage.setItem(SENSITIVE_PREF_KEY, JSON.stringify(v)); } catch(_) {} }
    function applySensitiveState(visible) {
        SENSITIVE_FIELDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('sensitive-hidden', !visible);
        });
        const btn = document.getElementById('toggle-sensitive-all');
        if (btn) {
            btn.title = (visible ? 'Ocultar' : 'Mostrar') + ' datos sensibles';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        }
    }
    function toggleSensitive() { const v = !getSensitiveVisible(); setSensitiveVisible(v); applySensitiveState(v); }

    // ── Ping ──────────────────────────────────────────────────────────────────
    function checkPing() {
        fetch(PING_URL).then(r => r.json()).then(d => setPingState(d.online ? 'online' : 'offline')).catch(() => setPingState('offline'));
    }
    function setPingState(state) {
        const dot   = document.getElementById('ping-dot');
        const label = document.getElementById('ping-label');
        if (!dot) return;
        if (state === 'online') {
            dot.className   = 'w-4 h-4 rounded-full bg-green-500 shadow-[0_0_8px_2px_rgba(34,197,94,0.6)] ml-2 transition-all duration-500';
            if (label) { label.textContent = 'Encendido'; label.className = 'text-xs font-medium text-green-400'; }
        } else {
            dot.className   = 'w-4 h-4 rounded-full bg-red-500 shadow-[0_0_8px_2px_rgba(239,68,68,0.6)] ml-2 transition-all duration-500';
            if (label) { label.textContent = 'Apagado'; label.className = 'text-xs font-medium text-red-400'; }
        }
    }
    checkPing();
    setInterval(checkPing, 30000);

    // ── Stats ─────────────────────────────────────────────────────────────────
    function loadStats() {
        const btn  = document.getElementById('refresh-btn');
        const icon = document.getElementById('refresh-icon');
        if (btn)  btn.disabled = true;
        if (icon) icon.classList.add('animate-spin');
        document.getElementById('error-banner').style.display = 'none';
        fetch(STATS_URL)
            .then(r => r.json())
            .then(d => {
                renderStats(d);
                const el = document.getElementById('last-update');
                if (el) el.textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            })
            .catch(e => showError('Error de conexión: ' + e.message))
            .finally(() => {
                if (btn)  btn.disabled = false;
                if (icon) icon.classList.remove('animate-spin');
            });
    }
    function showError(msg) {
        const el = document.getElementById('error-banner');
        if (el) { el.textContent = msg; el.style.display = ''; el.style.cssText = 'display:block;margin-bottom:1rem;padding:.75rem 1rem;border-radius:.5rem;background:rgba(127,29,29,.2);border:1px solid #991b1b;color:#fca5a5;font-size:.875rem'; }
    }
    function renderStats(d) {
        if (d.fr24_feeding !== null && d.fr24_feeding !== undefined) {
            const feeding = d.fr24_feeding === true;
            const dot  = document.getElementById('feed-dot');
            const text = document.getElementById('feed-text');
            if (dot)  dot.className  = 'w-3 h-3 rounded-full mb-2 ' + (feeding ? 'bg-green-500' : 'bg-red-500');
            if (text) { text.className = 'text-sm font-semibold ' + (feeding ? 'text-green-400' : 'text-red-400'); text.textContent = feeding ? 'Activo' : 'Inactivo'; }
        }
        if (d.aircraft_1090 != null) setText('aircraft-1090', d.aircraft_1090);
        setText('temperature',   d.temperature   ?? '—');
        setText('radar-code',    d.radar_code    ?? '—');
        setText('version',       d.version          ?? '—');
        setText('uptime',        d.uptime            ?? '—');
        setText('partition',     d.partition_usage   ?? '—');
        setText('mac',           d.mac_address       ?? '—');
        setText('ext-ip',   d.external_ip    ?? '—');
        setText('int-ip',   d.internal_ip    ?? '—');
        setText('dns-pub',  d.dns_public     ?? '—');
        setText('dns-cfg',  d.dns_configured ?? '—');
        const gpsOk = (d.gps_status || '').toUpperCase() === 'OK';
        const gpsEl = document.getElementById('gps-status');
        if (gpsEl) { gpsEl.textContent = d.gps_status ?? '—'; gpsEl.className = 'font-mono font-semibold ' + (gpsOk ? 'text-green-400' : 'text-yellow-400'); }
        setText('gps-sats',   d.satellites    ?? '—');
        setText('gps-pos',    d.gps_position  ?? '—');
        setText('gps-signal', d.signal_levels ?? '—');
    }
    function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val ?? '—'; }

    // ── Meteorología ─────────────────────────────────────────────────────────
    function renderWeatherCard(prefix, report, emptyText) {
        setText(prefix + '-issued', report?.issued_at ?? '—');
        setText(prefix + '-raw', report?.raw ?? emptyText);
    }
    function setWeatherLoading(prefix, label) {
        setText(prefix + '-issued', 'Actualizando…');
        setText(prefix + '-raw', label);
    }
    function copyWeather(prefix) {
        const raw = document.getElementById(prefix + '-raw')?.textContent?.trim();
        if (!raw) return;
        navigator.clipboard.writeText(raw).then(() => {
            const el = document.getElementById(prefix + '-issued');
            if (!el) return;
            const prev = el.textContent; el.textContent = 'Copiado'; setTimeout(() => { el.textContent = prev; }, 1200);
        }).catch(() => {});
    }
    function loadWeather(target = null) {
        if (target === 'metar')      setWeatherLoading('metar', 'Actualizando METAR…');
        else if (target === 'taf')   setWeatherLoading('taf',   'Actualizando TAFOR…');
        else { setWeatherLoading('metar', 'Cargando METAR…'); setWeatherLoading('taf', 'Cargando TAFOR…'); }
        fetch(WEATHER_URL)
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.error || 'No se pudo obtener meteorología');
                renderWeatherCard('metar', data.metar, 'METAR no disponible');
                renderWeatherCard('taf',   data.taf,   'TAFOR no disponible');
            })
            .catch(err => {
                renderWeatherCard('metar', null, err.message);
                renderWeatherCard('taf',   null, err.message);
            });
    }

    loadStats();
    setInterval(loadStats, 30000);
    loadWeather();
    setInterval(loadWeather, 300000);

    // ── Estado aviones ────────────────────────────────────────────────────────
    const AC        = {};
    let msgCount    = 0;
    let msgT0       = Date.now();
    let acMap       = null;
    let lgTracks    = null;
    const acMarkers = {};
    const acTrails  = {};
    let selectedHex   = null;
    let followSelected = false;
    let firstFit      = false;
    let userPanned    = false;
    let baseMapLayer  = null;

    // ── Mapa Leaflet ──────────────────────────────────────────────────────────
    const LEBZ_POS = [38.8913, -6.8211];
    const MAPBOX_STYLES = [
        { id: 'dark',      label: 'Dark',      url: 'mapbox/dark-v11',              short: 'D' },
        { id: 'streets',   label: 'Streets',   url: 'mapbox/streets-v12',           short: 'S' },
        { id: 'satellite', label: 'Satellite', url: 'mapbox/satellite-streets-v12', short: 'A' },
    ];

    function getSavedMapStyleId() {
        const fallback = MAPBOX_STYLES[0].id;
        try { const v = localStorage.getItem(MAP_STYLE_KEY) || fallback; return MAPBOX_STYLES.some(s => s.id === v) ? v : fallback; } catch(_) { return fallback; }
    }
    function setSavedMapStyleId(id) { try { localStorage.setItem(MAP_STYLE_KEY, id); } catch(_) {} }
    function getMapStyleById(id) { return MAPBOX_STYLES.find(s => s.id === id) || MAPBOX_STYLES[0]; }

    function applyBaseMapStyle(styleId) {
        if (!acMap) return null;
        if (baseMapLayer) { acMap.removeLayer(baseMapLayer); baseMapLayer = null; }
        if (MAPBOX_TOKEN) {
            const style = getMapStyleById(styleId);
            baseMapLayer = L.tileLayer('https://api.mapbox.com/styles/v1/{styleId}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
                styleId: style.url, accessToken: MAPBOX_TOKEN, tileSize: 512, zoomOffset: -1,
                maxZoom: 22, crossOrigin: true, attribution: '© OpenStreetMap © Mapbox',
            }).addTo(acMap);
            setSavedMapStyleId(style.id);
            return style;
        }
        baseMapLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd', maxZoom: 19, crossOrigin: true, attribution: '© OpenStreetMap © CARTO',
        }).addTo(acMap);
        return null;
    }

    function renderDescripcion(desc) {
        if (desc == null) return '—';
        if (typeof desc === 'string') return desc || '—';
        const entries = Object.entries(desc).filter(([, v]) => v !== '' && v != null);
        if (!entries.length) return '—';
        return entries.map(([k, v]) => `<b>${k}:</b> ${v}`).join('<br>');
    }

    function resolveColor(fill, def) { return (!fill || fill === 'a') ? def : fill; }

    function initMap() {
        acMap = L.map('ac-map', { zoomControl: true, attributionControl: false, scrollWheelZoom: false });
        const initialMapStyle = applyBaseMapStyle(getSavedMapStyleId());
        if (!initialMapStyle) sbsAppend('Mapbox no configurado, usando CARTO como respaldo.', '#facc15');

        L.DomEvent.disableScrollPropagation(acMap.getContainer());
        L.DomEvent.disableClickPropagation(acMap.getContainer());
        L.DomEvent.on(acMap.getContainer(), 'touchmove', L.DomEvent.preventDefault);
        acMap.getContainer().addEventListener('wheel', function(e) {
            e.preventDefault();
            if (!e.ctrlKey) return;
            const current = acMap.getZoom();
            const delta   = e.deltaY < 0 ? 1 : -1;
            const target  = Math.max(acMap.getMinZoom(), Math.min(acMap.getMaxZoom(), current + delta));
            const point   = acMap.mouseEventToContainerPoint(e);
            const latlng  = acMap.containerPointToLatLng(point);
            acMap.setZoomAround(latlng, target, { animate: false });
        }, { passive: false });

        L.control.attribution({ prefix: false }).addTo(acMap);
        acMap.setView(LEBZ_POS, 8);
        acMap.on('dragstart', () => { userPanned = true; });

        // ── Grupos de capas ────────────────────────────────────────────────
        const lgRings  = L.layerGroup().addTo(acMap);
        const geoLayers = {};
        lgTracks = L.layerGroup().addTo(acMap);
        const followBadge = L.DomUtil.create('div', 'ac-follow-badge', acMap.getContainer());
        followBadge.textContent = 'Seguimiento: OFF';

        function clearAllTracks() {
            Object.keys(acTrails).forEach(hex => { acTrails[hex].polyline.remove(); delete acTrails[hex]; });
        }

        // ── Captura de pantalla ────────────────────────────────────────────
        function loadScript(src) {
            return new Promise((resolve, reject) => {
                const s = document.createElement('script'); s.src = src; s.async = true;
                s.onload = () => resolve(true); s.onerror = () => reject(new Error('No se pudo cargar ' + src));
                document.head.appendChild(s);
            });
        }
        async function ensureHtml2Canvas() {
            if (typeof window.html2canvas === 'function') return window.html2canvas;
            const sources = [
                'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
                'https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js',
            ];
            for (const src of sources) {
                try { await loadScript(src); if (typeof window.html2canvas === 'function') return window.html2canvas; } catch(_) {}
            }
            return null;
        }
        function closeCapturePreview() { const el = document.getElementById('capture-preview-modal'); if (el) el.remove(); }
        function showCapturePreview(blob, filename) {
            closeCapturePreview();
            const objectUrl = URL.createObjectURL(blob);
            const modal = document.createElement('div');
            modal.id = 'capture-preview-modal';
            modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(2,6,23,.82);display:flex;align-items:center;justify-content:center;padding:16px;';
            const card = document.createElement('div');
            card.style.cssText = 'width:min(980px,96vw);max-height:92vh;overflow:auto;background:#0f172a;border:1px solid #334155;border-radius:12px;box-shadow:0 24px 60px rgba(0,0,0,.6);padding:12px;';
            const header = document.createElement('div');
            header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;color:#cbd5e1;font:600 12px ui-sans-serif,system-ui,sans-serif;';
            header.textContent = 'Captura generada';
            const actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:8px;';
            const dl = document.createElement('a');
            dl.href = objectUrl; dl.download = filename; dl.textContent = 'Descargar PNG';
            dl.style.cssText = 'text-decoration:none;background:#2563eb;color:#fff;border:1px solid #1d4ed8;border-radius:8px;padding:6px 10px;font:600 12px ui-sans-serif,system-ui,sans-serif;';
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button'; closeBtn.textContent = 'Cerrar';
            closeBtn.style.cssText = 'background:#1e293b;color:#cbd5e1;border:1px solid #475569;border-radius:8px;padding:6px 10px;font:600 12px ui-sans-serif,system-ui,sans-serif;cursor:pointer;';
            const img = document.createElement('img');
            img.src = objectUrl; img.alt = 'Captura del mapa';
            img.style.cssText = 'display:block;width:100%;height:auto;border-radius:8px;border:1px solid #334155;background:#020617;';
            actions.appendChild(dl); actions.appendChild(closeBtn); header.appendChild(actions);
            card.appendChild(header); card.appendChild(img); modal.appendChild(card); document.body.appendChild(modal);
            modal.addEventListener('click', ev => { if (ev.target === modal) { closeCapturePreview(); URL.revokeObjectURL(objectUrl); } });
            closeBtn.addEventListener('click', () => { closeCapturePreview(); URL.revokeObjectURL(objectUrl); });
        }
        async function captureMapImage() {
            const target = document.getElementById('ac-map');
            if (!target) { showError('No se encontró el contenedor del mapa.'); return; }
            const h2c = await ensureHtml2Canvas();
            if (!h2c) { showError('No se pudo cargar html2canvas.'); sbsAppend('Error captura mapa: html2canvas no disponible', '#f87171'); return; }
            document.getElementById('error-banner').style.display = 'none';
            sbsAppend('Generando captura de mapa…', '#94a3b8');
            try {
                const canvas = await h2c(target, { useCORS: true, allowTaint: false, backgroundColor: null, logging: false });
                const ts       = new Date().toISOString().replace(/[:.]/g, '-');
                const filename = `fr24-mapa-${ts}.png`;
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                if (blob) {
                    showCapturePreview(blob, filename);
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a'); a.download = filename; a.href = url;
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    sbsAppend('Captura generada.', '#60a5fa'); return;
                }
                const dataUrl = canvas.toDataURL('image/png');
                const opened = window.open(dataUrl, '_blank', 'noopener');
                if (!opened) { showError('El navegador bloqueó la apertura. Permite popups.'); return; }
                sbsAppend('Captura abierta en nueva pestaña.', '#60a5fa');
            } catch(e) { showError('No se pudo capturar el mapa (posible CORS).'); sbsAppend('Error captura: ' + (e?.message || 'desconocido'), '#f87171'); }
        }

        // ── Hit test polígonos ─────────────────────────────────────────────
        function walkLayers(layer, fn) {
            if (layer && typeof layer.eachLayer === 'function') { layer.eachLayer(child => walkLayers(child, fn)); return; }
            fn(layer);
        }
        function pointInRing(latlng, ring) {
            let inside = false; const x = latlng.lng; const y = latlng.lat;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const xi = ring[i].lng; const yi = ring[i].lat; const xj = ring[j].lng; const yj = ring[j].lat;
                const intersects = ((yi > y) !== (yj > y)) && (x < ((xj - xi) * (y - yi)) / ((yj - yi) || 1e-12) + xi);
                if (intersects) inside = !inside;
            }
            return inside;
        }
        function pointInPolygonLayer(latlng, layer) {
            if (!(layer instanceof L.Polygon)) return false;
            const coords = layer.getLatLngs();
            if (!Array.isArray(coords) || !coords.length) return false;
            const isRing = Array.isArray(coords[0]) && coords[0].length && coords[0][0] && coords[0][0].lat !== undefined;
            if (isRing) return pointInRing(latlng, coords[0]);
            for (const poly of coords) {
                if (Array.isArray(poly) && poly.length && Array.isArray(poly[0]) && poly[0].length && poly[0][0]?.lat !== undefined) {
                    if (pointInRing(latlng, poly[0])) return true;
                }
            }
            return false;
        }
        function popupContentToString(c) {
            if (typeof c === 'string') return c;
            if (c instanceof HTMLElement) return c.outerHTML;
            return String(c ?? '');
        }
        function showOverlappingInfo(latlng) {
            const groups = Object.entries(geoLayers)
                .filter(([t]) => TIPO_CONFIG[t]?.polygon)
                .map(([t, layer]) => ({ name: TIPO_CONFIG[t].label, layer }));
            const hits = [];
            groups.forEach(group => {
                if (!acMap.hasLayer(group.layer)) return;
                walkLayers(group.layer, layer => {
                    if (!layer || typeof layer.getPopup !== 'function') return;
                    if (!pointInPolygonLayer(latlng, layer)) return;
                    const popup = layer.getPopup();
                    if (!popup) return;
                    hits.push({ group: group.name, html: popupContentToString(popup.getContent()) });
                });
            });
            if (hits.length <= 1) return false;
            const seen = new Set();
            const rows = hits.filter(h => { const key = `${h.group}::${h.html}`; if (seen.has(key)) return false; seen.add(key); return true; });
            const combinedHtml = `<div style="font-size:12px;line-height:1.6;min-width:260px;max-width:360px">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:6px">Capas superpuestas (${rows.length})</div>
                ${rows.map((h, i) => `<div style="${i ? 'margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,.25);' : ''}"><div style="font-size:11px;color:#fbbf24;margin-bottom:4px">${h.group}</div>${h.html}</div>`).join('')}
            </div>`;
            L.popup({ maxWidth: 420 }).setLatLng(latlng).setContent(combinedHtml).openOn(acMap);
            return true;
        }
        acMap.on('click', e => { showOverlappingInfo(e.latlng); });

        // ── Control de capas ───────────────────────────────────────────────
        let geoPanelRef = null;
        function addLayerRow(container, label, color, lg) {
            const row = L.DomUtil.create('label', 'ac-layer-row', container);
            const cb  = L.DomUtil.create('input', '', row);
            cb.type = 'checkbox'; cb.checked = acMap.hasLayer(lg);
            const dot = L.DomUtil.create('span', 'ac-layer-dot', row);
            dot.style.background = color;
            const txt = L.DomUtil.create('span', '', row);
            txt.textContent = label;
            L.DomEvent.on(cb, 'change', () => { cb.checked ? lg.addTo(acMap) : acMap.removeLayer(lg); });
        }
        function buildGeoLayerPanelRows() {
            if (!geoPanelRef) return;
            const knownOrder = Object.keys(TIPO_CONFIG);
            const sorted = [...knownOrder.filter(t => geoLayers[t]), ...Object.keys(geoLayers).filter(t => !TIPO_CONFIG[t])];
            sorted.forEach(tipo => {
                const cfg = TIPO_CONFIG[tipo] ?? {};
                addLayerRow(geoPanelRef, cfg.label ?? tipo, cfg.color ?? '#94a3b8', geoLayers[tipo]);
            });
        }

        const LayersCtrl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd(map) {
                const wrap = L.DomUtil.create('div', 'ac-map-tools');

                const fsBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                fsBtn.title = 'Pantalla completa';
                fsBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>`;

                const btn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                btn.title = 'Capas del mapa';
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>`;

                const centerBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                centerBtn.title = 'Centrar en LEBZ';
                centerBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></svg>`;

                const followBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                followBtn.title = 'Seguir avión seleccionado';
                followBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9 22 2z"/></svg>`;

                function refreshFollowUi() {
                    followBtn.classList.toggle('active', followSelected);
                    if (!followSelected) { followBadge.textContent = 'Seguimiento: OFF'; followBadge.classList.remove('on'); return; }
                    const ac = selectedHex ? AC[selectedHex] : null;
                    followBadge.textContent = `Seguimiento: ON (${ac?.callsign || selectedHex || 'sin objetivo'})`;
                    followBadge.classList.add('on');
                }

                const clearTracksBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                clearTracksBtn.title = 'Limpiar tracks';
                clearTracksBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>`;

                const captureBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                captureBtn.title = 'Captura del mapa (PNG)';
                captureBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>`;

                const styleBtn = L.DomUtil.create('button', 'ac-map-tool-btn', wrap);
                styleBtn.title = 'Cambiar estilo de mapa';
                styleBtn.textContent = MAPBOX_TOKEN ? getMapStyleById(getSavedMapStyleId()).short : 'C';

                const panel = L.DomUtil.create('div', 'ac-layer-panel', wrap);
                panel.style.display = 'none';
                geoPanelRef = panel;

                [
                    ['Anillos NM',     '#64748b', lgRings ],
                    ['Tracks aviones', '#34d399', lgTracks],
                ].forEach(([label, color, lg]) => addLayerRow(panel, label, color, lg));

                L.DomEvent.on(fsBtn, 'click', e => {
                    L.DomEvent.stopPropagation(e);
                    const el = document.getElementById('ac-map');
                    if (!document.fullscreenElement) el.requestFullscreen?.() || el.webkitRequestFullscreen?.();
                    else document.exitFullscreen?.() || document.webkitExitFullscreen?.();
                });
                L.DomEvent.on(btn, 'click', e => { L.DomEvent.stopPropagation(e); panel.style.display = panel.style.display === 'none' ? 'block' : 'none'; });
                L.DomEvent.on(centerBtn, 'click', e => { L.DomEvent.stopPropagation(e); acMap.setView(LEBZ_POS, Math.min(acMap.getMaxZoom(), acMap.getZoom() + 3), { animate: true }); });
                L.DomEvent.on(followBtn, 'click', e => { L.DomEvent.stopPropagation(e); followSelected = !followSelected; refreshFollowUi(); });
                L.DomEvent.on(clearTracksBtn, 'click', e => { L.DomEvent.stopPropagation(e); clearAllTracks(); });
                L.DomEvent.on(captureBtn, 'click', e => { L.DomEvent.stopPropagation(e); captureMapImage(); });
                L.DomEvent.on(styleBtn, 'click', e => {
                    L.DomEvent.stopPropagation(e);
                    if (!MAPBOX_TOKEN) { showError('No hay token de Mapbox configurado.'); return; }
                    const current = getSavedMapStyleId();
                    const idx  = MAPBOX_STYLES.findIndex(s => s.id === current);
                    const next = MAPBOX_STYLES[(idx + 1) % MAPBOX_STYLES.length];
                    applyBaseMapStyle(next.id);
                    styleBtn.textContent = next.short;
                    sbsAppend('Estilo mapa: ' + next.label, '#60a5fa');
                });

                L.DomEvent.disableClickPropagation(wrap);
                L.DomEvent.disableScrollPropagation(wrap);
                refreshFollowUi();
                return wrap;
            }
        });
        acMap.addControl(new LayersCtrl());

        // ── Anillos de distancia con etiquetas NM ─────────────────────────
        const NM = 1852;
        [
            [10,  2,   .75, .06],
            [25,  1.5, .60, .04],
            [50,  1.5, .50, .03],
            [100, 1.5, .45, .02],
            [150, 1.5, .40, .02],
        ].forEach(([nm, weight, op, fillOp]) => {
            L.circle(LEBZ_POS, {
                radius: nm * NM, color: '#64748b', weight, opacity: op,
                fillColor: '#64748b', fillOpacity: fillOp, dashArray: '6 6',
            }).addTo(lgRings);
            const lonOffset = (nm * NM) / (111320 * Math.cos(LEBZ_POS[0] * Math.PI / 180));
            L.marker([LEBZ_POS[0], LEBZ_POS[1] + lonOffset], {
                icon: L.divIcon({ className: '', html: '', iconSize: [0, 0] }),
                interactive: false,
            }).bindTooltip(`${nm} NM`, { permanent: true, direction: 'right', className: 'ring-label' }).addTo(lgRings);
        });

        // ── TIPO_CONFIG ───────────────────────────────────────────────────
        const TIPO_CONFIG = {
            'VOR':        { label: 'VOR/DME',      color: '#22d3ee', icon: 'vor'     },
            'Aeropuerto': { label: 'Aeropuertos',  color: '#fbbf24', icon: 'airport' },
            'Helipuerto': { label: 'Helipuertos',  color: '#f87171', icon: 'helipad' },
            'Antena':     { label: 'Antenas',      color: '#a78bfa', icon: 'antena'  },
            'VP':         { label: 'Puntos VP',    color: '#f472b6', icon: 'vp'      },
            'M':          { label: 'Manga M10',    color: '#fbbf24', icon: 'vp'      },
            'CTR':        { label: 'CTR',          color: '#e67e22', polygon: 'ctr'  },
            'SEC':        { label: 'Sectores TMA', color: '#f59e0b', polygon: 'sec'  },
            'LER':        { label: 'Zonas LER',    color: '#60a5fa', polygon: 'ler'  },
            'LINEA':      { label: 'Líneas',       color: '#94a3b8', polygon: 'line' },
        };

        // ── Iconos ────────────────────────────────────────────────────────
        function makeVorIcon(id) {
            return L.divIcon({
                className: '',
                html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
                         <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                           <polygon points="13,1 22.5,6.5 22.5,17.5 13,23 3.5,17.5 3.5,6.5" fill="rgba(34,211,238,.12)" stroke="#22d3ee" stroke-width="1.5"/>
                           <line x1="13" y1="3"  x2="13" y2="8"  stroke="#22d3ee" stroke-width="1"/>
                           <line x1="13" y1="18" x2="13" y2="23" stroke="#22d3ee" stroke-width="1"/>
                           <line x1="3"  y1="13" x2="8"  y2="13" stroke="#22d3ee" stroke-width="1"/>
                           <line x1="18" y1="13" x2="23" y2="13" stroke="#22d3ee" stroke-width="1"/>
                           <circle cx="13" cy="13" r="2.5" fill="#22d3ee"/>
                         </svg>
                         <div style="background:rgba(0,0,0,.75);color:#22d3ee;font-size:9px;font-family:monospace;font-weight:700;padding:1px 4px;border-radius:3px;white-space:nowrap;letter-spacing:.5px">${id}</div>
                       </div>`,
                iconSize: [26,42], iconAnchor: [13,13], popupAnchor: [0,-22],
            });
        }
        function makeAptIcon(icao, fill) {
            const c = resolveColor(fill, '#cbd5e1');
            return L.divIcon({
                className: '',
                html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
                         <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22">
                           <circle cx="11" cy="11" r="9" fill="${c}22" stroke="${c}" stroke-width="1.2"/>
                           <line x1="11" y1="4"  x2="11" y2="18" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/>
                           <line x1="6"  y1="11" x2="16" y2="11" stroke="${c}" stroke-width="1.2" stroke-linecap="round"/>
                           <line x1="7.5" y1="15" x2="14.5" y2="15" stroke="${c}" stroke-width="0.9" stroke-linecap="round"/>
                         </svg>
                         <div style="background:rgba(0,0,0,.7);color:${c};font-size:8px;font-family:monospace;font-weight:700;padding:1px 3px;border-radius:3px;white-space:nowrap">${icao}</div>
                       </div>`,
                iconSize: [22,36], iconAnchor: [11,11], popupAnchor: [0,-18],
            });
        }
        function makeVpIcon(nombre, color) {
            const c = color || '#f472b6';
            return L.divIcon({
                className: '',
                html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
                         <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                           <circle cx="12" cy="12" r="8" fill="${c}29" stroke="${c}" stroke-width="1.5"/>
                           <circle cx="12" cy="12" r="2.2" fill="${c}"/>
                         </svg>
                         <div style="background:rgba(0,0,0,.72);color:${c};font-size:8px;font-family:monospace;font-weight:700;padding:1px 3px;border-radius:3px;white-space:nowrap;letter-spacing:.4px">${nombre || '?'}</div>
                       </div>`,
                iconSize: [24,38], iconAnchor: [12,12], popupAnchor: [0,-18],
            });
        }
        function makePointIconSvg(tipo, color) {
            if (tipo === 'Helipuerto') {
                return `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                           <circle cx="13" cy="13" r="10" fill="${color}" fill-opacity="0.18" stroke="${color}" stroke-width="1.6"/>
                           <rect x="11" y="7" width="4" height="12" rx="1" fill="${color}"/>
                           <rect x="7" y="11" width="12" height="4" rx="1" fill="${color}"/>
                         </svg>`;
            }
            if (tipo === 'Antena') {
                return `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                           <line x1="13" y1="20" x2="13" y2="9" stroke="${color}" stroke-width="1.8" stroke-linecap="round"/>
                           <line x1="7"  y1="14" x2="13" y2="9" stroke="${color}" stroke-width="1.4" stroke-linecap="round"/>
                           <line x1="19" y1="14" x2="13" y2="9" stroke="${color}" stroke-width="1.4" stroke-linecap="round"/>
                           <line x1="5"  y1="11" x2="13" y2="7" stroke="${color}" stroke-width="1.1" stroke-linecap="round" stroke-dasharray="2 1.5"/>
                           <line x1="21" y1="11" x2="13" y2="7" stroke="${color}" stroke-width="1.1" stroke-linecap="round" stroke-dasharray="2 1.5"/>
                           <circle cx="13" cy="9" r="2" fill="${color}"/>
                           <line x1="10" y1="20" x2="16" y2="20" stroke="${color}" stroke-width="1.4" stroke-linecap="round"/>
                         </svg>`;
            }
            return `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                       <circle cx="13" cy="13" r="9" fill="${color}" fill-opacity="0.2" stroke="${color}" stroke-width="1.6"/>
                       <circle cx="13" cy="13" r="2.5" fill="${color}"/>
                     </svg>`;
        }
        function makePointIcon(nombre, tipo, color) {
            return L.divIcon({
                className: '',
                html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
                         ${makePointIconSvg(tipo, color)}
                         <div style="background:rgba(0,0,0,.72);color:${color};font-size:8px;font-family:monospace;font-weight:700;padding:1px 3px;border-radius:3px;white-space:nowrap;letter-spacing:.4px">${nombre || '?'}</div>
                       </div>`,
                iconSize: [26,40], iconAnchor: [13,13], popupAnchor: [0,-20],
            });
        }

        // ── Estilos polígonos ─────────────────────────────────────────────
        function parseAltitudeFeet(value) {
            if (value == null) return null;
            const raw = String(value).trim().toUpperCase();
            if (!raw) return null;
            if (raw === 'SFC' || raw === 'GND' || raw === 'GROUND') return 0;
            const fl = raw.match(/FL\s*(\d{2,3})/); if (fl) return parseInt(fl[1], 10) * 100;
            const ft = raw.match(/(\d{3,5})\s*FT/); if (ft) return parseInt(ft[1], 10);
            const nu = raw.match(/\d{3,5}/); if (nu) return parseInt(nu[0], 10);
            return null;
        }
        function buildLerStyle(props, baseColor) {
            const desc = props?.descripcion ?? {};
            const low  = parseAltitudeFeet(desc.inferior ?? props?.inferior);
            const up   = parseAltitudeFeet(desc.superior ?? props?.superior);
            const span = (low != null && up != null) ? Math.max(0, up - low) : null;
            const s = { color:baseColor, weight:1.5, opacity:0.75, fillColor:baseColor, fillOpacity:0.08 };
            if (span == null) return s;
            if (span >= 30000)      { s.fillOpacity=0.2;  s.weight=2.3; s.opacity=0.88; }
            else if (span >= 15000) { s.fillOpacity=0.14; s.weight=2;   s.opacity=0.82; }
            else if (span >= 7000)  { s.fillOpacity=0.11; s.weight=1.8; s.opacity=0.78; }
            else                    { s.fillOpacity=0.08; s.weight=1.6; s.opacity=0.74; }
            return s;
        }
        function styleGeoFeature(feature) {
            const p    = feature.properties ?? {};
            const tipo = p.tipo ?? '';
            const cfg  = TIPO_CONFIG[tipo] ?? {};
            const c    = resolveColor(p.fill, cfg.color ?? '#94a3b8');
            switch (cfg.polygon) {
                case 'ler':  return buildLerStyle(p, c);
                case 'sec':  return { color:c, weight:1.5, opacity:0.65, fillColor:c, fillOpacity:0.07 };
                case 'ctr':  return { color:c, weight:2,   opacity:0.80, fillColor:c, fillOpacity:0.09 };
                case 'line': return { color:c, weight:1.5, opacity:0.65, fillColor:c, fillOpacity:0    };
                default:     return { color:c, weight:1.5, opacity:0.65, fillColor:c, fillOpacity:0.1  };
            }
        }
        function makeGeoIcon(feature, latlng) {
            const p      = feature.properties ?? {};
            const tipo   = p.tipo ?? '';
            const cfg    = TIPO_CONFIG[tipo] ?? {};
            const c      = resolveColor(p.fill, cfg.color ?? '#94a3b8');
            const iconId = (!p.icon || p.icon === 'a') ? (cfg.icon ?? '') : p.icon;
            switch (iconId) {
                case 'vor':     return L.marker(latlng, { icon: makeVorIcon(p.descripcion?.identificador ?? p.nombre ?? '?'), keyboard:false });
                case 'airport': return L.marker(latlng, { icon: makeAptIcon(p.descripcion?.icao ?? p.nombre ?? '?', p.fill), keyboard:false });
                case 'vp':      return L.marker(latlng, { icon: makeVpIcon(p.nombre ?? '?', c), keyboard:false });
                default:        return L.marker(latlng, { icon: makePointIcon(p.nombre ?? '?', tipo, c), keyboard:false });
            }
        }
        function bindGeoFeature(feature, layer) {
            const p    = feature.properties ?? {};
            const tipo = p.tipo ?? '';
            const cfg  = TIPO_CONFIG[tipo] ?? {};
            const c    = resolveColor(p.fill, cfg.color ?? '#94a3b8');
            const lbl  = p.nombre;
            if (!lbl || lbl === 'a') return;
            if (cfg.polygon) {
                const s = styleGeoFeature(feature);
                if (lbl) {
                    layer.bindTooltip(`<span style="font-size:10px;font-weight:700;color:${c}">${lbl}</span>`, {
                        permanent: true, direction: 'center', className: 'geo-polygon-label',
                    });
                }
                layer.on('mouseover', function() { this.setStyle({ fillOpacity: Math.min(0.32, (s.fillOpacity ?? 0.08) + 0.12), weight: (s.weight ?? 1.5) + 0.35 }); });
                layer.on('mouseout',  function() { this.setStyle(s); });
                layer.on('click',     function(e) { setTimeout(() => showOverlappingInfo(e.latlng), 0); });
            }
            const prefix = tipo === 'VOR' ? '⬡ ' : tipo === 'Aeropuerto' ? '✈ ' : tipo === 'VP' ? 'PV ' : '';
            layer.bindPopup(`<div style="font-size:12px;line-height:1.7;min-width:170px">
                <strong style="font-size:13px;color:${c}">${prefix}${lbl}</strong><br>
                <span style="font-size:11px;color:#94a3b8">${renderDescripcion(p.descripcion)}</span>
            </div>`, { maxWidth: 260 });
        }

        // ── Cargador dinámico de GeoJSON ───────────────────────────────────
        async function loadAllGeoJsonLayers() {
            const allFeatures = Array.isArray(ALL_GEOJSON_INLINE) ? [...ALL_GEOJSON_INLINE] : [];

            const byTipo = {};
            for (const feat of allFeatures) {
                const t = feat.properties?.tipo ?? 'Sin tipo';
                (byTipo[t] ??= []).push(feat);
            }
            const knownOrder = Object.keys(TIPO_CONFIG);
            const allTipos   = [
                ...knownOrder.filter(t => byTipo[t]),
                ...Object.keys(byTipo).filter(t => !TIPO_CONFIG[t]),
            ];
            for (const tipo of allTipos) {
                const features = byTipo[tipo];
                const lg = L.layerGroup().addTo(acMap);
                geoLayers[tipo] = lg;
                if (tipo === 'VP') {
                    features.forEach(f => {
                        if (f.geometry?.type === 'Point' && Array.isArray(f.geometry.coordinates)) {
                            const [lon, lat] = f.geometry.coordinates;
                            L.polyline([[lat, lon], LEBZ_POS], {
                                color:'#cbd5e1', weight:0.8, opacity:0.15, dashArray:'6 4', lineCap:'round', interactive:false,
                            }).addTo(lg);
                        }
                    });
                }
                L.geoJSON({ type:'FeatureCollection', features }, {
                    style:         feature           => styleGeoFeature(feature),
                    pointToLayer:  (feature, latlng) => makeGeoIcon(feature, latlng),
                    onEachFeature: (feature, layer)  => bindGeoFeature(feature, layer),
                }).addTo(lg);
            }
            if (!geoLayers['Aeropuerto']) geoLayers['Aeropuerto'] = L.layerGroup().addTo(acMap);
            buildGeoLayerPanelRows();
        }
        loadAllGeoJsonLayers();
    } // end initMap()

    // ── Colores por altitud ───────────────────────────────────────────────────
    function altColor(alt) {
        if (alt == null)    return '#60a5fa';
        if (alt > 35000)    return '#f472b6';
        if (alt > 25000)    return '#a78bfa';
        if (alt > 15000)    return '#60a5fa';
        if (alt >  5000)    return '#34d399';
        return '#fbbf24';
    }

    function distanceMeters(lat1, lon1, lat2, lon2) {
        const dlat = (lat2 - lat1) * 111320;
        const dlon = (lon2 - lon1) * 111320 * Math.cos(lat1 * 0.017453293);
        return Math.sqrt(dlat * dlat + dlon * dlon);
    }

    function updateAircraftTrail(a) {
        if (!acMap || !lgTracks || a.lat == null || a.lon == null) return;
        const TRACK_MAX_POINTS = 90;
        const TRACK_MIN_METERS = 120;
        const isEmergency = a.squawk && EMERGENCY_SQUAWKS.has(a.squawk);
        const trailColor  = isEmergency ? '#ef4444' : altColor(a.alt);
        if (!acTrails[a.hex]) {
            const first    = L.latLng(a.lat, a.lon);
            const polyline = L.polyline([first], { color: trailColor, weight: isEmergency ? 2.6 : 2, opacity: isEmergency ? 0.9 : 0.55, lineCap: 'round', lineJoin: 'round' }).addTo(lgTracks);
            acTrails[a.hex] = { points: [first], polyline };
            return;
        }
        const trail = acTrails[a.hex];
        const next  = L.latLng(a.lat, a.lon);
        const last  = trail.points[trail.points.length - 1];
        if (distanceMeters(last.lat, last.lng, next.lat, next.lng) >= TRACK_MIN_METERS) {
            trail.points.push(next);
            if (trail.points.length > TRACK_MAX_POINTS) trail.points.splice(0, trail.points.length - TRACK_MAX_POINTS);
            trail.polyline.setLatLngs(trail.points);
        }
        trail.polyline.setStyle({ color: trailColor, weight: isEmergency ? 2.6 : 2, opacity: isEmergency ? 0.9 : 0.55 });
    }

    const EMERGENCY_SQUAWKS = new Set(['7500', '7600', '7700']);
    const EMERGENCY_LABELS  = { '7500': 'Secuestro', '7600': 'Fallo radio', '7700': 'Emergencia' };

    function acIconKey(track, alt, ground, squawk) {
        const emergency = squawk && EMERGENCY_SQUAWKS.has(squawk);
        const color = emergency ? 'E' : ground ? 'G' : altColor(alt);
        return `${Math.round((track ?? 0) / 5) * 5}_${color}`;
    }
    function acIcon(track, alt, ground, squawk) {
        const deg       = track ?? 0;
        const emergency = squawk && EMERGENCY_SQUAWKS.has(squawk);
        const color     = emergency ? '#ef4444' : ground ? '#94a3b8' : altColor(alt);
        const pulse     = emergency ? 'ac-emergency-pulse' : '';
        return L.divIcon({
            className: '',
            html: `<div class="${pulse}" style="width:22px;height:22px;transform:rotate(${deg}deg)">
                     <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22">
                       <polygon points="11,1 15.5,20 11,15.5 6.5,20" fill="${color}" stroke="#111" stroke-width="1" stroke-linejoin="round"/>
                     </svg>
                   </div>`,
            iconSize: [22,22], iconAnchor: [11,11], popupAnchor: [0,-14],
        });
    }
    function acPopupHtml(a) {
        const altStr = a.alt   != null ? a.alt.toLocaleString('es-ES') + ' ft' : '—';
        const spdStr = a.speed != null ? a.speed + ' kt' : '—';
        const hdgStr = a.track != null ? Math.round(a.track) + '°' : '—';
        const vrStr  = a.vrate != null ? (a.vrate >= 0 ? '+' : '') + a.vrate + ' ft/min' : '';
        const squawk = a.squawk ? ' &nbsp;·&nbsp; ' + a.squawk : '';
        return `<div style="font-size:12px;line-height:1.7;min-width:140px">
            <div style="font-size:14px;font-weight:700;margin-bottom:2px">${a.callsign || a.hex}</div>
            <div style="color:#94a3b8;font-size:11px;margin-bottom:6px">${a.hex}${squawk}</div>
            <div>↕ ${altStr}</div>
            <div>→ ${spdStr} &nbsp; ⬆ ${hdgStr}</div>
            ${vrStr ? '<div>' + vrStr + '</div>' : ''}
            ${a.ground ? '<div style="color:#fbbf24">En tierra</div>' : ''}
        </div>`;
    }

    // ── Parser SBS ────────────────────────────────────────────────────────────
    function parseSBS(line) {
        const f = line.split(',');
        if (f[0] !== 'MSG' || f.length < 11) return;
        const hex  = f[4];
        const type = parseInt(f[1]);
        if (!hex) return;
        if (!AC[hex]) AC[hex] = { hex, callsign:null, alt:null, speed:null, track:null, lat:null, lon:null, vrate:null, squawk:null, ground:false, lastSeen:0 };
        const a = AC[hex];
        a.lastSeen = Date.now();
        if (type === 1 && f[10] && f[10].trim()) a.callsign = f[10].trim();
        if (type === 2 || type === 3) {
            if (f[11]) a.alt = parseInt(f[11]);
            if (f[14]) a.lat = parseFloat(f[14]);
            if (f[15]) a.lon = parseFloat(f[15]);
            if (f[21] === '1') a.ground = true;
            if (f[21] === '0') a.ground = false;
        }
        if (type === 4) {
            if (f[12]) a.speed = parseInt(f[12]);
            if (f[13]) a.track = parseFloat(f[13]);
            if (f[16]) a.vrate = parseInt(f[16]);
        }
        if (type === 5 && f[11]) a.alt = parseInt(f[11]);
        if (type === 6) {
            if (f[11]) a.alt    = parseInt(f[11]);
            if (f[17]) a.squawk = f[17];
        }
    }

    // ── Render loop (2 s) ─────────────────────────────────────────────────────
    function refreshTracking() {
        if (!acMap) return;
        const now   = Date.now();
        const STALE = 120000;

        Object.keys(AC).forEach(hex => {
            if (now - AC[hex].lastSeen > STALE) {
                if (acMarkers[hex]) { acMarkers[hex].remove(); delete acMarkers[hex]; }
                if (acTrails[hex])  { acTrails[hex].polyline.remove(); delete acTrails[hex]; }
                delete AC[hex];
            }
        });

        const pts = [];
        Object.values(AC).forEach(a => {
            if (a.lat == null || a.lon == null) return;
            pts.push([a.lat, a.lon]);
            updateAircraftTrail(a);
            const newKey = acIconKey(a.track, a.alt, a.ground, a.squawk);
            if (acMarkers[a.hex]) {
                acMarkers[a.hex].setLatLng([a.lat, a.lon]);
                if (acMarkers[a.hex]._iconKey !== newKey) {
                    acMarkers[a.hex].setIcon(acIcon(a.track, a.alt, a.ground, a.squawk));
                    acMarkers[a.hex]._iconKey = newKey;
                }
                if (acMarkers[a.hex].isPopupOpen()) acMarkers[a.hex].setPopupContent(acPopupHtml(a));
            } else {
                const marker = L.marker([a.lat, a.lon], { icon: acIcon(a.track, a.alt, a.ground, a.squawk) })
                    .addTo(acMap)
                    .bindPopup(acPopupHtml(a), { maxWidth: 220 })
                    .on('click', () => { selectedHex = a.hex; });
                marker._iconKey = newKey;
                acMarkers[a.hex] = marker;
            }
        });

        if (followSelected && selectedHex && AC[selectedHex]?.lat != null) {
            acMap.panTo([AC[selectedHex].lat, AC[selectedHex].lon], { animate: true });
        }
        if (pts.length >= 2 && !firstFit && !userPanned) {
            firstFit = true;
            try { acMap.fitBounds(L.latLngBounds(pts).pad(0.15), { maxZoom: 10, animate: true }); } catch(_) {}
        }

        // Banner emergencias
        const emergencies = Object.values(AC).filter(a => a.squawk && EMERGENCY_SQUAWKS.has(a.squawk) && now - a.lastSeen < 30000);
        const emBanner = document.getElementById('emergency-banner');
        const emList   = document.getElementById('emergency-list');
        if (emBanner && emList) {
            if (emergencies.length > 0) {
                emList.textContent = emergencies.map(a => `${a.callsign || a.hex} ${a.squawk} (${EMERGENCY_LABELS[a.squawk] || ''})`).join(' · ');
                emBanner.style.display = '';
            } else {
                emBanner.style.display = 'none';
            }
        }

        // Tabla
        const filterVal = (document.getElementById('ac-filter')?.value || '').trim().toLowerCase();
        const allList   = Object.values(AC).sort((a, b) => (b.alt ?? -1) - (a.alt ?? -1));
        const list      = filterVal ? allList.filter(a => a.hex.toLowerCase().includes(filterVal) || (a.callsign || '').toLowerCase().includes(filterVal)) : allList;
        const tableSig  = list.map(a => `${a.hex}${a.callsign}${a.alt}${a.speed}${Math.round(a.track??0)}${a.squawk}`).join('|');
        const tbody = document.getElementById('ac-tbody');
        if (tbody && tbody._lastSig !== tableSig) {
            tbody._lastSig = tableSig;
            if (list.length === 0) {
                const msg = filterVal ? 'Sin resultados para "' + filterVal + '"' : 'Sin aviones detectados';
                tbody.innerHTML = `<tr><td colspan="5" style="padding:24px;text-align:center;color:#6b7280;font-size:12px">${msg}</td></tr>`;
            } else {
                tbody.innerHTML = list.map(a => {
                    const stale     = now - a.lastSeen > 30000;
                    const emergency = a.squawk && EMERGENCY_SQUAWKS.has(a.squawk);
                    const selBg     = selectedHex === a.hex ? 'rgba(59,130,246,.12)' : '';
                    const emBg      = emergency ? 'rgba(239,68,68,.15)' : selBg;
                    const color     = emergency ? '#ef4444' : altColor(a.alt);
                    const vr        = a.vrate != null ? (a.vrate > 128 ? ' ↑' : a.vrate < -128 ? ' ↓' : '') : '';
                    const sqLabel   = emergency ? ` <span style="color:#ef4444;font-size:10px">⚠${a.squawk}</span>` : '';
                    return `<tr style="cursor:pointer;background:${emBg};opacity:${stale ? '0.4' : '1'}" onclick="selectAircraft('${a.hex}')">
                        <td style="padding:5px 12px;font-family:monospace;font-size:11px;color:#6b7280">${a.hex}</td>
                        <td style="padding:5px 12px;font-family:monospace;font-size:11px;font-weight:600">${a.callsign || '—'}${sqLabel}</td>
                        <td style="padding:5px 12px;font-family:monospace;font-size:11px;text-align:right;color:${color}">${a.alt != null ? a.alt.toLocaleString('es-ES') : '—'}</td>
                        <td style="padding:5px 12px;font-family:monospace;font-size:11px;text-align:right">${a.speed ?? '—'}</td>
                        <td style="padding:5px 12px;font-family:monospace;font-size:11px;text-align:right">${a.track != null ? Math.round(a.track) + '°' : '—'}${vr}</td>
                    </tr>`;
                }).join('');
            }
        }

        const withPos = list.filter(a => a.lat != null);
        const elapsed = (now - msgT0) / 60000;
        const rate    = elapsed > 0.05 ? Math.round(msgCount / elapsed) : 0;
        if (elapsed > 5) { msgCount = 0; msgT0 = Date.now(); }
        setText('ac-total',      list.length);
        setText('ac-pos',        withPos.length);
        setText('ac-msgrate',    rate);
        setText('aircraft-1090', list.length);
        const countEl = document.getElementById('ac-table-count');
        if (countEl) countEl.textContent = list.length > 0 ? list.length + ' aviones' : '';
    }

    function selectAircraft(hex) {
        selectedHex = hex;
        const a = AC[hex];
        if (a && a.lat != null && acMap) {
            acMap.panTo([a.lat, a.lon], { animate: true });
            if (acMarkers[hex]) acMarkers[hex].openPopup();
        }
    }

    setInterval(refreshTracking, 2000);

    // ── SBS stream ────────────────────────────────────────────────────────────
    let sbsSource = null;
    let sbsPaused = false;

    function sbsConnect() {
        if (sbsSource) {
            sbsSource.onopen    = null;
            sbsSource.onmessage = null;
            sbsSource.onerror   = null;
            sbsSource.close();
            sbsSource = null;
        }
        sbsDot('yellow');
        sbsSource = new EventSource(STREAM_URL);
        sbsSource.onopen = function() {
            sbsDot('green');
            sbsAppend('Conectado a ' + SBS_IP + ':30003', '#22c55e');
            const dot  = document.getElementById('feed-dot');
            const text = document.getElementById('feed-text');
            if (dot)  dot.className  = 'w-3 h-3 rounded-full mb-2 bg-green-500';
            if (text) { text.className = 'text-sm font-semibold text-green-400'; text.textContent = 'Activo'; }
        };
        sbsSource.onmessage = function(e) {
            let line;
            try { line = JSON.parse(e.data); } catch(_) { return; }
            if (line && typeof line === 'object' && line.error) {
                sbsAppend('ERROR: ' + line.error, '#f87171');
                sbsDot('red');
                return;
            }
            msgCount++;
            parseSBS(line);
            if (!sbsPaused) sbsAppend(line);
        };
        sbsSource.onerror = function() {
            if (sbsSource && sbsSource.readyState === EventSource.CLOSED) {
                sbsDot('red');
                sbsAppend('Conexión cerrada.', '#6b7280');
            }
        };
    }
    function sbsStop() {
        if (sbsSource) { sbsSource.onopen = null; sbsSource.onmessage = null; sbsSource.onerror = null; sbsSource.close(); sbsSource = null; }
        sbsDot('red');
        sbsAppend('Stream detenido.', '#6b7280');
    }
    function sbsReconnect() {
        sbsPaused = false;
        const btn = document.getElementById('sbs-btn-pause');
        if (btn) btn.textContent = 'Pausar';
        sbsConnect();
    }
    function sbsTogglePause() {
        sbsPaused = !sbsPaused;
        const btn = document.getElementById('sbs-btn-pause');
        if (btn) btn.textContent = sbsPaused ? 'Reanudar' : 'Pausar';
        sbsDot(sbsPaused ? 'yellow' : 'green');
        if (!sbsPaused) sbsAppend('Reanudado.', '#facc15');
    }
    function sbsClear() { const con = document.getElementById('sbs-content'); if (con) con.innerHTML = ''; }
    function sbsAppend(text, color) {
        const con = document.getElementById('sbs-content');
        if (!con) return;
        const line = document.createElement('div');
        if (color) line.style.color = color;
        line.textContent = text;
        con.appendChild(line);
        while (con.children.length > 500) con.removeChild(con.firstChild);
        if (!sbsPaused) con.scrollTop = con.scrollHeight;
    }
    function sbsDot(state) {
        const el = document.getElementById('sbs-dot');
        if (!el) return;
        const colors = { green:'#22c55e', yellow:'#facc15', red:'#ef4444', gray:'#6b7280' };
        el.style.background = colors[state] || colors.gray;
        el.style.boxShadow  = state === 'green' ? '0 0 6px #22c55e' : state === 'yellow' ? '0 0 6px #facc15' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        applySensitiveState(getSensitiveVisible());
        initMap();
        sbsConnect();
    });
</script>
</body>
</html>
