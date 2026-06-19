<div class="mb-4 rounded overflow-hidden border border-secondary" style="background:#010409">
    <div class="d-flex align-items-center px-3 py-2 gap-2 flex-wrap" style="background:#161b22; border-bottom:1px solid #30363d">
        <span class="rounded-circle bg-success" style="width:10px;height:10px;display:inline-block"></span>
        <small class="text-light fw-semibold">Raw SBS — Puerto 30003</small>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-warning py-0" id="btn-pause">Pausar</button>
            <button class="btn btn-sm btn-outline-danger  py-0" id="btn-clear">Limpiar</button>
        </div>
    </div>
    <div id="sbs-console" style="height:180px;overflow-y:auto;font-family:monospace;font-size:.75rem;padding:.5rem 1rem;color:#8b949e"></div>
</div>

<script>
(function () {
    const el       = document.getElementById('sbs-console');
    const btnPause = document.getElementById('btn-pause');
    const btnClear = document.getElementById('btn-clear');

    let paused = false, autoScroll = true;

    const colors = { '1':'#f39c12','3':'#58d68d','4':'#5dade2','5':'#a569bd' };

    function append(text) {
        const p     = text.split(',');
        const color = p[0] === 'MSG' ? (colors[p[1]] || '#8b949e') : '#8b949e';

        const div = document.createElement('div');
        div.style.cssText = `color:${color};line-height:1.5;white-space:nowrap`;
        div.textContent = text;
        el.appendChild(div);
        while (el.children.length > 1000) el.removeChild(el.firstChild);
        if (autoScroll) el.scrollTop = el.scrollHeight;
    }

    window.sbsSource = new EventSource('sse.php');
    window.sbsSource.onmessage = e => { if (!paused) append(e.data); };
    window.sbsSource.addEventListener('error', () => append('⚠ Reconectando...'));

    btnPause.addEventListener('click', () => {
        paused = !paused;
        btnPause.textContent = paused ? 'Reanudar' : 'Pausar';
        btnPause.className   = paused ? 'btn btn-sm btn-warning py-0' : 'btn btn-sm btn-outline-warning py-0';
    });

    btnClear.addEventListener('click', () => {
        el.innerHTML = '';
    });

    el.addEventListener('scroll', () => {
        autoScroll = el.scrollHeight - el.scrollTop - el.clientHeight < 30;
    });
})();
</script>
