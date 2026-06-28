# FlightRadar24-Fortified

Panel de monitoreo web para receptores ADS-B de FlightRadar24 (Feeder y Box). Muestra en tiempo real el estado del receptor, estadísticas de vuelos y un mapa interactivo de posiciones de aeronaves con capas meteorológicas y de situación.

**Repositorio:** https://github.com/DoverKan/FR24-Fortified

---

## Características

- **Dashboard en tiempo real** — estado del receptor, estadísticas y tabla de vuelos activos con auto-refresco
- **Mapa interactivo** — Mapbox GL con múltiples estilos de base (oscuro, calles, satélite, terreno) y capas GeoJSON configurables
- **Seguimiento de aeronaves** — posiciones, rumbo, altitud y tracks históricos en tiempo real vía stream SBS (puerto 30003); iconos con código de colores por altitud y alerta de squawk de emergencia
- **Controles del mapa** — centrar en todos los aviones, seguir un avión seleccionado, retener tracks ilimitados, exportar el mapa como PNG, anillos de distancia NM, perfil de elevación, regla de medición
- **Capas meteorológicas** — radar de precipitación animado (RainViewer) con datos de los últimos ~40 min, e imagen de nubosidad diaria por satélite (NASA GIBS / MODIS Terra)
- **Incendios activos** — capa FIRMS (NASA) con puntos interactivos en tiempo real; muestra satélite de detección, confianza, FRP (Fire Radiative Power) y hora UTC
- **Capas GeoJSON** — espacio aéreo (CTR, LER, sectores), aeropuertos, VOR/DME, puntos visuales, helipuertos, hospitales, antenas, nodos Meshtastic y cualquier GeoJSON personalizado
- **Edificios 3D y terreno** — extrusión de edificios y elevación del terreno (requiere token Mapbox)
- **Capa de tráfico** — tráfico rodado en tiempo real (requiere token Mapbox)
- **Consola SBS** — stream en tiempo real del protocolo SBS (puerto 30003) vía Server-Sent Events
- **Soporte dual** — compatible con receptores tipo `feeder` y tipo `box`
- **Versión standalone** — archivo único `fr24-standalone.php` para despliegue sin configuración adicional

---

## Requisitos

- PHP 7.4 o superior con extensión `curl` habilitada
- Servidor web Apache (Linux) o XAMPP (Windows)
- Acceso de red al receptor FlightRadar24 (local o LAN)
- Token de Mapbox *(solo para la página de mapa)*
- MAP_KEY de NASA FIRMS *(solo para la capa de incendios)*

---

## Instalación en Linux (Apache)

### 1. Instalar Apache y PHP

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-curl git
```

Verificar que los servicios estén activos:

```bash
sudo systemctl enable apache2
sudo systemctl start apache2
```

### 2. Clonar el repositorio

```bash
cd /var/www/html
sudo git clone https://github.com/DoverKan/FR24-Fortified.git fr24
sudo chown -R www-data:www-data /var/www/html/fr24
```

### 3. Configurar el Alias en Apache

Editar la configuración del sitio por defecto:

```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Añadir dentro del bloque `<VirtualHost *:80>`:

```apache
Alias /fr24 /var/www/html/fr24/public

<Directory /var/www/html/fr24/public>
    AllowOverride All
    Require all granted
</Directory>
```

Activar el módulo rewrite y recargar Apache:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 4. Configurar la aplicación

```bash
sudo cp /var/www/html/fr24/config/config.example.php /var/www/html/fr24/config/config.php
sudo nano /var/www/html/fr24/config/config.php
```

### 5. Acceder al dashboard

Abre el navegador en `http://localhost/fr24`

---

## Instalación en Windows (XAMPP)

### 1. Instalar XAMPP

1. Descarga XAMPP desde https://www.apachefriends.org
2. Ejecuta el instalador y selecciona al menos **Apache** y **PHP**
3. Abre el **Panel de Control de XAMPP** e inicia el módulo **Apache**

### 2. Clonar el repositorio

Abre **Git Bash** o **PowerShell** y ejecuta:

```bash
cd C:\xampp\htdocs
git clone https://github.com/DoverKan/FR24-Fortified.git fr24
```

### 3. Configurar la aplicación

```bash
copy C:\xampp\htdocs\fr24\config\config.example.php C:\xampp\htdocs\fr24\config\config.php
```

Edita `config.php` con un editor de texto (Notepad++, VSCode, etc.).

### 4. Verificar que PHP cURL está habilitado

Abre `C:\xampp\php\php.ini`, busca la línea siguiente y asegúrate de que **no** tiene `;` al inicio:

```ini
extension=curl
```

Reinicia Apache desde el Panel de Control de XAMPP.

### 5. Acceder al dashboard

Abre el navegador en `http://localhost/fr24`

---

## Configuración

Editar `config/config.php` con los valores de tu receptor:

```php
define('FR24_IP',        '192.168.1.11');  // IP del receptor en la red local
define('FR24_PORT',      8754);             // Puerto del receptor
define('FR24_TYPE',      'box');            // 'feeder' o 'box'
define('ICAO',           'LEBZ');           // Código ICAO del aeropuerto (opcional)
define('LAT',            38.891944);        // Latitud del centro del mapa (opcional)
define('LON',            -6.822397);        // Longitud del centro del mapa (opcional)
define('MAPBOX_TOKEN',   'pk.eyJ...');      // Token de Mapbox — https://account.mapbox.com
define('FIRMS_MAP_KEY',  'xxxxxxxx');       // MAP_KEY de NASA FIRMS — https://firms.modaps.eosdis.nasa.gov/usfs/api/map_key/
```

`MAPBOX_TOKEN` y `FIRMS_MAP_KEY` son opcionales. Sin ellos el mapa funciona con cartografía básica de CartoDB y sin la capa de incendios.

---

## Capas del mapa

### Capas GeoJSON

Coloca archivos `.geojson` en `public/geojson/` y se cargarán automáticamente. Tipos de geometría soportados: puntos, líneas y polígonos con popups interactivos.

Consulta la guía completa en [docs/geojson.md](docs/geojson.md):
- Estructura de propiedades (`nombre`, `tipo`, `fill`, `icon`, `descripcion`)
- Tipos de geometría con ejemplos (Point, Polygon, LineString, MultiPolygon)
- Tabla de iconos disponibles
- Cómo obtener coordenadas desde Overpass Turbo, geojson.io o Google Maps
- Validación y errores comunes

### Capas meteorológicas y de situación

| Capa | Fuente | Clave necesaria | Notas |
|---|---|---|---|
| Radar meteo | RainViewer | No | Animación de los últimos ~40 min (8 fotogramas) |
| Satélite nubosidad | NASA GIBS / MODIS Terra | No | Imagen del día anterior; desactivada por defecto |
| Incendios activos | NASA FIRMS / VIIRS SNPP | `FIRMS_MAP_KEY` | Últimas 24 h; puntos clicables con detalle |

### Información de incendios (popup FIRMS)

Al hacer clic en un punto de incendio se muestra:
- Fecha y hora UTC de detección
- Satélite de detección (VIIRS SNPP)
- Nivel de confianza: `high` / `nominal` / `low`
- FRP — Fire Radiative Power en MW
- Periodo: Diurno / Nocturno

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
│   ├── partials/               # Componentes reutilizables (sidebar, topbar, footer)
│   ├── css/                    # Hojas de estilo
│   └── geojson/                # Capas GeoJSON personalizadas
│
└── docs/                       # Documentación adicional
```

---

## Páginas y endpoints

| Ruta | Descripción |
|---|---|
| `/` o `/index.php` | Dashboard principal con estado del receptor y tabla de vuelos |
| `/mapa.php` | Mapa interactivo con posiciones de aeronaves y capas adicionales |
| `/console.php` | Consola del stream SBS en tiempo real |
| `/data.php` | API JSON para refresco de datos del dashboard |
| `/sse.php` | Stream Server-Sent Events (usado por mapa y consola) |
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
| Radar / Satélite IR | RainViewer API (gratuita) |
| Nubosidad satélite | NASA GIBS / MODIS Terra (gratuita) |
| Incendios | NASA FIRMS / VIIRS SNPP NRT (MAP_KEY gratuita) |
| HTTP | PHP cURL (peticiones paralelas) |
| Streaming | Server-Sent Events (SSE) |

---

## Seguridad

El archivo `config/config.php` está excluido de git mediante `.gitignore` para evitar exponer credenciales. Usa siempre `config/config.example.php` como base y nunca confirmes el archivo de configuración real al repositorio.

---

## Licencia

Este proyecto está licenciado bajo [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/) — consulta el archivo [LICENSE](LICENSE) para más detalles.

Puedes usar, modificar y redistribuir este proyecto siempre que:
- **Cites al autor** (DoverKan) de forma apropiada
- **No lo uses con fines comerciales**
- **Distribuyas las obras derivadas** bajo la misma licencia
