<?php

class Config
{
    private static array $validTypes = ['feeder', 'box'];

    public static function load(): array
    {
        $configFile = __DIR__ . '/../config/config.php';

        if (!file_exists($configFile)) {
            return ['no se encuentra <code>config/config.php</code>. Copia <code>config.example.php</code> y configúralo.'];
        }

        require_once $configFile;

        return self::validate();
    }

    public static function warnings(): array
    {
        $warnings = [];
        if (!defined('MAPBOX_TOKEN') || trim(MAPBOX_TOKEN) === '') {
            $warnings[] = 'MAPBOX_TOKEN no está configurado. El mapa de MapBox no estará disponible.';
        }
        if (!defined('ICAO') || trim(ICAO) === '') {
            $warnings[] = 'ICAO no está configurado. No se mostrará el aeropuerto en el mapa.';
        }
        if (!defined('LAT') || !defined('LON') || (LAT == 0 && LON == 0)) {
            $warnings[] = 'LAT/LON no están configurados. El mapa no tendrá centro definido.';
        }
        return $warnings;
    }

    private static function validate(): array
    {
        $errors = [];

        if (!defined('FR24_IP') || FR24_IP === '') {
            $errors[] = 'FR24_IP está vacío.';
        } elseif (filter_var(FR24_IP, FILTER_VALIDATE_IP) === false) {
            $errors[] = 'FR24_IP no es una dirección IP válida: <code>' . htmlspecialchars(FR24_IP) . '</code>';
        }

        if (!defined('FR24_PORT') || FR24_PORT === '') {
            $errors[] = 'FR24_PORT está vacío.';
        } elseif (!is_int(FR24_PORT) || FR24_PORT < 1 || FR24_PORT > 65535) {
            $errors[] = 'FR24_PORT debe ser un número entre 1 y 65535: <code>' . htmlspecialchars(FR24_PORT) . '</code>';
        }

        if (!defined('FR24_TYPE') || FR24_TYPE === '') {
            $errors[] = 'FR24_TYPE está vacío. Debe ser <code>feeder</code> o <code>box</code>.';
        } elseif (!in_array(FR24_TYPE, self::$validTypes, true)) {
            $errors[] = 'FR24_TYPE no es válido: <code>' . htmlspecialchars(FR24_TYPE) . '</code>. Debe ser <code>feeder</code> o <code>box</code>.';
        }

        return $errors;
    }
}
