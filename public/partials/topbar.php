<header id="topbar">
    <button id="btn-sidebar-toggle" aria-label="Alternar menú">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
        </svg>
    </button>
    <div class="topbar-breadcrumb">
        <span class="fw-semibold">FR24</span>
        <?php if (!$errors): ?>
            <span class="ms-2 badge <?= $typeBadge[FR24_TYPE] ?>"><?= htmlspecialchars(FR24_TYPE) ?></span>
            <span class="ms-2">
                <span class="rounded-circle <?= (!isset($data['error'])) ? 'bg-success' : 'bg-danger' ?>" style="width:8px;height:8px;display:inline-block"></span>
                <small class="ms-1 <?= (!isset($data['error'])) ? 'text-success' : 'text-danger' ?>">
                    <?= (!isset($data['error'])) ? 'Encendido' : 'Error' ?>
                </small>
            </span>
        <?php endif; ?>
    </div>

    <div class="topbar-actions">
        <small class="text-muted me-3">Actualizado: <?= date('H:i:s') ?></small>
        <button class="btn btn-sm btn-outline-secondary me-2" onclick="window.location.reload()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/>
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/>
            </svg>
            Actualizar
        </button>
        <small class="text-muted topbar-ip"><?= defined('FR24_IP') ? htmlspecialchars(FR24_IP) : '' ?></small>
    </div>
</header>
<script>
(function () {
    const KEY      = 'fr24_sidebar';
    const btn      = document.getElementById('btn-sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    function isMobile() { return window.innerWidth < 768; }

    // Restaurar estado colapsado en desktop
    if (!isMobile() && localStorage.getItem(KEY) === '0') {
        document.body.classList.add('sidebar-collapsed');
    }

    function closeMobile() {
        document.body.classList.remove('sidebar-open');
    }

    btn.addEventListener('click', () => {
        if (isMobile()) {
            document.body.classList.toggle('sidebar-open');
        } else {
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(KEY, collapsed ? '0' : '1');
        }
    });

    backdrop.addEventListener('click', closeMobile);

    // Al pasar de mobile a desktop cerrar overlay
    window.addEventListener('resize', () => {
        if (!isMobile()) closeMobile();
    });
})();
</script>
