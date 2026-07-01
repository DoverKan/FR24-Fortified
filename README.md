# FlightRadar24-Fortified

Panel de monitoreo web para receptores ADS-B de FlightRadar24 (Feeder y Box). Muestra en tiempo real el estado del receptor, estadísticas de vuelos y un mapa interactivo de posiciones de aeronaves con capas meteorológicas, de situación aérea y espacios aéreos.

**Repositorio:** https://github.com/DoverKan/FR24-Fortified

---

## Características

- **Dashboard en tiempo real** — estado del receptor, estadísticas y tabla de vuelos activos con auto-refresco
- **Mapa interactivo** — Mapbox GL con múltiples estilos de base (oscuro, calles, satélite, terreno, Standard) y capas GeoJSON configurables
- **Seguimiento de aeronaves** — posiciones, rumbo, altitud y tracks históricos en tiempo real vía stream SBS (puerto 30003); iconos con código de colores por altitud y alerta de squawk de emergencia
- **Controles del mapa** — centrar en todos los aviones, seguir un avión seleccionado, retener tracks ilimitados, exportar como PNG, anillos de distancia NM, perfil de elevación, regla de medición, inspección de coordenadas
- **Ruta de vuelo** — arco geodésico origen → destino con avión animado sobre la curva; datos de ruta obtenidos automáticamente desde adsbdb.com
- **Capas meteorológicas RainViewer** — radar de precipitación animado (~40 min, 8 fotogramas) y satélite de nubosidad (NASA GIBS / MODIS Terra)
- **Capas meteorológicas OWM** — viento, presión e isobares y precipitación en tiempo real (OpenWeatherMap), cada una con toggle independiente en el panel de capas
- **Incendios activos** — capa FIRMS (NASA) con puntos interactivos; muestra satélite, confianza, FRP y hora UTC
- **Espacios aéreos (OpenAIP)** — CTR, TMA, ATZ, zonas restringidas, peligrosas y prohibidas con popup de clase ICAO, base y techo; datos cargados vía proxy PHP (clave nunca expuesta al navegador)
- **Capas GeoJSON** — espacio aéreo (CTR, LER, sectores), aeropuertos, VOR/DME, puntos visuales, helipuertos, hospitales, antenas, nodos Meshtastic y cualquier GeoJSON personalizado
- **Edificios 3D y terreno** — extrusión de edificios por tipo y elevación del terreno (requiere token Mapbox)
- **Capa de tráfico** — tráfico rodado en tiempo real (requiere token Mapbox)
- **Radar sweep** — animación de barrido radar canvas sobre la posición del receptor con iluminación de aeronaves al paso del haz
- **Iluminación solar** — capa sky + fog dinámica calculando posición real del sol (amanecer, día, atardecer, noche, estrellas)
- **Dibujo libre** — herramienta de trazado de polígonos, líneas y puntos sobre el mapa
- **Consola SBS** — stream en tiempo real del protocolo SBS vía Server-Sent Events
- **Soporte dual** — compatible con receptores tipo `feeder` y tipo `box`

---

## Requisitos

- PHP 7.4 o superior con extensión `curl` habilitada
- Servidor web Apache (Linux) o XAMPP (Windows)
- Acceso de red al receptor FlightRadar24 (local o LAN)
- Token de Mapbox *(opcional — para estilos premium, terreno 3D, edificios y tráfico)*
- MAP_KEY de NASA FIRMS *(opcional — para capa de incendios)*
- API Key de OpenWeatherMap *(opcional — para capas de viento, presión y precipitación)*
- API Key de openAIP.net *(opcional — para espacios aéreos)*

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
define('OWM_KEY',        'xxxxxxxx');       // API Key de OpenWeatherMap — https://openweathermap.org/api
define('OPENAIP_KEY',    'xxxxxxxx');       // API Key de openAIP.net — https://www.openaip.net
```

Las claves `MAPBOX_TOKEN`, `FIRMS_MAP_KEY`, `OWM_KEY` y `OPENAIP_KEY` son opcionales. Sin ellas el mapa funciona con cartografía básica de CartoDB y sin las capas que las requieren.

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
| Satélite nubosidad | NASA GIBS / MODIS Terra | No | Imagen del día anterior |
| Incendios activos | NASA FIRMS / VIIRS SNPP | `FIRMS_MAP_KEY` | Últimas 24 h; puntos clicables con detalle |
| Viento | OpenWeatherMap | `OWM_KEY` | Velocidad del viento como gradiente de color |
| Presión | OpenWeatherMap | `OWM_KEY` | Isobares; siempre visible con tiempo activo |
| Precipitación | OpenWeatherMap | `OWM_KEY` | Lluvia y nieve activas |

> Las claves de OWM recién creadas pueden tardar hasta 2 horas en activarse.

### Espacios aéreos (OpenAIP)

Los espacios aéreos se cargan automáticamente al iniciar el mapa si `OPENAIP_KEY` está configurado. Los datos se obtienen directamente desde el navegador (igual que FIRMS y RainViewer), por lo que la clave viaja al cliente en el HTML de la página. Para instalaciones privadas/personales esto es aceptable; si necesitas proteger la clave, usa `airspaces.php` como proxy y ajusta `addOpenAIPLayer()` para apuntar a él. Los datos se cachean en memoria durante la sesión.

| Grupo en el panel | Tipos incluidos | Color | Visible al cargar |
|---|---|---|---|
| Restric. / Peligro / Prohib. | P, R, D, ADIZ, Alerta, Aviso | Rojo / naranja | Sí |
| CTR / TMA / ATZ | CTR, TMA, ATZ, RMZ, TMZ, TRA, TSA, CTA | Azul / cian | Sí |
| FIR / UIR | FIR, UIR, ACC | Gris | No (oculto por defecto) |

Clic en cualquier espacio aéreo → popup con nombre, clase ICAO, tipo, base y techo.

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
│   ├── mapa2.php               # Mapa interactivo (variante con botón toggle panel)
│   ├── console.php             # Consola del stream SBS
│   ├── data.php                # Endpoint JSON para auto-refresco
│   ├── sse.php                 # Endpoint Server-Sent Events
│   ├── airspaces.php           # Proxy PHP para OpenAIP (mantiene la clave en servidor)
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
| `/mapa2.php` | Variante del mapa con botón de toggle para el panel de capas |
| `/console.php` | Consola del stream SBS en tiempo real |
| `/data.php` | API JSON para refresco de datos del dashboard |
| `/sse.php` | Stream Server-Sent Events (usado por mapa y consola) |
| `/airspaces.php` | Proxy para OpenAIP API (parámetro opcional `?country=XX`) |
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
| Capas meteorológicas | OpenWeatherMap API (OWM_KEY gratuita) |
| Espacios aéreos | openAIP.net API (OPENAIP_KEY gratuita) |
| Rutas de vuelo | adsbdb.com API (gratuita, sin clave) |
| HTTP | PHP cURL (peticiones paralelas y proxy) |
| Streaming | Server-Sent Events (SSE) |

---

## Seguridad

El archivo `config/config.php` está excluido de git mediante `.gitignore` para evitar exponer credenciales. La clave de Mapbox, FIRMS y OWM se pasan al JS del cliente (patrón estándar en instalaciones personales). La clave de OpenAIP también se pasa al navegador para que éste haga la llamada directamente a la API, ya que PHP/XAMPP en Windows puede tener problemas de resolución DNS con hosts externos. Usa siempre `config/config.example.php` como base y nunca confirmes el archivo de configuración real al repositorio.

---

## Licencia

Este proyecto está licenciado bajo [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/) — consulta el archivo [LICENSE](LICENSE) para más detalles.

Puedes usar, modificar y redistribuir este proyecto siempre que:
- **Cites al autor** (DoverKan) de forma apropiada
- **No lo uses con fines comerciales**
- **Distribuyas las obras derivadas** bajo la misma licencia
