# FlightRadar24-Fortified

Panel de monitoreo web para receptores ADS-B de FlightRadar24 (Feeder y Box). Muestra en tiempo real el estado del receptor, estadísticas de vuelos y un mapa interactivo de posiciones de aeronaves.

---

## Características

- **Dashboard en tiempo real** — estado del receptor, estadísticas y tabla de vuelos activos con auto-refresco
- **Mapa interactivo** — visualización de posiciones de aeronaves sobre Mapbox GL con capas GeoJSON (espacio aéreo, aeropuertos, etc.)
- **Consola SBS** — stream en tiempo real del protocolo SBS/AVR (puerto 30003) vía Server-Sent Events
- **Soporte dual** — compatible con receptores tipo `feeder` y tipo `box`
- **Versión standalone** — archivo único `fr24-standalone.php` para despliegue sin configuración adicional

---

## Requisitos

- PHP 7.4 o superior
- Extensión `curl` habilitada en PHP
- Servidor web (Apache, Nginx) o servidor built-in de PHP
- Acceso de red al receptor FlightRadar24 (local o LAN)
- Token de Mapbox (solo para la página de mapa)

---

## Instalación

**1. Clonar el repositorio**

```bash
git clone <url-del-repo> FR24
cd FR24
```

**2. Configurar la aplicación**

```bash
cp config/config.example.php config/config.php
```

Editar `config/config.php` con los valores de tu receptor:

```php
define('FR24_IP',      '192.168.1.11');  // IP del receptor en la red local
define('FR24_PORT',    8754);             // Puerto del receptor
define('FR24_TYPE',    'box');            // 'feeder' o 'box'
define('ICAO',         'LEBZ');           // Código ICAO del aeropuerto (opcional)
define('LAT',          38.891944);        // Latitud del centro del mapa (opcional)
define('LON',          -6.822397);        // Longitud del centro del mapa (opcional)
define('MAPBOX_TOKEN', 'pk.eyJ...');     // Token de Mapbox (necesario para el mapa)
```

**3. Iniciar el servidor**

```bash
# Servidor built-in de PHP (desarrollo)
php -S localhost:8000 -t public/
```

O configurar Apache/Nginx apuntando el `document root` a `public/`.

**4. Abrir en el navegador**

```
http://localhost:8000
```

---

## Estructura del proyecto

```
FR24/
├── config/
│   ├── config.php              # Configuración activa (credenciales, en .gitignore)
│   └── config.example.php      # Plantilla de configuración
│
├── src/
│   ├── Config.php              # Cargador y validador de configuración
│   └── Source/
│       ├── SourceInterface.php # Interfaz común para fuentes de datos
│       ├── FeederSource.php    # Conector para receptor tipo Feeder
│       ├── BoxSource.php       # Conector para receptor tipo Box
│       └── views/
│           ├── feeder.php      # Vista del dashboard para Feeder
│           └── box.php         # Vista del dashboard para Box
│
├── public/
│   ├── index.php               # Dashboard principal
│   ├── mapa.php                # Mapa interactivo de aeronaves
│   ├── console.php             # Consola del stream SBS
│   ├── data.php                # Endpoint JSON para auto-refresco
│   ├── sse.php                 # Endpoint Server-Sent Events
│   ├── fr24-standalone.php     # Versión autónoma en un solo archivo
│   ├── partials/               # Componentes reutilizables (sidebar, topbar)
│   ├── css/                    # Hojas de estilo
│   └── geojson/                # Capas GeoJSON (aeropuertos, sectores, etc.)
│
└── docs/                       # Documentación adicional
```

---

## Páginas y endpoints

| Ruta | Descripción |
|---|---|
| `/` o `/index.php` | Dashboard principal con estado del receptor y tabla de vuelos |
| `/mapa.php` | Mapa interactivo con posiciones de aeronaves |
| `/console.php` | Consola del stream SBS en tiempo real |
| `/data.php` | API JSON para refresco de datos del dashboard |
| `/sse.php` | Stream de Server-Sent Events (usado por la consola) |
| `/fr24-standalone.php` | Versión standalone (admite `?action=stream\|ping\|stats\|weather`) |

---

## Tipos de receptor

### Feeder

Consulta `/monitor.json` (estado) y `/flights.json` (aeronaves) del receptor.

```php
define('FR24_TYPE', 'feeder');
define('FR24_PORT', 8754);
```

### Box

Realiza peticiones paralelas a `/index.php`, `/flights.js`, `/gps.json` y `/stats.json`, y parsea la respuesta JSONP de vuelos.

```php
define('FR24_TYPE', 'box');
```

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 7.4+ |
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| UI | Bootstrap 5.3.3 |
| Mapas | Mapbox GL JS v3.3.0 |
| HTTP | PHP cURL (peticiones paralelas) |
| Streaming | Server-Sent Events (SSE) |

---

## Seguridad

El archivo `config/config.php` está excluido de git mediante `.gitignore` para evitar exponer credenciales. Usa siempre `config/config.example.php` como base y nunca confirmes el archivo de configuración real al repositorio.

---

## Licencia

Proyecto privado. Todos los derechos reservados.
