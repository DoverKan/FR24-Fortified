<?php

header('Content-Type: application/json');

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Source/FeederSource.php';
require __DIR__ . '/../src/Source/BoxSource.php';

$errors = Config::load();

if ($errors) {
    echo json_encode(['error' => 'config']);
    exit;
}

$source = FR24_TYPE === 'feeder'
    ? new FeederSource(FR24_IP, FR24_PORT)
    : new BoxSource(FR24_IP);

echo json_encode($source->getData());
