<?php

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Desactivar cualquier buffer de salida
while (ob_get_level()) {
    ob_end_flush();
}

require __DIR__ . '/../src/Config.php';
Config::load();

$socket = @fsockopen(FR24_IP, 30003, $errno, $errstr, 5);

if (!$socket) {
    echo "event: error\n";
    echo "data: No se pudo conectar al stream ({$errstr})\n\n";
    flush();
    exit;
}

stream_set_timeout($socket, 60);
set_time_limit(0);

while (!feof($socket) && !connection_aborted()) {
    $line = fgets($socket, 1024);

    if ($line === false) {
        break;
    }

    $line = trim($line);

    if ($line === '') {
        continue;
    }

    echo "data: " . $line . "\n\n";
    flush();
}

fclose($socket);
