<?php

require_once __DIR__ . '/../Source/SourceInterface.php';

class FeederSource implements SourceInterface
{
    private string $baseUrl;

    private const TIMEOUT = 5;

    public function __construct(string $ip, int $port)
    {
        $this->baseUrl = "http://{$ip}:{$port}";
    }

    public function getData(): array
    {
        $monitor = $this->fetch('/monitor.json');
        $flights = $this->fetch('/flights.json');

        if (isset($monitor['error'])) {
            return ['error' => $monitor['error']];
        }

        return [
            'monitor' => $monitor,
            'flights' => is_array($flights) && !isset($flights['error']) ? $flights : [],
        ];
    }

    private function fetch(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);

        if ($errno !== 0 || $response === false) {
            return ['error' => "No se pudo conectar a {$this->baseUrl}{$path}"];
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return ['error' => "Respuesta no válida de {$this->baseUrl}{$path}"];
        }

        return $data;
    }
}
