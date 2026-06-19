<?php include __DIR__ . '/../../../public/partials/sbs-stream.php'; ?>

<?php
$m       = $data['monitor'];
$flights = $data['flights'];

$acTracked     = max((int)($m['d11_map_size'] ?? 0), (int)($m['ac_map_size'] ?? 0));
$acUploaded    = (int)($m['feed_num_ac_tracked'] ?? 0);
$fr24Connected = ($m['feed_status'] ?? '') === 'connected';
$rxConnected   = ($m['rx_connected'] ?? '0') === '1';
$mlatOk        = ($m['mlat-ok'] ?? 'NO') === 'YES';
$version       = implode(' / ', array_filter([
    $m['build_os'] ?? null, $m['build_arch'] ?? null, $m['build_version'] ?? null,
]));
?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Estado</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">FR24 Link</td>
                            <td id="f-fr24-link">
                                <?php if ($fr24Connected): ?>
                                    <span class="badge bg-success">Conectado</span>
                                    <small class="text-muted"><?= htmlspecialchars($m['feed_current_mode'] ?? '') ?></small>
                                <?php else: ?>
                                    <span class="badge bg-danger">Desconectado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Receptor</td>
                            <td id="f-rx">
                                <?= htmlspecialchars($m['cfg_receiver'] ?? 'N/A') ?>
                                <span class="badge <?= $rxConnected ? 'bg-success' : 'bg-danger' ?> ms-1"><?= $rxConnected ? 'OK' : 'Sin señal' ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">MLAT</td>
                            <td id="f-mlat">
                                <?php if ($mlatOk): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No activo</span>
                                    <?php if (!empty($m['mlat_problem'])): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($m['mlat_problem']) ?>)</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Servidor FR24</td>
                            <td><small id="f-server"><?= htmlspecialchars($m['feed_current_server'] ?? 'N/A') ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">IP local</td>
                            <td><small id="f-ips"><?= htmlspecialchars(str_replace(',', ', ', $m['local_ips'] ?? 'N/A')) ?></small></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Estadísticas</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Código radar</td>
                            <td><span class="badge bg-primary" id="f-radar"><?= htmlspecialchars($m['feed_alias'] ?? 'N/A') ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Aeronaves detectadas</td>
                            <td><span class="fw-bold" id="f-tracked"><?= $acTracked ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Aeronaves subidas</td>
                            <td><span class="fw-bold" id="f-uploaded"><?= $acUploaded ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Última conexión RX</td>
                            <td><small id="f-last-rx"><?= htmlspecialchars($m['last_rx_connect_time_s'] ?? 'N/A') ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Versión</td>
                            <td><small><?= htmlspecialchars($version) ?></small></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        Vuelos
        <span class="badge bg-secondary" id="f-flights-count"><?= count($flights) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Hex</th><th>Callsign</th><th>Altitud</th><th>Velocidad</th><th>Lat</th><th>Lon</th></tr>
                </thead>
                <tbody id="f-flights-body">
                    <?php if (empty($flights)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-3">Sin vuelos en este momento.</td></tr>
                    <?php else: ?>
                        <?php foreach ($flights as $hex => $f): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($hex) ?></code></td>
                                <td><?= htmlspecialchars($f[16] ?? '—') ?></td>
                                <td><?= htmlspecialchars($f[4]  ?? '—') ?></td>
                                <td><?= htmlspecialchars($f[5]  ?? '—') ?></td>
                                <td><?= htmlspecialchars($f[1]  ?? '—') ?></td>
                                <td><?= htmlspecialchars($f[2]  ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const INTERVAL = 30000;

    function esc(v) {
        return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function badge(ok, yes, no) {
        return `<span class="badge ${ok ? 'bg-success' : 'bg-danger'}">${ok ? yes : no}</span>`;
    }

    function refresh() {
        fetch('data.php')
            .then(r => r.json())
            .then(d => {
                const m  = d.monitor ?? {};
                const fl = d.flights ?? {};

                const connected = m.feed_status === 'connected';
                const rxConn    = m.rx_connected === '1';
                const mlatOk    = m['mlat-ok'] === 'YES';

                document.getElementById('f-fr24-link').innerHTML =
                    badge(connected, 'Conectado', 'Desconectado') +
                    (connected ? ` <small class="text-muted">${esc(m.feed_current_mode)}</small>` : '');

                document.getElementById('f-rx').innerHTML =
                    esc(m.cfg_receiver) +
                    ` <span class="badge ${rxConn ? 'bg-success' : 'bg-danger'} ms-1">${rxConn ? 'OK' : 'Sin señal'}</span>`;

                document.getElementById('f-mlat').innerHTML =
                    badge(mlatOk, 'Activo', 'No activo') +
                    (!mlatOk && m.mlat_problem ? ` <small class="text-muted">(${esc(m.mlat_problem)})</small>` : '');

                document.getElementById('f-server').textContent  = m.feed_current_server ?? 'N/A';
                document.getElementById('f-ips').textContent     = (m.local_ips ?? 'N/A').replace(/,/g, ', ');
                document.getElementById('f-radar').textContent   = m.feed_alias ?? 'N/A';
                document.getElementById('f-tracked').textContent = Math.max(parseInt(m.d11_map_size)||0, parseInt(m.ac_map_size)||0);
                document.getElementById('f-uploaded').textContent= m.feed_num_ac_tracked ?? '0';
                document.getElementById('f-last-rx').textContent = m.last_rx_connect_time_s ?? 'N/A';

                // Tabla de vuelos
                const keys = Object.keys(fl);
                document.getElementById('f-flights-count').textContent = keys.length;
                document.getElementById('f-flights-body').innerHTML = keys.length === 0
                    ? '<tr><td colspan="6" class="text-muted text-center py-3">Sin vuelos en este momento.</td></tr>'
                    : keys.map(hex => {
                        const f = fl[hex];
                        return `<tr>
                            <td><code>${esc(hex)}</code></td>
                            <td>${esc(f[16])}</td>
                            <td>${esc(f[4])}</td>
                            <td>${esc(f[5])}</td>
                            <td>${esc(f[1])}</td>
                            <td>${esc(f[2])}</td>
                        </tr>`;
                    }).join('');
            })
            .catch(() => {});
    }

    setInterval(refresh, INTERVAL);
})();
</script>
