<?php
require __DIR__ . '/../src/Config.php';
Config::load();

$debug = isset($_GET['debug']);

header('Content-Type: application/json; charset=utf-8');
if (!$debug) header('Cache-Control: public, max-age=3600');

if (!defined('OPENAIP_KEY') || trim(OPENAIP_KEY) === '') {
    http_response_code(503);
    echo json_encode(['error' => 'no_key']);
    exit;
}

$country = 'ES';
if (isset($_GET['country'])) {
    $c = strtoupper(preg_replace('/[^A-Za-z]/', '', $_GET['country']));
    if (strlen($c) === 2) $country = $c;
}

$key = trim(OPENAIP_KEY);

function aipFetch(string $url, string $key, bool $debug = false): array {
    $info = ['url' => $url, 'method' => null, 'http_code' => null, 'error' => null, 'raw' => null];

    if (function_exists('curl_init')) {
        $info['method'] = 'curl';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                "x-openaip-client-id: {$key}",
                'Accept: application/json',
            ],
        ]);
        $raw             = curl_exec($ch);
        $info['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info['error']   = curl_error($ch) ?: null;
        curl_close($ch);
        $info['raw'] = $raw ?: null;
    } else {
        $info['method'] = 'file_get_contents';
        $ctx = stream_context_create(['http' => [
            'header'        => "x-openaip-client-id: {$key}\r\nAccept: application/json\r\n",
            'timeout'       => 20,
            'ignore_errors' => true,
        ]]);
        $raw           = @file_get_contents($url, false, $ctx);
        $info['raw']   = $raw ?: null;
        $info['http_code'] = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) $info['http_code'] = (int)$m[1];
            }
        }
    }

    if ($debug) return $info;

    if (!$info['raw'] || $info['http_code'] !== 200) return [];
    $d = json_decode($info['raw'], true);
    return is_array($d) ? $d : [];
}

if ($debug) {
    $info = aipFetch(
        "https://api.core.openaip.net/api/airspaces?country={$country}&page=0&limit=5",
        $key,
        true
    );
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$all  = [];
$page = 0;
do {
    $url = "https://api.core.openaip.net/api/airspaces?country={$country}&page={$page}&limit=1000";
    $d   = aipFetch($url, $key);
    if (empty($d['items'])) break;
    $all   = array_merge($all, $d['items']);
    $total = $d['totalCount'] ?? count($all);
    $page++;
} while (count($all) < $total && $page < 5);

if (empty($all)) {
    echo json_encode(['type' => 'FeatureCollection', 'features' => [], '_debug' => 'no items returned']);
    exit;
}

$CLASS_MAP = [0=>'A',1=>'B',2=>'C',3=>'D',4=>'E',5=>'F',6=>'G',7=>'',8=>''];
$TYPE_MAP  = [
    0=>'Otro',     1=>'Restringida', 2=>'Peligrosa',  3=>'Prohibida',
    4=>'CTR',      5=>'TMZ',         6=>'RMZ',         7=>'TMA',
    8=>'TRA',      9=>'TSA',         10=>'FIR',        11=>'UIR',
    12=>'ADIZ',    13=>'ATZ',        14=>'MATZ',       17=>'Alerta',
    18=>'Aviso',   26=>'CTA',        27=>'ACC',
];

function fmtAlt(?array $lim): string {
    if (!$lim) return '—';
    $v = $lim['value'] ?? 0;
    $u = $lim['unit']  ?? 1;
    $d = $lim['referenceDatum'] ?? 1;
    if ($v == 0 && $d == 0) return 'GND';
    if ($u == 0) return 'FL' . str_pad((string)(int)$v, 3, '0', STR_PAD_LEFT);
    if ($u == 6) return $v . ' m';
    $refs = [0 => 'AGL', 1 => 'MSL', 2 => 'STD'];
    $ref  = $refs[$d] ?? '';
    return number_format($v) . ' ft' . ($ref ? " $ref" : '');
}

$features = [];
foreach ($all as $item) {
    if (empty($item['geometry'])) continue;
    $type  = $item['type']  ?? 0;
    $class = $item['class'] ?? null;
    $features[] = [
        'type'     => 'Feature',
        'geometry' => $item['geometry'],
        'properties' => [
            'nombre'    => $item['name'] ?? '',
            'class'     => $class !== null ? ($CLASS_MAP[$class] ?? '') : '',
            'type'      => $type,
            'typeLabel' => $TYPE_MAP[$type] ?? 'Otro',
            'lower'     => fmtAlt($item['lowerLimit'] ?? null),
            'upper'     => fmtAlt($item['upperLimit'] ?? null),
        ],
    ];
}

echo json_encode(['type' => 'FeatureCollection', 'features' => $features]);
