<?php

require_once __DIR__ . '/../Source/SourceInterface.php';

class BoxSource implements SourceInterface
{
    private string $baseUrl;

    private const TIMEOUT = 5;

    public function __construct(string $ip)
    {
        $this->baseUrl = "http://{$ip}";
    }

    public function getData(): array
    {
        // Todas las peticiones en paralelo
        $responses = $this->curlMulti([
            'index'   => "{$this->baseUrl}/index.php",
            'flights' => "{$this->baseUrl}/flights.js",
            'gps'     => "{$this->baseUrl}/gps.json",
            'stats'   => "{$this->baseUrl}/stats.json",
        ]);

        $info    = $this->parseIndex($responses['index']);
        $flights = $this->parseFlights($responses['flights']);
        $gps     = $this->parseGps($responses['gps']);
        $msgRate = $this->parseMsgRate($responses['stats']);

        if (isset($flights['error'])) {
            return ['error' => $flights['error']];
        }

        $withPosition = count(array_filter($flights, fn($f) => isset($f[1]) && $f[1] != 0));

        return [
            'info'          => $info,
            'gps'           => $gps,
            'flights'       => $flights,
            'total'         => count($flights),
            'with_position' => $withPosition,
            'msg_rate'      => $msgRate,
        ];
    }

    private function parseIndex(?string $html): array
    {
        if ($html === null) return [];

        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $data = [];

        $build = $xpath->query('//*[contains(@class,"receiver-overview-subtitle__build")]');
        if ($build->length) $data['version'] = trim($build->item(0)->textContent);

        $updated = $xpath->query('//*[contains(@class,"receiver-overview-subtitle__updated")]');
        if ($updated->length) $data['updated'] = trim($updated->item(0)->textContent);

        $rows = $xpath->query('//table[contains(@class,"receiver-table--overview")]//tr');
        foreach ($rows as $row) {
            $th = $xpath->query('th', $row)->item(0);
            $td = $xpath->query('td', $row)->item(0);
            if ($th && $td) {
                $data[trim($th->textContent)] = trim($td->textContent);
            }
        }

        return $data;
    }

    private function parseFlights(?string $raw): array
    {
        if ($raw === null) {
            return ['error' => "No se pudo conectar al box ({$this->baseUrl})"];
        }

        $json = preg_replace('/^\s*fr24_callback\s*\(\s*/', '', $raw);
        $json = preg_replace('/\s*\)\s*;?\s*$/', '', $json);
        $json = preg_replace('/,(?=,|\])/', ',null', $json);

        $data = json_decode($json, true);

        return is_array($data) ? $data : ['error' => 'Respuesta de vuelos no válida.'];
    }

    private function parseGps(?string $raw): array
    {
        if ($raw === null) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? ($data['ReceiverStatus'] ?? []) : [];
    }

    private function parseMsgRate(?string $raw): ?int
    {
        if ($raw === null) return null;

        $json   = preg_replace('/\[\s*,/', '[', $raw);
        $data   = json_decode($json, true);
        $blocks = array_values(array_filter($data['blocks'] ?? []));

        if (empty($blocks)) return null;

        $last   = end($blocks);
        $f1090  = $last['data']['f1090'] ?? [];

        if (empty($f1090)) return null;

        // f1090 son mensajes/segundo — convertir a mensajes/minuto
        return (int) end($f1090) * 60;
    }

    private function curlMulti(array $urls): array
    {
        $multi   = curl_multi_init();
        $handles = [];

        foreach ($urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);

        $responses = [];
        foreach ($handles as $key => $ch) {
            $responses[$key] = curl_errno($ch) === 0 ? curl_multi_getcontent($ch) : null;
            curl_multi_remove_handle($multi, $ch);
        }

        curl_multi_close($multi);

        return $responses;
    }
}
