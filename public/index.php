<?php

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Source/FeederSource.php';
require __DIR__ . '/../src/Source/BoxSource.php';

$errors = Config::load();

$warnings = $errors ? Config::warnings() : [];

if ($errors):
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Error de configuración</title>
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
            <h4 class="mt-3 mb-1 fw-bold">Error de configuración</h4>
            <p class="text-muted mb-0" style="font-size:.9rem">Revisa el fichero <code>config/config.php</code> antes de continuar.</p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php foreach ($errors as $i => $error): ?>
                    <div class="d-flex align-items-start gap-3 px-4 py-3 <?= $i > 0 ? 'border-top' : '' ?>">
                        <span class="mt-1 text-danger flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                            </svg>
                        </span>
                        <span style="font-size:.9rem"><?= $error ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($warnings): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body p-0">
                <?php foreach ($warnings as $i => $warning): ?>
                    <div class="d-flex align-items-start gap-3 px-4 py-3 <?= $i > 0 ? 'border-top' : '' ?>">
                        <span class="mt-1 text-warning flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                            </svg>
                        </span>
                        <span class="text-muted" style="font-size:.9rem"><?= $warning ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:.8rem">
            Copia <code>config/config.example.php</code> como <code>config/config.php</code> y ajusta los valores.
        </p>
    </div>
</body>
</html>
<?php
    exit;
endif;

$data      = [];
$typeBadge = ['feeder' => 'bg-info', 'box' => 'bg-warning text-dark'];

$source = FR24_TYPE === 'feeder'
    ? new FeederSource(FR24_IP, FR24_PORT)
    : new BoxSource(FR24_IP);

$data = $source->getData();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FR24 — Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/layout.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>
<?php include __DIR__ . '/partials/topbar.php'; ?>

<main id="main">
    <div id="content">

        <?php if (isset($data['error'])): ?>
            <div class="alert alert-warning">
                <strong>Error de conexión:</strong> <?= htmlspecialchars($data['error']) ?>
            </div>
        <?php elseif (FR24_TYPE === 'feeder'): ?>
            <?php include __DIR__ . '/../src/Source/views/feeder.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/../src/Source/views/box.php'; ?>
        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
