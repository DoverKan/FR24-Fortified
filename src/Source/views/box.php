<?php
$info    = $data['info']    ?? [];
$gps     = $data['gps']     ?? [];
$flights = $data['flights'] ?? [];
$total   = $data['total']   ?? 0;
$withPos = $data['with_position'] ?? 0;
$msgRate = $data['msg_rate'] ?? null;

$fr24Active = strtolower($info['FR24 feeding'] ?? '') === 'yes';
$gpsOk      = strtolower($info['Status'] ?? '') === 'ok';
?>

<?php include __DIR__ . '/../../../public/partials/sbs-stream.php'; ?>

<!-- Métricas principales -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="mb-1" id="b-fr24-feed">
                    <span class="rounded-circle <?= $fr24Active ? 'bg-success' : 'bg-danger' ?>" style="width:10px;height:10px;display:inline-block"></span>
                    <span class="fw-semibold <?= $fr24Active ? 'text-success' : 'text-danger' ?> ms-1"><?= $fr24Active ? 'Activo' : 'Inactivo' ?></span>
                </div>
                <div class="text-muted small">FR24 Feed</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold" id="b-ac-1090"><?= htmlspecialchars($info['1090MHz aicraft'] ?? '—') ?></div>
                <div class="text-muted small">Aviones 1090 MHz</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold" id="b-temp"><?= htmlspecialchars(str_replace('℃', '°C', $info['Temperature'] ?? '—')) ?></div>
                <div class="text-muted small">Temperatura</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold" id="b-radar"><?= htmlspecialchars($info['FR24 radar code'] ?? '—') ?></div>
                <div class="text-muted small">Radar code</div>
            </div>
        </div>
    </div>
</div>

<!-- Contadores vuelos -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary" id="b-total"><?= $total ?></div>
                <div class="text-muted small">Aviones detectados</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success" id="b-with-pos"><?= $withPos ?></div>
                <div class="text-muted small">Con posición</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold" id="b-without-pos"><?= $total - $withPos ?></div>
                <div class="text-muted small">Sin posición</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold" id="b-msg-rate"><?= $msgRate !== null ? number_format($msgRate) : '—' ?></div>
                <div class="text-muted small">Mensajes / min</div>
            </div>
        </div>
    </div>
</div>

<!-- Info detallada -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold text-muted small text-uppercase">Sistema</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted">Versión</td>   <td id="b-version"><?= htmlspecialchars($info['version'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Actualizado</td><td id="b-updated"><?= htmlspecialchars($info['updated'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Uptime</td>    <td id="b-uptime"><?= htmlspecialchars($info['Uptime'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Partición</td> <td id="b-partition"><?= htmlspecialchars($info['Persistent partition usage'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">MAC</td>       <td><code id="b-mac"><?= htmlspecialchars($info['MAC address'] ?? '—') ?></code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold text-muted small text-uppercase">Red</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted">IP externa</td> <td id="b-ext-ip"><?= htmlspecialchars($info['External IP']    ?? '—') ?></td></tr>
                        <tr><td class="text-muted">IP interna</td> <td id="b-int-ip"><?= htmlspecialchars($info['Internal IP']    ?? '—') ?></td></tr>
                        <tr><td class="text-muted">DNS público</td><td id="b-dns-pub"><?= htmlspecialchars($info['DNS public']     ?? '—') ?></td></tr>
                        <tr><td class="text-muted">DNS config.</td><td id="b-dns-cfg"><?= htmlspecialchars($info['DNS configured'] ?? '—') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold text-muted small text-uppercase">GPS</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Estado</td>
                            <td id="b-gps-status"><span class="badge <?= $gpsOk ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($info['Status'] ?? '—') ?></span></td>
                        </tr>
                        <tr><td class="text-muted">Satélites</td><td id="b-sats"><?= htmlspecialchars($info['Satellites used'] ?? ($gps['Sats'] ?? '—')) ?></td></tr>
                        <tr><td class="text-muted">Posición</td> <td><small id="b-pos"><?= htmlspecialchars($info['GPS position'] ?? '—') ?></small></td></tr>
                        <tr><td class="text-muted">Señal</td>    <td><small class="text-muted" id="b-signal"><?= htmlspecialchars($info['Signal levels'] ?? '—') ?></small></td></tr>
                        <tr><td class="text-muted">Antena</td>   <td id="b-antenna"><?= htmlspecialchars($gps['Antenna Open'] ?? '—') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const INTERVAL = 30000;

    function esc(v) {
        return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function set(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val ?? '—';
    }

    function setHTML(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function refresh() {
        fetch('data.php')
            .then(r => r.json())
            .then(d => {
                const info = d.info    ?? {};
                const gps  = d.gps     ?? {};
                const fl   = d.flights ?? {};
                const total   = d.total          ?? 0;
                const withPos = d.with_position  ?? 0;
                const msgRate = d.msg_rate;

                const active = (info['FR24 feeding'] ?? '').toLowerCase() === 'yes';
                const gpsOk  = (info['Status'] ?? '').toLowerCase() === 'ok';

                setHTML('b-fr24-feed',
                    `<span class="rounded-circle ${active ? 'bg-success' : 'bg-danger'}" style="width:10px;height:10px;display:inline-block"></span>
                     <span class="fw-semibold ${active ? 'text-success' : 'text-danger'} ms-1">${active ? 'Activo' : 'Inactivo'}</span>`
                );

                set('b-ac-1090',   info['1090MHz aicraft']);
                set('b-temp',      (info['Temperature'] ?? '—').replace('℃','°C'));
                set('b-radar',     info['FR24 radar code']);
                set('b-total',     total);
                set('b-with-pos',  withPos);
                set('b-without-pos', total - withPos);
                set('b-msg-rate',  msgRate != null ? parseInt(msgRate).toLocaleString() : '—');

                set('b-version',   info['version']);
                set('b-updated',   info['updated']);
                set('b-uptime',    info['Uptime']);
                set('b-partition', info['Persistent partition usage']);
                set('b-mac',       info['MAC address']);
                set('b-ext-ip',    info['External IP']);
                set('b-int-ip',    info['Internal IP']);
                set('b-dns-pub',   info['DNS public']);
                set('b-dns-cfg',   info['DNS configured']);

                setHTML('b-gps-status',
                    `<span class="badge ${gpsOk ? 'bg-success' : 'bg-danger'}">${esc(info['Status'] ?? '—')}</span>`
                );
                set('b-sats',    info['Satellites used'] ?? gps['Sats']);
                set('b-pos',     info['GPS position']);
                set('b-signal',  info['Signal levels']);
                set('b-antenna', gps['Antenna Open']);
            })
            .catch(() => {});
    }

    setInterval(refresh, INTERVAL);

    // Contadores en tiempo real desde el stream SBS
    const aircraft = new Map(); // hex -> {hasPosition}
    const msgTimes = [];        // timestamps para mensajes/min

    function updateCounters() {
        const now    = Date.now();
        const cutoff = now - 60000;

        // Limpiar mensajes fuera de la ventana de 1 min
        while (msgTimes.length && msgTimes[0] < cutoff) msgTimes.shift();

        const total   = aircraft.size;
        const withPos = [...aircraft.values()].filter(a => a.hasPosition).length;

        set('b-total',       total);
        set('b-with-pos',    withPos);
        set('b-without-pos', total - withPos);
        set('b-msg-rate',    (msgTimes.length).toLocaleString());
    }

    // Esperar a que el partial haya creado sbsSource
    window.addEventListener('load', () => {
        if (!window.sbsSource) return;

        window.sbsSource.addEventListener('message', e => {
            const p = e.data.split(',');
            if (p[0] !== 'MSG') return;

            msgTimes.push(Date.now());

            const hex = p[4];
            if (!hex) return;

            if (!aircraft.has(hex)) {
                aircraft.set(hex, { hasPosition: false });
            }

            // MSG,3 lleva lat/lon — marcar si son válidos
            if (p[1] === '3') {
                const lat = parseFloat(p[14]);
                const lon = parseFloat(p[15]);
                if (!isNaN(lat) && !isNaN(lon) && (lat !== 0 || lon !== 0)) {
                    aircraft.get(hex).hasPosition = true;
                }
            }
        });

        setInterval(updateCounters, 1000);
    });
})();
</script>
