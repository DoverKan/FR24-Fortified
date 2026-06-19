<?php
require __DIR__ . '/../src/Config.php';
$errors = Config::load();
$data   = [];
$typeBadge = ['feeder' => 'bg-info', 'box' => 'bg-warning text-dark'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Stream SBS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/layout.css" rel="stylesheet">
    <style>
        #sbs-console {
            font-family: 'Courier New', monospace;
            font-size: .8rem;
            background: #010409;
            border-radius: .375rem;
            height: calc(100vh - var(--topbar-height) - 130px);
            overflow-y: auto;
            padding: .75rem 1rem;
            color: #8b949e;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/topbar.php'; ?>

<main id="main">
    <div id="content">

        <div class="rounded overflow-hidden border border-secondary mb-3" style="background:#010409">
            <div class="d-flex align-items-center px-3 py-2 gap-2 flex-wrap" style="background:#161b22; border-bottom:1px solid #30363d">
                <span class="rounded-circle bg-success" style="width:10px;height:10px;display:inline-block"></span>
                <small class="text-light fw-semibold">Raw SBS — Puerto 30003</small>
                <span class="badge bg-dark border border-secondary ms-2" id="counter">0 mensajes</span>
                <div class="ms-auto d-flex gap-2">
                    <button class="btn btn-sm btn-outline-warning py-0" id="btn-pause">Pausar</button>
                    <button class="btn btn-sm btn-outline-danger  py-0" id="btn-clear">Limpiar</button>
                </div>
            </div>
            <div id="sbs-console"></div>
        </div>

        <div class="d-flex gap-3" style="font-size:.75rem; color:#8b949e;">
            <span><span style="color:#58d68d">■</span> Posición (MSG,3)</span>
            <span><span style="color:#5dade2">■</span> Velocidad (MSG,4)</span>
            <span><span style="color:#f39c12">■</span> Selección (MSG,1)</span>
            <span><span style="color:#a569bd">■</span> Nueva aeronave (MSG,5)</span>
            <span><span style="color:#8b949e">■</span> Otros</span>
        </div>

    </div>
</main>

<script>
(function () {
    const el       = document.getElementById('sbs-console');
    const counterEl= document.getElementById('counter');
    const btnPause = document.getElementById('btn-pause');
    const btnClear = document.getElementById('btn-clear');

    let count = 0;
    let paused = false, autoScroll = true;

    const colors = { '1':'#f39c12','3':'#58d68d','4':'#5dade2','5':'#a569bd' };

    function append(text) {
        const p     = text.split(',');
        const color = p[0] === 'MSG' ? (colors[p[1]] || '#8b949e') : '#8b949e';

        count++;
        counterEl.textContent = count.toLocaleString() + ' mensajes';

        const div = document.createElement('div');
        div.style.cssText = `color:${color};line-height:1.5;white-space:nowrap`;
        div.textContent = text;
        el.appendChild(div);
        while (el.children.length > 2000) el.removeChild(el.firstChild);
        if (autoScroll) el.scrollTop = el.scrollHeight;
    }

    const source = new EventSource('sse.php');
    source.onmessage = e => { if (!paused) append(e.data); };
    source.addEventListener('error', () => append('⚠ Reconectando...'));

    btnPause.addEventListener('click', () => {
        paused = !paused;
        btnPause.textContent = paused ? 'Reanudar' : 'Pausar';
        btnPause.className   = paused ? 'btn btn-sm btn-warning py-0' : 'btn btn-sm btn-outline-warning py-0';
    });

    btnClear.addEventListener('click', () => {
        el.innerHTML = '';
        count = 0;
        counterEl.textContent = '0 mensajes';
    });

    el.addEventListener('scroll', () => {
        autoScroll = el.scrollHeight - el.scrollTop - el.clientHeight < 30;
    });
})();
</script>

</body>
</html>
